<?php

/**
 * Plugin Name: Openpay PSE
 * Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
 * Description: Provides a PSE payment method with Openpay for WooCommerce. Compatible with WooCommerce 4.5.2 and Wordpress 5.5.
 * Version: 1.7.1
 * Author: Openpay
 * Author URI: http://www.openpay.mx
 * Developer: Openpay
 * Text Domain: openpay-pse
 *
 * WC requires at least: 3.0
 * WC tested up to: 8.5.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Openpay Docs: http://www.openpay.co/docs/
 */

function openpay_pse_init_your_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        include_once('openpay_pse_gateway.php');
    }
}

add_action('plugins_loaded', 'openpay_pse_init_your_gateway', 0);
add_action('template_redirect', 'openpay_pse_redirect_after_purchase', 0);
add_action('woocommerce_api_pse_confirm', 'openpay_pse_confirm', 10, 0);
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

function openpay_pse_confirm() {
        global $woocommerce;
        $logger = wc_get_logger();
        
        $id = preg_replace('/[^a-z0-9]/', '', $_GET['id']);        
        
        $logger->info('openpay_woocommerce_confirm => '.$id);   
        
        try {
            $openpay_pse = new Openpay_Pse();
            $openpay = $openpay_pse->getOpenpayInstance();
            $charge = $openpay->charges->get($id);
            $order = new WC_Order($charge->order_id);
            
            $logger->info('openpay_woocommerce_confirm => '.json_encode(array('id' => $charge->id, 'status' => $charge->status)));

            if ($order && $charge->status == 'completed') {
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
                $order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", 'Openpay_Pse', $charge->id));

                $order->update_meta_data('_transaction_id', $charge->id);

            }else if($order && ($charge->status == 'cancelled' || $charge->status == 'failed')) {
                $order->add_order_note(sprintf("%s PSE Payment Failed with message: '%s'", 'Openpay_Pse', 'Status '+$charge->status));
                $order->set_status('failed');
                $order->save();

                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
                } else {
                    $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
                }
            }
                        
            wp_redirect($openpay_pse->get_return_url($order));            
        } catch (Exception $e) {
            $logger->error($e->getMessage());            
            status_header( 404 );
            nocache_headers();
            include(get_query_template('404'));
            die();
        }                
    }    

function openpay_pse_redirect_after_purchase() {
    global $wp;
    $logger = wc_get_logger();
    $logger->info('openpay_pse_redirect_after_purchase');

    if (is_checkout() && !empty($wp->query_vars['order-received'])) {
        $order = new WC_Order($wp->query_vars['order-received']);
        $redirect_url= $order->get_meta('_openpay_pse_redirect_url');

        if ($redirect_url && $order->get_status() == 'on-hold') {
            $logger->debug($redirect_url);
            wp_redirect($redirect_url);
            exit();
        }
    }
}