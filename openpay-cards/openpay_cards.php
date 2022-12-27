<?php

/**
 * Plugin Name: Openpay Cards Plugin
 * Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
 * Description: Provides a credit card payment method with Openpay for WooCommerce.
 * Version: 2.7.9
 * Author: Openpay
 * Author URI: http://www.openpay.mx
 * Developer: Openpay
 * Text Domain: openpay-cards
 *
 * WC requires at least: 3.0
 * WC tested up to: 6.7.0
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
    if(!class_exists('Utils')) {
        require_once("utils/utils.php");
    }
}

add_action('plugins_loaded', 'openpay_cards_init_your_gateway', 0);
add_action('template_redirect', 'wc_custom_redirect_after_purchase', 0);
add_action('woocommerce_order_refunded', 'openpay_woocommerce_order_refunded', 10, 2);        
add_action('woocommerce_order_status_changed','openpay_woocommerce_order_status_change_custom', 10, 3);
add_action('woocommerce_api_openpay_confirm', 'openpay_woocommerce_confirm', 10, 0);

//Cancel orders
add_action('woocommerce_order_item_add_action_buttons','add_cancel_order', 10, 2);

// Partial capture.
add_action('woocommerce_order_item_add_action_buttons','add_partial_capture_toggle', 10, 1);

// Hook para usuarios no logueados
add_action('wp_ajax_nopriv_get_type_card_openpay', 'get_type_card_openpay');

// Hook para usuarios logueados
add_action('wp_ajax_get_type_card_openpay', 'get_type_card_openpay');

add_action('admin_enqueue_scripts','admin_enqueue_scripts_order' );
add_action('wp_ajax_wc_openpay_admin_order_capture','ajax_capture_handler');

//Cancel orders
add_action('wp_ajax_wc_openpay_admin_order_cancel', 'ajax_cancel_handler');

add_action('woocommerce_before_thankyou', 'confirm_card_saved_notice',11,1);
add_action('woocommerce_before_thankyou', 'order_confirmation_notice',10,1);
add_filter( 'woocommerce_thankyou_order_received_text', 'order_confirmation_text_remove', 10, 2 );

function confirm_card_saved_notice($order_id){
    $cardSavedFlag = get_post_meta($order_id, '_openpay_card_saved_flag', true);
    if ($cardSavedFlag){
        wc_print_notice('Tu tarjeta ha sido registrada exitosamente', 'success', $data = []);
    }
}

function order_confirmation_notice($order_id){
    $order = new WC_Order($order_id);
    if ($order->get_status() === "processing"){
        wc_print_notice('Tu pedido fue procesado con éxito', 'success', $data = []);
    }
}

function order_confirmation_text_remove( $text, $order ){
    return null;
}

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
                if (property_exists($charge, 'authorization') && ($charge->status == 'in_progress' && ($charge->id != $charge->authorization))) {
                    $order->set_status('on-hold');
                    $order->save();
                } else {
                    $order->add_order_note(sprintf("%s Credit Card Payment Failed with message: '%s'", 'Openpay_Cards', 'Status '.$charge->status));
                    $order->set_status('failed');
                    $order->save();

                    if (function_exists('wc_add_notice')) {
                        wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
                    } else {
                        $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
                    }
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

        if ($redirect_url && $order->get_status() != 'completed') {
            delete_post_meta($order->get_id(), '_openpay_3d_secure_url');
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
    $logger->info('ORDER REFUNDED: '.$order_id);             
    $logger->info('REFUND REFUNDED: '.$refund_id); 
    
    $order  = wc_get_order($order_id);
    $refund = wc_get_order($refund_id);
    
    if ($order->get_payment_method() != 'openpay_cards') {
        $logger->info('get_payment_method: '.$order->get_payment_method());             
        return;
    }

    $openpay_cards = new Openpay_Cards();
    if(!strcmp($openpay_cards->settings['sandbox'], 'yes')){
        $customer_id = get_post_meta($order_id, '_openpay_customer_sandbox_id', true);
    }else{
        $customer_id = get_post_meta($order_id, '_openpay_customer_id', true);
    }

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
        if($openpay_cards->settings['country'] == 'CO'){
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

function openpay_woocommerce_order_status_change_custom($order_id, $old_status, $new_status)
{
    // Execute only if there are not a partial capture yet
    if (get_post_meta($order_id, '_captured_total', true) == null) {
        $logger = wc_get_logger();
        $logger->info('openpay_woocommerce_order_status_change_custom');
        $logger->info('$old_status: ' . $old_status);
        $logger->info('$new_status: ' . $new_status);

        $order = wc_get_order($order_id);
        if ($order->get_payment_method() != 'openpay_cards') {
            $logger->info('get_payment_method: ' . $order->get_payment_method());
            return;
        }

        $expected_new_status = array('completed', 'processing');
        $transaction_id = get_post_meta($order_id, '_transaction_id', true);
        $capture = get_post_meta($order_id, '_openpay_capture', true);
        $logger->info('$capture: ' . $capture);

        if ($capture == 'false' && $old_status == 'on-hold' && in_array($new_status, $expected_new_status)) {
            try {
                $openpay_cards = new Openpay_Cards();
                $openpay = $openpay_cards->getOpenpayInstance();
                $settings = $openpay_cards->init_settings();

                if (strcmp($settings['sandbox'], 'yes')) {
                    $customer_id = get_post_meta($order_id, '_openpay_customer_sandbox_id', true);
                } else {
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
                $order->add_order_note('There was an error with Openpay plugin: ' . $e->getMessage());
            }
        }
    }
    // Update the total order with the total captured value
    else{
        $order = wc_get_order($order_id);
        $order->set_total( get_post_meta($order_id, '_captured_total', true));
        $order->save();
    }
}

function get_type_card_openpay(){
    
    global $woocommerce;

    $logger     = wc_get_logger();
    $card_bin   = isset( $_POST['card_bin'] ) ? $_POST['card_bin'] : false;

    if($card_bin) {
        try {

            $openpay_cards  = new Openpay_Cards();
            $country        = $openpay_cards->settings['country'];
            $is_sandbox     = strcmp($openpay_cards->settings['sandbox'], 'yes') == 0;
            $merchant_id    = $is_sandbox === true ? $openpay_cards->settings['test_merchant_id'] : $openpay_cards->settings['live_merchant_id'];
            $auth           = $is_sandbox === true ? $openpay_cards->settings['test_private_key'] : $openpay_cards->settings['live_private_key'];
            $amount         = $woocommerce->cart->total;
            $currency       = get_woocommerce_currency();

            switch ($country) {

                case 'MX':
                    $path       = sprintf('/%s/bines/man/%s', $merchant_id, $card_bin);
                    $cardInfo = Utils::requestOpenpay($path, $country, $is_sandbox,null,null,$auth);
                    
                    wp_send_json(array(
                        'status'    => 'success',
                        'card_type' => $cardInfo->type
                    ));

                break;

                case 'PE':

                    $path       = sprintf('/%s/bines/%s/promotions', $merchant_id, $card_bin);
                    $params     = array('amount' => $amount, 'currency' => $currency);
                    $cardInfo    = Utils::requestOpenpay($path, $country, $is_sandbox);

                    wp_send_json(array(
                        'status'        => 'success',
                        'card_type' => $cardInfo->cardType,
                        'installments'  => $cardInfo->installments,
                        'withInterest' => $cardInfo->withInterest
                    ));

                break;

                default:
                    $path       = sprintf('/cards/validate-bin?bin=%s', $card_bin);
                    $cardInfo = Utils::requestOpenpay($path, $country, $is_sandbox);
                    wp_send_json(array(
                        'status' => 'success',
                        'card_type' => $cardInfo->card_type
                    ));

                break;

            }

        } catch (Exception $e) {
            $logger->error($e->getMessage());
        }
    }
    wp_send_json(array(
        'status' => 'error',
        'card_type' => "credit card not found"
    ));
}

function add_partial_capture_toggle( $order ) {
    $openpay_cards = new Openpay_Cards();
    if ($openpay_cards->is_preauthorized_order($order)){

        $auth_total       = $openpay_cards->get_order_auth_amount( $order );
        $auth_remaining   = $openpay_cards->get_order_auth_remaining( $order );
        $already_captured = $openpay_cards->get_order_captured_total( $order );

        if ( $auth_remaining < 1 ) {
            return;
        }

        include( plugin_dir_path( __FILE__ ) . 'templates/partial-capture.php' );
    }
}

// add for cancelation
function add_cancel_order($order){
    if($order->get_status() != 'refunded' && $order->get_status() != 'cancelled') {
        include(plugin_dir_path(__FILE__).'templates/cancel-order.php');
    }
}

function admin_enqueue_scripts_order( $hook ) {

    global $post, $post_type;
    $order_id = ! empty( $post ) ? $post->ID : false;

    if ( $order_id && 'shop_order' === $post_type && 'post.php' === $hook ) {
        $order = wc_get_order( $order_id );

        // add for cancelation
        $openpay_cards = new Openpay_Cards();
        $auth_total       = $openpay_cards->get_order_auth_amount( $order );

        wp_enqueue_script(
            'woo-openpay-admin-order',
            plugins_url(
                'assets/js/openpay-admin-order.js',
                __FILE__
            ),
            array( 'jquery' )
        );

        wp_localize_script(
            'woo-openpay-admin-order',
            'wc_openpay_admin_order',
            array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'capture_nonce' => wp_create_nonce( 'wc_openpay_admin_order_capture-' . $order_id ),
                'action'        => 'wc_openpay_admin_order_capture',
                'order_id'      => $order_id,
            )
        );

        //Se agrega el script para la cancelación
        wp_localize_script(
            'woo-openpay-admin-order',
            'wc_openpay_cancel_admin_order',
            array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'auth_total' => $auth_total,
                //'capture_nonce' => wp_create_nonce( 'wc_openpay_admin_order_capture-' . $order_id ),
                'action'        => 'wc_openpay_admin_order_cancel',
                'order_id'      => $order_id,
            )
        );

    }
}

function ajax_capture_handler() {
    $order_id = $_POST['order_id'];
    $amount   = isset( $_POST['amount'] ) ? $_POST['amount'] : 0;

    try {
        check_ajax_referer( 'wc_openpay_admin_order_capture-' . $order_id, 'capture_nonce' );
        $order = wc_get_order( $order_id );

        // Capture.
        $openpay_cards = new Openpay_Cards();
        $openpay = $openpay_cards->getOpenpayInstance();
        $settings = $openpay_cards->init_settings();
        $transaction_id = get_post_meta($order_id, '_transaction_id', true);

        if(strcmp($settings['sandbox'], 'yes')){
            $customer_id = get_post_meta($order_id, '_openpay_customer_sandbox_id', true);
        }else{
            $customer_id = get_post_meta($order_id, '_openpay_customer_id', true);
        }

        $customer = $openpay->customers->get($customer_id);
        $charge = $customer->charges->get($transaction_id);
        $charge->capture(array(
            'amount' => floatval($amount)
        ));

        // Actualizar valor de Captura total en los metadatos de la orden
        $order = new WC_Order($order_id);
        update_post_meta($order_id, '_captured_total', $amount);
        $order->set_total($amount);
        $order->payment_complete();
        $order->save();

        $order->add_order_note('Payment was captured in Openpay');

        if ( $charge ) {
            wp_send_json_success();
        } else {
            throw new Exception( 'Capture not successful.' );
        }
    } catch ( Exception $e ) {
        wp_send_json_error( array( 'error' => $e->getMessage() ) );
    }
    wp_die();
}

//Cancel Handler add for cancelation
function ajax_cancel_handler(){
    $logger = wc_get_logger();
    $order_id = $_POST['order_id'];
    $auth_total = $_POST['auth_total'];
    $order = $_POST['order'];

    $logger->info('ORDER: '.$order_id); 
    $logger->info('TOTAL: '. $auth_total);        

    $order = wc_get_order( $order_id );
    
    if ($order->get_payment_method() != 'openpay_cards') {
        $logger->info('get_payment_method: '.$order->get_payment_method());             
        return;
    }

    $openpay_cards = new Openpay_Cards();
    $openpay = $openpay_cards->getOpenpayInstance();

    if(!strcmp($openpay_cards->settings['sandbox'], 'yes')){
        $customer_id = get_post_meta($order_id, '_openpay_customer_sandbox_id', true);
    }else{
        $customer_id = get_post_meta($order_id, '_openpay_customer_id', true);
    }

    $transaction_id = get_post_meta($order_id, '_transaction_id', true);
    $logger->info('_openpay_customer_id: '.$customer_id);      
    if (!strlen($customer_id)) {
        return;
    }

    $reason =  'Cancelation';
    $amount = floatval($auth_total);

    try {
        $logger->info('sandbox'.$openpay_cards->settings['sandbox']);
        $logger->info('pais'.$openpay_cards->settings['country']);

        if($openpay_cards->settings['country'] == 'CO'){
            $order->add_order_note('Openpay plugin does not support cancelations');      
            return;
        }

        //$openpay = $openpay_cards->getOpenpayInstance();
        $customer = $openpay->customers->get($customer_id);
        $charge = $customer->charges->get($transaction_id);
        $charge->refund(array(
            'description' => $reason,
            'amount' => $amount                
        ));
        $order->update_status('cancelled');
        $order->add_order_note('Payment was also cancelled in Openpay');
        $order->add_order_note('Order status change to Cancelled');
        wp_send_json_success();
    } catch (Exception $e) {
        $logger->error($e->getMessage());             
        $order->add_order_note('There was an error cancelling charge in Openpay: '.$e->getMessage());
        wp_send_json_error( array( 'error' => $e->getMessage() ) );
    }        

    wp_die();
}
