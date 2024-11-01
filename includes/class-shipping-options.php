<?php

/* ******************************************** */
/*   Copyright: go4seven GmbH                   */
/*         http://www.surpriseme.com            */
/* ******************************************** */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Surpriseme_Shipping_Options')) {

    function surpriseme_shipping_methods_init() {

        class Surpriseme_Shipping_Options extends \WC_Shipping_Method {

            private $options_array_label = '';

            // object fields...
            private $api = null;

            public function __construct() {

                $this->id = 'surpriseme_shipping';
                $this->method_title = __('surpriseme Settings', \SurprisemeMain::$text_domain);
                $this->title = __('surpriseme', \SurprisemeMain::$text_domain);
                $this->options_array_label = 'surpriseme_shipping_options';
                $this->method_description = __('Shipping Options Description', \SurprisemeMain::$text_domain);

                add_action('woocommerce_update_options_shipping_'.$this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_update_options_shipping_'.$this->id, array($this, 'process_shipping_options'));

                add_filter('woocommerce_cart_needs_shipping', array($this, 'cart_needs_shipping'), 50);

                $this->init();
            }

            function init() {

                $this->init_api();

                $this->init_filters();
                $this->init_actions();
                
                $this->init_form_fields();
                $this->init_settings();

                $this->fee = $this->get_option('fee');

                $this->get_shipping_options();
            }

            // init methods...
            private function init_api()
            {
                $this->api = new \Surpriseme\Api();
            }

            function init_filters() {
                add_filter('woocommerce_shipping_methods', array(&$this, 'add_shipping_methods'));
            }

            function init_actions() {

                add_action('woocommerce_cart_totals_after_shipping', array(&$this, 'cart_totals_after_shipping'));
                add_action('woocommerce_review_order_after_shipping', array(&$this, 'review_order_after_shipping'));
            }

            // admin options methods...
            function admin_options() {
                ?>
                    <h3><?php echo $this->method_title; ?></h3>
                    <p><?php _e('surpriseme description Text', \SurprisemeMain::$text_domain); ?></p>
                    <table class="form-table">
                        <?php $this->generate_settings_html(); ?>
                    </table>
                <?php
            }

            function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable', \SurprisemeMain::$text_domain),
                        'type' => 'checkbox',
                        'label' => __('Enable surpriseme Shipping option', \SurprisemeMain::$text_domain),
                        'default' => 'no'
                    ),
                    'fee' => array(
                        'title' => __('Delivery Fee', \SurprisemeMain::$text_domain),
                        'type' => 'price',
                        'description' => __('What fee do you want to charge for local delivery, disregarded if you choose free. Leave blank to disable.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                        'placeholder' => wc_format_localized_price(0)
                    ),
                );
            }

            // shipping options methods...
            function process_shipping_options() {

                $options = array();

                if (isset($_POST[$this->id.'_options'])) {
                    $options = array_map('wc_clean', $_POST[$this->id.'_options']);
                }

                update_option($this->options_array_label, $options);

                $this->get_shipping_options();
            }

            function get_shipping_options() {
                $this->shipping_options = array_filter((array)get_option($this->options_array_label));
            }

            // other methods...
            function add_shipping_methods($methods) {
                $methods[] = $this;
                return $methods;
            }

            function cart_totals_after_shipping() {
                $this->review_order_after_shipping();
            }

            function review_order_after_shipping() {

                global $woocommerce;

                $shipping_method = $woocommerce->session->get('chosen_shipping_methods');
                if (is_array($shipping_method) && in_array($this->id, $shipping_method)) {

                    $response = $this->api->get_shipping_order();

                    //if (!empty($response['thumbnail'])) {
                        ?>
                        <script>
                            function surpriseme_gift_edit_modal(show) {
                                if (show == false) {
                                    jQuery('#surpriseme_gift_edit_modal').hide();
                                    jQuery('input[name^=shipping_method]:checked').trigger('change');
                                }
                                else {
                                    if (jQuery('#surpriseme_gift_edit_modal').length < 1) {

                                        var modal = '<div id="surpriseme_gift_edit_modal" style="z-index:99999997; position:fixed; top:0px; left:0px; width:100%; height:100%;">';
                                        modal += '<div style="z-index:99999998; position:absolute; margin:0; padding:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">&nbsp;</div>';
                                        modal += '<div style="z-index:99999999; position:absolute; top:5%; left:5%; height:90%; width:90%; margin:auto; padding:15px; border-radius:5px; background:#fff;">';
                                        modal += '<table style="width:100%; height:100%;" cellspacing="0" cellpadding="0">';
                                        modal += '<tr><td><iframe src="https://business.surpriseme.com/plugin/<?php echo \Surpriseme\Api::get_surpriseme_session_id(); ?>?<?php $apiObj = new \Surpriseme\Api(); echo $apiObj->get_surpriseme_hash(); ?>" style="width:100%; height:100%; border:0;" frameborder="0" border="0"></iframe></td></tr>';
                                        modal += '<tr><td style="height:50px; text-align:center;"><a href="javascript:;" target="_self" onclick="surpriseme_gift_edit_modal(false);" class="button alt" style="width:100%;"><?php _e('Close', \SurprisemeMain::$text_domain); ?></a></td></tr>';
                                        modal += '</table>';
                                        modal += '</div>';
                                        modal += '</div>';

                                        jQuery('body').append(modal);
                                    }

                                    jQuery('#surpriseme_gift_edit_modal').show();
                                }
                            }
                            <?php if ($response['required_fields'] != true) { ?>
                            jQuery(document).ready(function() {
                                jQuery('.wc-proceed-to-checkout').hide();
                            });
                            <?php } ?>
                            jQuery('.gift-certificate').hide();
                        </script>
                        <tr>
                            <th>&nbsp;</th>
                            <td>
                                <div style="<?php if(!empty($response['background_thumbnail'])) { ?>background-image: url(<?php echo $response['background_thumbnail']; ?>); background-position: center; background-size: cover;<?php } ?> max-width:150px; max-height:150px; border-radius: 1em;">
                                    <img src="<?php echo $response['thumbnail']; ?>" style="max-width:150px; max-height:150px;"/>
                                </div><br />
                                Titel: "<?php echo (!empty($response['title']) ? nl2br($response['title']) : __('bitte bearbeiten…', \SurprisemeMain::$text_domain) ) ?>"<br />
                                Text: "<?php echo (!empty($response['text']) ? nl2br($response['text']) : __('bitte bearbeiten…', \SurprisemeMain::$text_domain) ) ?>"<br />
                                <a href="javascript:;" target="_self" onclick="surpriseme_gift_edit_modal(true);">Geschenk bearbeiten</a>
                                <?php if ($response['required_fields'] != true) { ?>
                                <br /><br /><small><em>(Inhalt Fehlen, bitte bearbeiten Sie das Geschenk)</em></small>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php
                    //}
                }
            }

            function is_settings_enabled() {
                return 'yes' === $this->settings['enabled'];
            }

            function is_all_enabled() {
                if ($this->is_enabled() && $this->is_settings_enabled()) {
                    return true;
                }
                return false;
            }

            function is_available($package) {

                global $woocommerce;

                if (!$this->is_all_enabled()) {
                    return false;
                }

                $surpriseme_allowed = false;
                $items = $woocommerce->cart->get_cart();
                if (!empty($items)) {
                    foreach ($items as $item => $values) {
                        $surpriseme_enabled = get_post_meta($values['product_id'], '_surpriseme_enabled', true);
                        if ($surpriseme_enabled === 'yes') {
                            $surpriseme_allowed = true;
                        }
                    }
                }

                if (!$surpriseme_allowed) {
                    return false;
                }

                return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', true, $package);
            }

            function calculate_shipping($package = array()) {

                $fee = (trim($this->fee) == '') ? 0 : $this->fee;

                $shipping_rate_cost = $fee;

                $rate = array(
                    'id' => $this->id,
                    'label' => $this->title,
                    'cost' => $shipping_rate_cost
                );

                $this->add_rate($rate);
            }

            public function cart_needs_shipping($needs_shipping) {

                /*global $woocommerce;
                $items = $woocommerce->cart->get_cart();
                if (!empty($items)) {
                    foreach ($items as $item => $values) {
                        $product = wc_get_product($values['product_id']);
                        echo '<pre>'.print_r($values, true).'</pre>';
                        echo '<pre>'.print_r($product, true).'</pre>';
                        $_surpriseme_allowed = get_post_meta($values['product_id'], '_surpriseme_allowed', true);
                        echo 'Surpriseme_allowed: '.$_surpriseme_allowed;
                    }
                }*/

                return $needs_shipping;
            }
        }

        new Surpriseme_Shipping_Options();
    }

    add_action('woocommerce_shipping_init', 'surpriseme_shipping_methods_init');

    // product...
    add_action('woocommerce_product_options_general_product_data', 'add_advanced_product_options');
    add_action('woocommerce_process_product_meta', 'save_advanced_product_options');

    function add_advanced_product_options() {

        global $post;

        $surpriseme_enabled = get_post_meta($post->ID, '_surpriseme_enabled', true);
        if (empty($surpriseme_enabled)) {
            $surpriseme_enabled = 'no';
        }

        ?>
        <div class="options_group surpriseme">
            <?php
            woocommerce_wp_checkbox(
                array(
                    'id' => '_surpriseme_enabled',
                    'label' => __('surpriseme verwenden', \SurprisemeMain::$text_domain),
                    'cbvalue' => 'yes',
                    'value' => esc_attr($surpriseme_enabled)
                )
            );
            ?>
        </div>
        <?php
    }

    function save_advanced_product_options($post_id) {
        $surpriseme_enabled = ($_POST['_surpriseme_enabled'] == 'yes')? 'yes' : 'no';
        update_post_meta($post_id, '_surpriseme_enabled', $surpriseme_enabled);
    }
}
