<?php
/*
  Plugin Name: Openpay Cards Plugin
  Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
  Description: Provides a credit card payment method with Openpay for WooCommerce. Compatible with WooCommerce 3.4.3 and Wordpress 4.9.7.
  Version: 1.4.0
  Author: Openpay
  Author URI: http://www.openpay.mx

  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html

  Openpay Docs: http://www.openpay.mx/docs/
 */


function openpay_cards_init_your_gateway() 
{
    if (class_exists('WC_Payment_Gateway'))
    {
        include_once('openpay_cards_gateway.php');
    }
}

add_action('plugins_loaded', 'openpay_cards_init_your_gateway', 0);
add_action('template_redirect', 'wc_custom_redirect_after_purchase', 0);

function wc_custom_redirect_after_purchase() {
    global $wp;
    $logger = wc_get_logger();
        
    if (is_checkout() && !empty($wp->query_vars['order-received'])) {
        $order = new WC_Order($wp->query_vars['order-received']);
        $redirect_url = get_post_meta($order->get_id(), '_openpay_3d_secure_url', true);        
        
        if ($redirect_url && $order->get_status() == 'pending') {
            $logger->debug($redirect_url);
            wp_redirect($redirect_url);
            exit();        
        }
    }
}