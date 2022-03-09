<?php
/**
 * Plugin Name: Openpay Checkout Lending
 * Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
 * Description: Provides a lending payment method with Openpay for WooCommerce.
 * Version: 1.1.1
 * Author: Openpay
 * Author URI: http://www.openpay.mx
 * Developer: Openpay
 * Text Domain: openpay-checkout-lending
 *
 * WC requires at least: 3.0
 * WC tested up to: 5.2.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Openpay Docs: http://www.openpay.mx/docs/
 */


 /**
  * If WC is installed include openpay_checkout_lending_gateway.php file.
  * @see class Openpay_Checkout_Lending => openpay_checkout_lending_gateway.php
  * @see class WC_Payment_Gateway => abstract-wc-payment-gateway.php
  */
function openpay_checkout_lending_init_your_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        include_once('openpay_checkout_lending_gateway.php');
    }
}

/**
 * Description.
 *  Payment gateways should be created as additional plugins that hook into WooCommerce. Inside the plugin, you need to create a class after plugins are loaded.
 */
add_action('plugins_loaded', 'openpay_checkout_lending_init_your_gateway', 0);


/** 
 * Call the woocommerce_payment_gateways filter which returns an array containing all the payment methods registered in woocommerce. 
 *  @return Array Contains the list of all payment methods registered on WC, you can see them in the checkout tab in seetings.
 */
add_filter('woocommerce_payment_gateways', 'openpay_checkout_lending_add_gateway');


/**
 * Adds the openpay_checkout_lending payment method to the list of WC payment methods
 * 
 *  @see The name of new payment method to add in $methods array should match with the class name which extends from WC_Payment_Gateway.
 *  @param Array $methods Contains the list of all payment methods registered on WC, you can see them in the checkout tab in seetings.
 *  @return Array After adds the openpay_checkout_lending method returns the array.
 */
function openpay_checkout_lending_add_gateway($methods) {
    array_push($methods, 'openpay_checkout_lending');
    return $methods;
}

/**
 * Adds a behavior to the woocomerce API.
 */
add_action('woocommerce_api_checkout_lending_failed', 'openpay_checkout_lending_failed', 10, 0);

/**
 * Changes the order status to failed if the transaction fails or is rejected.
 * @see processOpenpayCharge() line 312, 313 
 */
function openpay_checkout_lending_failed(){
    global $woocommerce;
    $id = $_GET['order_id'];        

        try {  
            $order = new WC_Order($id);
            $transaction_id = $order->get_transaction_id();
            $openpay_checkout_lending = new Openpay_Checkout_Lending();    
            $openpay = $openpay_checkout_lending->getOpenpayInstance();
            $charge = $openpay->charges->get($transaction_id); 

            if ($order && $charge->status != 'completed') {
                $order->add_order_note(sprintf("%s Credit Card Payment Failed with message: '%s'", 'Openpay_Cards', 'Status '+$charge->status));
                $order->set_status('failed');
                $order->save();

                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
                } else {
                    $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
                }
            }
                        
            wp_redirect($openpay_checkout_lending->get_return_url($order));            
        } catch (Exception $e) {           
            status_header( 404 );
            nocache_headers();
            include(get_query_template('404'));
            die();
        }                
}

