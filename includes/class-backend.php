<?php

/* ******************************************** */
/*   Copyright: go4seven GmbH                   */
/*         http://www.surpriseme.com            */
/* ******************************************** */

namespace Surpriseme;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('SURPRISEME_ACCOUNT_URL', 'https://business.surpriseme.com');

if (!class_exists('\Surpriseme\Backend')) {
    class Backend {

        // grouping fields...
        public static $settings_page_url_param = 'surpriseme_settings';
        public static $settings_plugin_section_id = 'surpriseme_plugin_settings_id';

        // helper fields...
        private $settings_errors = array();
        private $settings_updated = null;
        private $active_tab = null;

        // tabs fields...
        private $settings_tabs = array('general');
        private $settings_tabs_names = array();

        public function __construct() {
            $this->init();
        }

        // init methods...
        private function init() {

            $this->settings_tabs_names = array(
                'general' => __('Allgemein', \SurprisemeMain::$text_domain),
            );

            $this->add_actions();
        }

        // actions methods...
        private function add_actions() {
            try {
                add_action('admin_head', array(&$this, 'admin_head'));
                add_action('admin_footer', array(&$this, 'admin_footer'));
                add_action('admin_menu', array(&$this, 'settings_menu'));
                add_action('admin_init', array(&$this, 'settings_init'));
                add_action('admin_notices', array(&$this, 'settings_notices'));
            }
            catch(\Exception $_exception) {
                echo $_exception;
            }
        }

        public function admin_head() {
        }

        public function admin_footer() {
        }

        public function settings_menu() {
            add_submenu_page(
                'woocommerce',
                __('surpriseme', \SurprisemeMain::$text_domain),
                __('surpriseme', \SurprisemeMain::$text_domain),
                'manage_woocommerce',
                self::$settings_page_url_param,
                array($this, 'settings_page')
            );
        }

        // init methods...
        public function settings_init() {

            // general...
            register_setting(
                \SurprisemeMain::$settings_group,
                \SurprisemeMain::$settings_page_name,
                array($this, 'sanitize_callback')
            );

            add_settings_section(
                self::$settings_plugin_section_id,
                __('Grundeinstellungen', \SurprisemeMain::$text_domain),
                array($this, 'settings_section_callback'),
                \SurprisemeMain::$settings_page_name
            );

            $this->add_settings_field('surpriseme_api_email', __('API-E-Mail', \SurprisemeMain::$text_domain), 'general', 'settings_text_callback', __('API-E-Mail für surpriseme. Die API-E-Mail finden Sie in Ihren Provider-Einstellungen auf: ', \SurprisemeMain::$text_domain).'<a href="'.SURPRISEME_ACCOUNT_URL.'" target="_blank">'.SURPRISEME_ACCOUNT_URL.'</a>');
            $this->add_settings_field('surpriseme_api_token', __('API-Token', \SurprisemeMain::$text_domain), 'general', 'settings_text_callback', __('API-Token für surpriseme. Den API-Token finden Sie in Ihren Provider-Einstellungen auf: ', \SurprisemeMain::$text_domain).'<a href="'.SURPRISEME_ACCOUNT_URL.'" target="_blank">'.SURPRISEME_ACCOUNT_URL.'</a>');
            $this->add_settings_field('surpriseme_api_check', __('API-Test', \SurprisemeMain::$text_domain), 'general', 'settings_check_api', __('Testet ob eine Verbindung mit surpriseme und dem Lizenzkey hergestellt werden kann.', \SurprisemeMain::$text_domain));
        }

        public function add_hidden_inputs() {

            $_sSubmitText = __('Änderungen übernehmen', \SurprisemeMain::$text_domain);
            $_hidden_inputs = array();

            switch ($this->active_tab) {
                case 'shipping_presets':

                    $_sSubmitText = isset($this->shipping_preset_id) ? __('Speichern', \SurprisemeMain::$text_domain) : '';

                    $_hidden_inputs = array(
                        array('id' => 'preset_id', 'name' => 'preset_id', 'value' => $this->shipping_preset_id)
                    );
                    break;
            }

            foreach ($_hidden_inputs as $_hidden_input) {
                echo '<input type="hidden" id="'.\SurprisemeMain::$settings_page_name.'_'.$_hidden_input['id'].'" name="'.\SurprisemeMain::$settings_page_name.'_'.$_hidden_input['name'].'" value="'.$_hidden_input['value'].'" />';
            }

            return $_sSubmitText;
        }

        public function add_settings_field($_id, $_name, $_type, $_callback = 'settings_text_callback', $_description = '', $_default = null, $_options = null) {

            if ($_type == 'general') {$_type = '';}

            add_settings_field(
                $_id,
                $_name,
                array($this, $_callback),
                \SurprisemeMain::$settings_page_name.(!empty($_type) ? '_'.$_type : ''),
                self::$settings_plugin_section_id.(!empty($_type) ? '_'.$_type : ''),
                array(
                    'page' => \SurprisemeMain::$settings_page_name.(!empty($_type) ? '_'.$_type : ''),
                    'id' => $_id,
                    'description' => $_description,
                    'default' => $_default,
                    'options' => $_options
                )
            );
        }

        public function settings_notices() {
            settings_errors();
        }

        public function sanitize_callback($_data) {

            $_notice_message = null;
            $_notice_type = null;

            if (!empty($this->settings_errors)) {
                $_notice_message = '';
                foreach ($this->settings_errors as $_error) {
                    $_notice_message .= __($_error, \SurprisemeMain::$text_domain).'<br>';
                }
                $_notice_type = 'error';
            }
            else {
                $_notice_message = __('Ihre Einstellungen wurden erfolgreich gespeichert.', \SurprisemeMain::$text_domain);
                $_notice_type = 'updated';
            }

            add_settings_error(
                'surpriseme_settings_messages_id',
                esc_attr('settings_updated'),
                $_notice_message,
                $_notice_type
            );

            return $_data;
        }

        public function settings_page() {
            ?>
            <div class="wrap">
                <h2><?php _e('surpriseme Einstellungen', \SurprisemeMain::$text_domain); ?></h2>

                <?php
                $this->active_tab = empty($_REQUEST['tab']) ? 'general' : sanitize_title($_REQUEST['tab']);
                ?>

                <h2 class="nav-tab-wrapper">
                    <?php
                    foreach ($this->settings_tabs as $_tab) {
                        $this->add_tab(self::$settings_page_url_param, __($this->settings_tabs_names[$_tab], \SurprisemeMain::$text_domain), $_tab, $this->active_tab);
                    }
                    ?>
                </h2>

                <form action="options.php" method="POST" target="_self">
                    <?php
                    $_sSubmitText = $this->add_hidden_inputs();
                    if ($this->active_tab == 'general') {
                        settings_fields(\SurprisemeMain::$settings_group);
                        do_settings_sections(\SurprisemeMain::$settings_page_name);
                    }
                    else if (in_array($this->active_tab, $this->settings_tabs)) {
                        settings_fields(\SurprisemeMain::$settings_group.'_'.$this->active_tab);
                        do_settings_sections(\SurprisemeMain::$settings_page_name.'_'.$this->active_tab);
                    }
                    if (!empty($_sSubmitText)) {
                        submit_button($_sSubmitText);
                    }
                    ?>
                </form>
            </div>
            <?php
        }

        public function add_tab($_page, $_tab_name, $_tab_value) {
            ?>
            <a href="<?php echo add_query_arg(array('tab' => $_tab_value), '?page='.$_page); ?>" class="nav-tab <?php echo $this->active_tab == $_tab_value ? 'nav-tab-active' : ''; ?>"><?php echo $_tab_name; ?></a>
            <?php
        }

        public function settings_check_api($_args) {

            $_html = '<a href="'.get_admin_url(null, 'admin.php?page=surpriseme_settings').'&surpriseme_api_test=1" id="'.$_args['id'].'">'.__('testen…', \SurprisemeMain::$text_domain).'</a>';
            if (!empty($_args['description'])) {
                $_html .= '<p class="description">'.$_args['description'].'</p>';
            }
            echo $_html;
        }

        public function settings_text_callback($_args) {
            if (empty($_args['size'])) {
                $_args['size'] = 80;
            }

            $_value = '';
            if (!empty($_args['default'])) {
                $_value = $_args['default'];
            }

            $_options = get_option($_args['page']);
            if (isset($_options[$_args['id']])) {
                $_value = $_options[$_args['id']];
            }

            $_html = '<input type="text" id="'.$_args['id'].'" name="'.$_args['page'].'['.$_args['id'].']" value="'.$_value.'" size="'.$_args['size'].'"';
            if (!empty($_args['min_length'])) {
                $_html .= 'min-length="'.$_args['min_length'].'" ';
            }
            if (!empty($_args['max_length'])) {
                $_html .= 'max-length="'.$_args['max_length'].'" ';
            }
            $_html .= ' />';

            if (!empty($_args['description'])) {
                $_html .= '<p class="description">'.$_args['description'].'</p>';
            }

            echo $_html;
        }

        public function settings_textarea_callback($_args) {
            if (empty($_args['size'])) {
                $_args['size'] = 80;
            }

            if (empty($_args['rows'])) {
                $_args['rows'] = 8;
            }

            $_value = '';
            if (!empty($_args['default'])) {
                $_value = $_args['default'];
            }

            $_options = get_option($_args['page']);
            if (isset($_options[$_args['id']])) {
                $_value = $_options[$_args['id']];
            }

            $_html = '<textarea id="'.$_args['id'].'" name="'.$_args['page'].'['.$_args['id'].']" cols="'.$_args['size'].'" rows="'.$_args['rows'].'" ';
            if (!empty($_args['min_length'])) {
                $_html .= 'min-length="'.$_args['min_length'].'" ';
            }
            if (!empty($_args['max_length'])) {
                $_html .= 'max-length="'.$_args['max_length'].'" ';
            }
            $_html .= '>'.esc_textarea($_value).'</textarea>';

            if (!empty($_args['description'])) {
                $_html .= '<p class="description">'.$_args['description'].'</p>';
            }

            echo $_html;
        }

        public function settings_select_callback($_args) {
            $_value = '';
            if (!empty($_args['default'])) {
                $_value = $_args['default'];
            }

            $_options = get_option($_args['page']);
            if (isset($_options[$_args['id']])) {
                $_value = $_options[$_args['id']];
            }

            $_html = '<select id="'.$_args['id'].'" name="'.$_args['page'].'['.$_args['id'].']">';
            foreach ($_args['options'] as $_option) {
                $_html .= '<option value="'.$_option['value'].'"';
                if($_value == $_option['value']) { $_html .= ' selected'; }
                $_html .= '>'.$_option['name'].'</option>';
            }

            if (!empty($_args['description'])) {
                $_html .= '<p class="description">'.$_args['description'].'</p>';
            }

            echo $_html;
        }

        public function settings_checkbox_callback($_args) {

            $_value = '';
            $_options = get_option($_args['page']);
            if (isset($_options[$_args['id']])) {
                $_value = $_options[$_args['id']];
            }

            $_html = '<input type="checkbox" id="'.$_args['id'].'" name="'.$_args['page'].'['.$_args['id'].']" value="1"';
            if($_value == 1) { $_html .= ' checked'; }
            $_html .= ' />';

            $_html .= '<p class="description">'.$_args['description'].'</p>';

            echo $_html;
        }

        public function settings_section_callback() {
            return null;
        }
    }
}