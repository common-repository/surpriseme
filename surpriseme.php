<?php
/**
 * Plugin Name: surpriseme
 * Plugin URI: http://www.surpriseme.com
 * Description:
 * Version: 1.0.1
 * Author: go4seven GmbH
 * Author URI: https://surpriseme.com/
 *
 * Text Domain: surpriseme
 *
 * @package surpriseme
 * @category Core
 * @author surpriseme
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once('includes/class-shipping-options.php');

if (!class_exists('SurprisemeMain')) {

    final class SurprisemeMain
    {

        // settings fields...
        private $settings = null;

        // object fields...
        private $api = null;
        private $backend = null;

        // grouping fields...
        public static $text_domain = 'surpriseme';
        public static $settings_group = 'surpriseme_settings_group';
        public static $settings_page_name = 'surpriseme_plugin_settings';

        public static $plugin_url = '';

        public function __construct()
        {
            self::$plugin_url = plugin_dir_url(__FILE__);
            $this->init();
            do_action('surpriseme_loaded');
        }

        private function init()
        {
            $this->language();
            $this->includes();
            $this->load_settings();
            $this->init_api();
            $this->init_backend();
            $this->add_actions();
        }

        // language methods...
        private function language()
        {
        }

        // include methods...
        private function includes()
        {
            include_once('includes/class-backend.php');
            include_once('includes/class-api.php');
        }

        // settings methods...
        private function load_settings()
        {
            $this->settings = get_option(self::$settings_page_name);
        }

        // init methods...
        private function init_api()
        {
            $this->api = new \Surpriseme\Api();
        }

        private function init_backend()
        {
            $this->backend = new \Surpriseme\Backend();
        }

        // actions methods...
        private function add_actions()
        {
            try {

                // API test...
                if (isset($_REQUEST['surpriseme_api_test'])) {
                    if (intval($_REQUEST['surpriseme_api_test']) == 1) {
                        add_action('admin_notices', array($this, 'api_test'));
                    }
                }

                update_option('smart_coupons_is_send_email', 'no');

                add_action('woocommerce_thankyou', array($this, 'after_buy_action'), 10, 1);
                add_action('woocommerce_order_status_completed', array($this, 'order_status_completed'), 12, 1);
            }
            catch(\Exception $_exception) {
                echo $_exception;
                return false;
            }
        }

        public function api_test() {

            $result = $this->api->api_test();

            $message = '';
            if(!empty($result)) {
                if (isset($result['message'])) {
                    $message = '<div class="error">';
                    $message .= '<p>'.__('Es konnte keine Verbindung hergestellt werden oder die API-E-Mail und der API-Token stimmen nicht mit denen unter surpriseme.com überein! Bitte kontaktiere support@surpriseme.com.', \SurprisemeMain::$text_domain).'</p>';
                    $message .= '</div>';
                } else {
                    $message = '<div class="notice notice-success">';
                    $message .= '<p>' . __('Der Test war erfolgreich', \SurprisemeMain::$text_domain) . '</p>';
                    $message .= '</div>';
                }
            }

            echo $message;
        }

        public function after_buy_action($order_id) {
            $this->init();
            $order = new WC_Order($order_id);

            $surpriseme_session_id = \Surpriseme\Api::get_surpriseme_session_id();
            $surpriseme_coupon = 'placeholder coupon code';
            $surpriseme_price = 0;

            $_pf = new WC_Product_Factory();
            foreach ($order->get_items() as $key => $lineItem) {
                $surpriseme_enabled = get_post_meta($lineItem['product_id'], '_surpriseme_enabled', true);
                if($surpriseme_enabled === 'yes') {
                    $surpriseme_price += $lineItem['line_total'] + $lineItem['line_tax'];
                }
            }

            update_post_meta($order->post->ID, 'surpriseme_id', $surpriseme_session_id);
            update_post_meta($order->post->ID, 'surpriseme_coupon', $surpriseme_coupon);
            update_post_meta($order->post->ID, 'surpriseme_price', $surpriseme_price);

            \Surpriseme\Api::unset_surpriseme_session_id();

            $this->api->request('orders/'.$surpriseme_session_id, array('draft' => 'false'), 'put');
        }

        public function order_status_completed($order_id) {

            $order = new WC_Order($order_id);

            $surpriseme_session_id = get_post_meta($order->post->ID, 'surpriseme_id');
            if (is_array($surpriseme_session_id)) {
                $surpriseme_session_id = $surpriseme_session_id[0];
            }
            $surpriseme_coupon = get_post_meta($order->post->ID, 'surpriseme_coupon');
            if (is_array($surpriseme_coupon)) {
                $surpriseme_coupon = $surpriseme_coupon[0];
            }
            $surpriseme_price = get_post_meta($order->post->ID, 'surpriseme_price');
            if (is_array($surpriseme_price)) {
                $surpriseme_price = $surpriseme_price[0];
            }
            $smart_coupon = get_post_meta($order->post->ID, 'sc_coupon_receiver_details');
            if (is_array($smart_coupon)) {
                $surpriseme_coupon = $smart_coupon[0][0]['code'];
                if(!empty($surpriseme_coupon)) {
                    update_post_meta($order->post->ID, 'surpriseme_coupon', $smart_coupon[0][0]['code']);
                }
            }
            if (!empty($surpriseme_session_id)) {
                $this->api->send_order($surpriseme_session_id, $surpriseme_coupon, $surpriseme_price);
            }
        }
    }
}

$GLOBALS['surpriseme'] = new SurprisemeMain();