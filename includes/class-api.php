<?php

/* ******************************************** */
/*   Copyright: go4seven GmbH                   */
/*         http://www.surpriseme.com            */
/* ******************************************** */

namespace Surpriseme;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('SURPRISEME_API_URL', 'https://api.surpriseme.com/v1/');

if (!class_exists('\Surpriseme\Api')) {
    class Api
    {

        public $debug = false;
        private $settings = null;
        private $invoice_settings = null;
        private $requestLog = array();

        public function __construct()
        {
            $this->load_settings();
        }

        public function getRequestLog()
        {
            return $this->requestLog;
        }

        private function load_settings()
        {
            $this->settings = get_option(\SurprisemeMain::$settings_page_name);
            $this->invoice_settings = get_option(\SurprisemeMain::$settings_page_name . '_invoice');
        }

        private function check_settings()
        {
            if ((empty($this->settings['surpriseme_api_email'])) || (empty($this->settings['surpriseme_api_token']))) {
                return false;
            }
            return true;
        }

        public function request($_service, $_data, $_type = 'post', $_file = null)
        {
            $_credentials = trim($this->settings['surpriseme_api_email']).':'.trim($this->settings['surpriseme_api_token']);

            $_curl = curl_init();

            $headers = array(
                'Authorization: Basic '.base64_encode($_credentials)
            );

            if (!empty($_file)) {

                /*$headers[] = 'Content-Type:multipart/form-data';
                $_file_data = file_get_contents($_file);
                $_post_data['filedata'] = "@$_file_data";
                $_post_data['filename'] = basename($_file);*/
            }

            curl_setopt($_curl, CURLOPT_URL, SURPRISEME_API_URL.$_service);
            if (in_array($_type, array('get', 'post', 'put'))) {
                curl_setopt($_curl, CURLOPT_CUSTOMREQUEST, strtoupper($_type));
            }
            curl_setopt($_curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($_curl, CURLOPT_POSTFIELDS, $_data);
            curl_setopt($_curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($_curl, CURLOPT_VERBOSE, 1);
            curl_setopt($_curl, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($_curl, CURLOPT_TIMEOUT, 30);

            $_responseJson = curl_exec($_curl);
            $this->requestLog[] = $_responseJson;
            $_response = json_decode($_responseJson, true);
            if ($this->debug) {
                $_info = curl_getinfo($_curl);
            }

            curl_close($_curl);

            if (!isset($_response['status'])) {
                $_response['status'] = null;
            }
            if ($_response['status'] == 'error' && !empty($_response['message'])) {
                $savedError = get_option('surpriseme_plugin_error');
                if (empty($savedError)) {
                    add_option('surpriseme_plugin_error', array('time' => time(), 'msg' => $_response['message']));
                } else {
                    update_option('surpriseme_plugin_error', array('time' => time(), 'msg' => $_response['message']));
                }
            }

            if ($this->debug) {
                $debug_array = array(
                    'url' => SURPRISEME_API_URL.$_service,
                    'auth' => $_credentials,
                    'auth_base_64' => base64_encode($_credentials),
                    'request' => $_data,
                    'response' => $_response,
                    'info' => $_info
                );
                echo '<pre>'.print_r($debug_array, true).'</pre>';
            }

            return $_response;
        }

        public function api_test()
        {
            if (!$this->check_settings()) {
                return false;
            }
            $_response = $this->request('templates', array(), 'get');

            if (!empty($_response)) {
                return $_response;
            }

            return false;
        }

        public function get_shipping_thumbnail() {

            if (!$this->check_settings()) {
                return false;
            }

            $surpriseme_session_id = self::get_surpriseme_session_id();
            $_response = $this->request('orders/'.$surpriseme_session_id.'/thumbnail', array(), 'get');

            if (!empty($_response)) {
                return $_response;
            }

            return false;
        }

        public function get_shipping_order() {

            if (!$this->check_settings()) {
                return false;
            }

            $surpriseme_session_id = self::get_surpriseme_session_id();
            $_response = $this->request('orders/'.$surpriseme_session_id, array(), 'get');

            if (!empty($_response)) {
                return $_response;
            }

            return false;
        }

        public function send_order($surpriseme_session_id = null, $coupon = null, $price = 0) {

            if (empty($surpriseme_session_id)) {
                $surpriseme_session_id = self::get_surpriseme_session_id();
            }

            $shop_name = get_bloginfo('name');
            $shop_url = get_site_url();
            $price = $price;
            $_response = $this->request('orders/'.$surpriseme_session_id, array('coupon' => $coupon, 'sent' => true, 'draft' => false, 'shop_name' => $shop_name, 'shop_url' => $shop_url, 'price' => $price), 'post');

            if (!empty($_response)) {
                return $_response;
            }

            return false;
        }

        public static function get_surpriseme_session_id() {
            $surpriseme_session_id = WC()->session->get('surpriseme_session_id');
            if (empty($surpriseme_session_id)) {
                $surpriseme_session_id = time().'_'.mt_rand(11111, 99999);
                WC()->session->set('surpriseme_session_id', $surpriseme_session_id);
            }
            return $surpriseme_session_id;
        }

        public static function unset_surpriseme_session_id() {
            WC()->session->set('surpriseme_session_id', '');
        }

        public function get_surpriseme_hash() {
            $_response = $this->request('hash', array(), 'get');

            if(!empty($_response['hash'])) {
                $return = 'id=' . trim($this->settings['surpriseme_api_email']) . '&';
                $return .= 'hash=' . $_response['hash'];

                return $return;
            } else {
                return false;
            }
        }
    }
}