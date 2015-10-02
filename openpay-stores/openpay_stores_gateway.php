<?php

if (!class_exists('Openpay')) {
    require_once("lib/openpay/Openpay.php");
}
/*
  Title:	Openpay Payment extension for WooCommerce
  Author:	Federico Balderas
  URL:		http://foograde.com
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

class Openpay_Stores extends WC_Payment_Gateway
{

    protected $GATEWAY_NAME = "Openpay Stores";
    protected $is_sandbox = true;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;

    public function __construct()
    {
        $this->id = 'openpay_stores';
        $this->method_title = __('Openpay Stores', 'openpay_stores');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = 'Pago en efectivo en tiendas de conveniencia';
        $this->description = '';
        //$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/openpay.png';
        $this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->test_merchant_id = $this->settings['test_merchant_id'];
        $this->test_private_key = $this->settings['test_private_key'];
        $this->test_publishable_key = $this->settings['test_publishable_key'];
        $this->live_merchant_id = $this->settings['live_merchant_id'];
        $this->live_private_key = $this->settings['live_private_key'];
        $this->live_publishable_key = $this->settings['live_publishable_key'];

        $this->merchant_id = $this->is_sandbox ? $this->test_merchant_id : $this->live_merchant_id;
        $this->publishable_key = $this->is_sandbox ? $this->test_publishable_key : $this->live_publishable_key;
        $this->private_key = $this->is_sandbox ? $this->test_private_key : $this->live_private_key;
        $this->pdf_url_base = $this->is_sandbox ? 'https://sandbox-dashboard.openpay.mx/paynet-pdf' : 'https://dashboard.openpay.mx/paynet-pdf';
        // tell WooCommerce to save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_notices', array(&$this, 'perform_ssl_check'));
    }

    public function perform_ssl_check()
    {
        if (!$this->is_sandbox && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') :
            echo '<div class="error"><p>' . sprintf(__('%s sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->GATEWAY_NAME, admin_url('admin.php?page=settings')) . '</p></div>';
        endif;
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'type' => 'checkbox',
                'title' => __('Enable/Disable', 'woothemes'),
                'label' => __('Enable Openpay Stores', 'woothemes'),
                'default' => 'yes'
            ),
            'sandbox' => array(
                'type' => 'checkbox',
                'title' => __('Sandbox mode', 'woothemes'),
                'label' => __('Enable sandbox', 'woothemes'),
                'description' => __('Place the payment gateway in test mode using Sandbox API keys.', 'woothemes'),
                'default' => 'no'
            ),
            'test_merchant_id' => array(
                'type' => 'text',
                'title' => __('Sandbox merchant ID', 'woothemes'),
                'description' => __('Get your Sandbox API keys from your Openpay account.', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'test_private_key' => array(
                'type' => 'text',
                'title' => __('Sandbox secret key', 'woothemes'),
                'description' => __('Get your Sandbox API keys from your Openpay account ("sk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'test_publishable_key' => array(
                'type' => 'text',
                'title' => __('Sandbox public key', 'woothemes'),
                'description' => __('Get your Sandbox API keys from your Openpay account ("pk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'live_merchant_id' => array(
                'type' => 'text',
                'title' => __('Production merchant ID', 'woothemes'),
                'description' => __('Get your Production API keys from your Openpay account.', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'live_private_key' => array(
                'type' => 'text',
                'title' => __('Production secret key', 'woothemes'),
                'description' => __('Get your Production API keys from your Openpay account ("sk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'live_publishable_key' => array(
                'type' => 'text',
                'title' => __('Production public key', 'woothemes'),
                'description' => __('Get your Production API keys from your Openpay account ("pk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
        );
    }

    public function admin_options()
    {
        include_once('templates/admin.php');
    }

    public function payment_fields()
    {
        $this->images_dir = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/';
        include_once('templates/payment.php');
    }

    protected function processOpenpayCharge()
    {
        // Get the credit card details submitted by the form
        $charge_request = array(
            "method" => "store",
            "amount" => (float) $this->order->get_total(),
            "currency" => strtolower(get_woocommerce_currency()),
            "description" => sprintf("Charge for %s", $this->order->billing_email),
            "order_id" => "Woocommerce order " . $this->order->id
        );

        $openpay_customer = $this->getOpenpayCustomer();

        $result_json = $this->createOpenpayCharge($openpay_customer, $charge_request);

        if ($result_json != false) {
            
            $this->transaction_id = $result_json->id;
            WC()->session->set( 'pdf_url' , $this->pdf_url_base.'/'.$this->merchant_id.'/'.$result_json->payment_method->reference);
            //Save data for the ORDER
            update_post_meta($this->order->id, '_openpay_customer_id', $openpay_customer->id);
            update_post_meta($this->order->id, '_transaction_id', $result_json->id);
            update_post_meta($this->order->id, '_key', $this->private_key);

            return true;
        } else {
            return false;
        }
    }

    public function process_payment($order_id)
    {
        global $woocommerce;
               
        $this->order = new WC_Order($order_id);
        if ($this->processOpenpayCharge()) {            
            $this->completeOrder();
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
        } else {
            $this->markAsFailedPayment();

            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), $notice_type = 'error');
            } else {
                $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
            }
        }
    }

    protected function markAsFailedPayment()
    {
        $this->order->add_order_note(
                sprintf(
                        "%s Credit Card Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage
                )
        );
    }

    protected function completeOrder()
    {
        global $woocommerce;

        //$this->order->payment_complete();
        $this->order->update_status('on-hold', 'En espera de pago');
        $this->order->reduce_order_stock();
        $woocommerce->cart->empty_cart();

        $this->order->add_order_note(
            sprintf(
                    "%s payment completed with Transaction Id of '%s'", $this->GATEWAY_NAME, $this->transaction_id
            )
        );
    }

    public function createOpenpayCharge($customer, $charge_request)
    {
        Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);
        try {
            $charge = $customer->charges->create($charge_request);
            return $charge;
        } catch (Exception $e) {
            $this->error($e);
            return false;
        }
    }

    public function getOpenpayCustomer()
    {
        $customer_id = null;
        if (is_user_logged_in()) {
            $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true);
        }
        
        if (isNullOrEmptyString($customer_id)) {
            return $this->createOpenpayCustomer();
        } else {
            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
            Openpay::setProductionMode($this->is_sandbox ? false : true);
            try {
                return $openpay->customers->get($customer_id);
            } catch (Exception $e) {
                $this->error($e);
                return false;
            }
        }
    }

    public function createOpenpayCustomer()
    {

        $customerData = array(
            //'external_id' => $this->order->user_id == null ? null : $this->order->user_id,
            'name' => $this->order->billing_first_name,
            'last_name' => $this->order->billing_last_name,
            'email' => $this->order->billing_email,
            'requires_account' => false,
            'phone_number' => $this->order->billing_phone,
            'address' => array(
                'line1' => $this->order->billing_address_1,
                'line2' => $this->order->billing_address_2,
                'line3' => '',
                'state' => $this->order->billing_state,
                'city' => $this->order->billing_city,
                'postal_code' => $this->order->billing_postcode,
                'country_code' => $this->order->billing_country
            )
        );

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        try {
            $customer = $openpay->customers->add($customerData);

            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), '_openpay_customer_id', $customer->id);
            }

            return $customer;
        } catch (Exception $e) {
            $this->error($e);
            return false;
        }
    }

    public function error($e)
    {
        global $woocommerce;

        /* 6001 el webhook ya existe */
        switch ($e->getErrorCode()) {
            /* ERRORES GENERALES */
            case '1000':
            case '1004':
            case '1005':
                $msg = 'Servicio no disponible.';
                break;
            /* ERRORES TARJETA */
            case '3001':
            case '3004':
            case '3005':
            case '3007':
                $msg = 'La tarjeta fue rechazada.';
                break;
            case '3002':
                $msg = 'La tarjeta ha expirado.';
                break;
            case '3003':
                $msg = 'La tarjeta no tiene fondos suficientes.';
                break;
            case '3006':
                $msg = 'La operación no esta permitida para este cliente o esta transacción.';
                break;
            case '3008':
                $msg = 'La tarjeta no es soportada en transacciones en línea.';
                break;
            case '3009':
                $msg = 'La tarjeta fue reportada como perdida.';
                break;
            case '3010':
                $msg = 'El banco ha restringido la tarjeta.';
                break;
            case '3011':
                $msg = 'El banco ha solicitado que la tarjeta sea retenida. Contacte al banco.';
                break;
            case '3012':
                $msg = 'Se requiere solicitar al banco autorización para realizar este pago.';
                break;
            default: /* Demás errores 400 */
                $msg = 'La petición no pudo ser procesada.';
                break;
        }
        $error = 'ERROR ' . $e->getErrorCode() . '. ' . $msg;

        if (function_exists('wc_add_notice')) {
            wc_add_notice($error, $notice_type = 'error');
        } else {
            $woocommerce->add_error(__('Payment error:', 'woothemes') . $error);
        }
    }

}

