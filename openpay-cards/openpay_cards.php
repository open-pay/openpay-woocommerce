<?php

/**
 * Plugin Name: Openpay Cards Plugin
 * Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
 * Description: Provides a credit card payment method with Openpay for WooCommerce. Compatible with WooCommerce 4.4.1 and Wordpress 5.5.
 * Version: 2.3.0
 * Author: Openpay
 * Author URI: http://www.openpay.mx
 * Developer: Openpay
 * Text Domain: openpay-cards
 *
 * WC requires at least: 3.0
 * WC tested up to: 4.4
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Openpay Docs: http://www.openpay.mx/docs/
 */

function openpay_cards_init_your_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        include_once('openpay_cards_gateway.php');
    }
}

add_action('plugins_loaded', 'openpay_cards_init_your_gateway', 0);
add_action('template_redirect', 'wc_custom_redirect_after_purchase', 0);
add_action('woocommerce_order_refunded', 'openpay_woocommerce_order_refunded', 10, 2);        
add_action('woocommerce_order_status_changed','openpay_woocommerce_order_status_change_custom', 10, 3);
add_action('woocommerce_api_openpay_confirm', 'openpay_woocommerce_confirm', 10, 0);         

function openpay_woocommerce_confirm() {   
        global $woocommerce;
        $logger = wc_get_logger();
        
        $id = $_GET['id'];        
        
        $logger->info('openpay_woocommerce_confirm => '.$id);   
        
        try {            
            $openpay_cards = new Openpay_Cards();    
            $openpay = $openpay_cards->getOpenpayInstance();
            $charge = $openpay->charges->get($id);
            $order = new WC_Order($charge->order_id);
            
            $logger->info('openpay_woocommerce_confirm => '.json_encode(array('id' => $charge->id, 'status' => $charge->status)));   

            if ($order && $charge->status != 'completed') {
                $order->add_order_note(sprintf("%s Credit Card Payment Failed with message: '%s'", 'Openpay_Cards', 'Status '+$charge->status));
                $order->set_status('failed');
                $order->save();

                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
                } else {
                    $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
                }
            } else if ($order && $charge->status == 'completed') {
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
                $order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", 'Openpay_Cards', $charge->id));
            }
                        
            wp_redirect($openpay_cards->get_return_url($order));            
        } catch (Exception $e) {
            $logger->error($e->getMessage());            
            status_header( 404 );
            nocache_headers();
            include(get_query_template('404'));
            die();
        }                
    }    

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

/**
 * Realiza el reembolso de la orden en Openpay
 * 
 * @param type $order_id
 * @param type $refund_id
 * 
 * @link https://docs.woocommerce.com/wc-apidocs/source-function-wc_create_refund.html#587
 */
function openpay_woocommerce_order_refunded($order_id, $refund_id) { 
    $logger = wc_get_logger();                
    $logger->info('ORDER: '.$order_id);             
    $logger->info('REFUND: '.$refund_id); 
    
    $order  = wc_get_order($order_id);
    $refund = wc_get_order($refund_id);
    
    if ($order->get_payment_method() != 'openpay_cards') {
        $logger->info('get_payment_method: '.$order->get_payment_method());             
        return;
    }

    $customer_id = get_post_meta($order_id, '_openpay_customer_id', true);
    $transaction_id = get_post_meta($order_id, '_transaction_id', true);
        
    if (!strlen($customer_id)) {
        return;
    }

    $reason = $refund->get_reason() ? $refund->get_reason() : 'Refund ID: '.$refund_id;
    $amount = floatval($refund->get_amount());
    //$amount = $order->get_total_refunded();

    $logger->info('_openpay_customer_id: '.$customer_id);             
    $logger->info('_transaction_id: '.$transaction_id);             

    try {
        $openpay_cards = new Openpay_Cards();
        
        $settings = $openpay_cards->init_settings();
        if($settings['country'] != 'MX'){
            $order->add_order_note('Openpay plugin does not support refunds');             
            return;
        }

        $openpay = $openpay_cards->getOpenpayInstance();
        $customer = $openpay->customers->get($customer_id);
        $charge = $customer->charges->get($transaction_id);
        $charge->refund(array(
            'description' => $reason,
            'amount' => $amount                
        ));
        $order->add_order_note('Payment was also refunded in Openpay');
    } catch (Exception $e) {
        $logger->error($e->getMessage());             
        $order->add_order_note('There was an error refunding charge in Openpay: '.$e->getMessage());
    }        

    return;
} 

function openpay_woocommerce_order_status_change_custom($order_id, $old_status, $new_status) {
    $logger = wc_get_logger();                
    $logger->info('openpay_woocommerce_order_status_change_custom');             
    $logger->info('$old_status: '.$old_status);             
    $logger->info('$new_status: '.$new_status);   
    
    $order = wc_get_order($order_id);       
    if ($order->get_payment_method() != 'openpay_cards') {
        $logger->info('get_payment_method: '.$order->get_payment_method());             
        return;
    }
    
    $expected_new_status = array('completed', 'processing');
    $transaction_id = get_post_meta($order_id, '_transaction_id', true);
    $capture = get_post_meta($order_id, '_openpay_capture', true);    
    $logger->info('$capture: '.$capture);             
    
    if ($capture == 'false' && $old_status == 'on-hold' && in_array($new_status, $expected_new_status)) {
        try {
            $openpay_cards = new Openpay_Cards();    
            $openpay = $openpay_cards->getOpenpayInstance();
            $settings = $openpay_cards->init_settings();

            if(strcmp($settings['sandbox'], 'yes')){
                $customer_id = get_post_meta($order_id, '_openpay_customer_sandbox_id', true); 
            }else{
                $customer_id = get_post_meta($order_id, '_openpay_customer_id', true);    
            }

            $customer = $openpay->customers->get($customer_id);
            $charge = $customer->charges->get($transaction_id);
            $charge->capture(array(
                'amount' => floatval($order->get_total())
            ));
            $order->add_order_note('Payment was captured in Openpay');
        } catch (Exception $e) {
            $logger->error($e->getMessage());             
            $order->add_order_note('There was an error with Openpay plugin: '.$e->getMessage());
        }        
    }        

    return;
}