//function openpay_stores_order_status_completed($order_id)
//{
//    global $woocommerce;
//    $authcap = get_post_meta($order_id, 'auth_capture', true);
//    if ($authcap) {
//        Openpay::setApiKey(get_post_meta($order_id, 'key', true));
//        try {
//            $ch = Openpay_Charge::retrieve(get_post_meta($order_id, 'transaction_id', true));
//            $ch->capture();
//        } catch (Openpay_Error $e) {
//            // There was an error
//            $body = $e->getJsonBody();
//            $err = $body['error'];
//            error_log('Openpay Error:' . $err['message'] . "\n");
//
//            if (function_exists('wc_add_notice')) {
//                wc_add_notice($err['message'], $notice_type = 'error');
//            } else {
//                $woocommerce->add_error(__('Payment error:', 'woothemes') . $err['message']);
//            }
//            return null;
//        }
//        return true;
//    }
//}

function openpay_stores_add_creditcard_gateway($methods)
{
    array_push($methods, 'openpay_stores');
    return $methods;
}

function openpay_stores_template($template, $template_name, $template_path)
{
    global $woocommerce;
    
    //$mid = $this->merchant_id;
    
    $_template = $template;
    if (!$template_path){
        $template_path = $woocommerce->template_url;
    }

    $plugin_path = untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/woocommerce/';

    // Look within passed path within the theme - this is priority
    $template = locate_template(
            array(
                $template_path . $template_name,
                $template_name
            )
    );

    if (!$template && file_exists($plugin_path . $template_name))
        $template = $plugin_path . $template_name;

    if (!$template)
        $template = $_template;

    return $template;
}

//function attach_terms_conditions_pdf_to_email ( $attachments, $status , $order ) {
//
//    $allowed_statuses = array('on-hold');
//
//    if( isset( $status ) && in_array ( $status, $allowed_statuses ) ) {
//         //$your_pdf_path = get_template_directory() . '/media/test1.pdf'; 
//         $attachments[] = $pdf_dir = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/file.pdf';; 
//    } 
//    
//    return $attachments; 
//}

add_filter('woocommerce_payment_gateways', 'openpay_stores_add_creditcard_gateway');
add_filter( 'woocommerce_locate_template', 'openpay_stores_template', 1, 3 );
//add_filter( 'woocommerce_email_attachments', 'attach_terms_conditions_pdf_to_email', 1, 3); 
//add_action('woocommerce_order_status_processing_to_completed', 'openpay_stores_order_status_completed');