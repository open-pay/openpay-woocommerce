<?php

if (!class_exists('Openpay')) {
    require_once("lib/openpay/Openpay.php");
}
/*
  Title:	Openpay Payment extension for WooCommerce
  Author:	Openpay
  URL:		http://www.openpay.mx
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

class Openpay_Spei extends WC_Payment_Gateway
{

    protected $GATEWAY_NAME = "Openpay SPEI";
    protected $is_sandbox = true;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('MXN');
    protected $test_merchant_id;
    protected $test_private_key;
    protected $live_merchant_id;
    protected $live_private_key;
    protected $deadline;
    protected $merchant_id;
    protected $private_key;
    protected $pdf_url_base;
    protected $images_dir;


    public function __construct() {
        $this->id = 'openpay_spei';
        $this->method_title = __('Openpay SPEI', 'openpay_spei');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = 'Pago con transferencia interbancaria vía SPEI';
        $this->description = '';
        $this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->test_merchant_id = $this->settings['test_merchant_id'];
        $this->test_private_key = $this->settings['test_private_key'];
        $this->live_merchant_id = $this->settings['live_merchant_id'];
        $this->live_private_key = $this->settings['live_private_key'];
        $this->deadline = $this->settings['deadline'];

        $this->merchant_id = $this->is_sandbox ? $this->test_merchant_id : $this->live_merchant_id;
        $this->private_key = $this->is_sandbox ? $this->test_private_key : $this->live_private_key;
        $this->pdf_url_base = $this->is_sandbox ? 'https://sandbox-dashboard.openpay.mx/spei-pdf' : 'https://dashboard.openpay.mx/spei-pdf';
        // tell WooCommerce to save options
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'webhook_handler'));

        if (!$this->validateCurrency()) {
            $this->enabled = false;
        }
    }

    public function process_admin_options() {
        $post_data = $this->get_post_data();
        $mode = 'live';    
        
        if($post_data['woocommerce_'.$this->id.'_sandbox'] == '1'){
            $mode = 'test';            
        }
        
        $this->merchant_id = $post_data['woocommerce_'.$this->id.'_'.$mode.'_merchant_id'];
        $this->private_key = $post_data['woocommerce_'.$this->id.'_'.$mode.'_private_key'];
        
        $env = ($mode == 'live') ? 'Producton' : 'Sandbox';
        
        if($this->merchant_id == '' || $this->private_key == ''){
            $settings = new WC_Admin_Settings();
            $settings->add_error('You need to enter "'.$env.'" credentials if you want to use this plugin in this mode.');
        } else {
            $this->createWebhook();
        } 
        
        return parent::process_admin_options();        
    }    

    public function webhook_handler() {
        header('HTTP/1.1 200 OK');        
        $obj = file_get_contents('php://input');
        $json = json_decode($obj);

        if($json->transaction->method == 'bank_account'){
            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
            Openpay::setProductionMode($this->is_sandbox ? false : true);

            if(isset($json->transaction->customer_id)){
                $customer = $openpay->customers->get($json->transaction->customer_id);
                $charge = $customer->charges->get($json->transaction->id);
            }else{
                $charge = $openpay->charges->get($json->transaction->id);
            }

            $order_id = $json->transaction->order_id;
            $order = new WC_Order($order_id);

            if ($json->type == 'charge.succeeded' && $charge->status == 'completed') {            
                $payment_date = date("Y-m-d", strtotime($json->event_date));
                $order->update_meta_data('openpay_payment_date', $payment_date);
                $order->payment_complete();
                $order->add_order_note(sprintf("Payment completed.")); 
            }else if($json->type == 'transaction.expired' && $charge->status == 'cancelled'){
                $order->update_status('cancelled', 'Payment is due.');
            }
        }
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'type' => 'checkbox',
                'title' => __('Habilitar módulo', 'woothemes'),
                'label' => __('Habilitar', 'woothemes'),
                'default' => 'yes'
            ),
            'sandbox' => array(
                'type' => 'checkbox',
                'title' => __('Modo de pruebas', 'woothemes'),
                'label' => __('Habilitar', 'woothemes'),                
                'default' => 'no'
            ),
            'test_merchant_id' => array(
                'type' => 'text',
                'title' => __('ID de comercio de pruebas', 'woothemes'),
                'description' => __('Obten tus llaves de prueba de tu cuenta de Openpay.', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'test_private_key' => array(
                'type' => 'text',
                'title' => __('Llave secreta de pruebas', 'woothemes'),
                'description' => __('Obten tus llaves de prueba de tu cuenta de Openpay ("sk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'live_merchant_id' => array(
                'type' => 'text',
                'title' => __('ID de comercio de producción', 'woothemes'),
                'description' => __('Obten tus llaves de producción de tu cuenta de Openpay.', 'woothemes'),
                'default' => __('', 'woothemes')
            ),
            'live_private_key' => array(
                'type' => 'text',
                'title' => __('Llave secreta de producción', 'woothemes'),
                'description' => __('Obten tus llaves de producción de tu cuenta de Openpay ("sk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),      
            'deadline' => array(
                'type' => 'number',
                'required' => true,
                'title' => __('Payment deadline', 'woothemes'),
                'description' => __('Define how many hours have the customer to make the payment.', 'woothemes'),
                'default' => '48'
            )
        );
    }

    public function admin_options() {
        include_once('templates/admin.php');
    }

    public function payment_fields() {
        $this->images_dir = plugin_dir_url( __FILE__ ).'/assets/images/';
        include_once('templates/payment.php');
    }

    protected function processOpenpayCharge() {
                
        date_default_timezone_set('America/Mexico_City');
        $due_date = date('Y-m-d\TH:i:s', strtotime('+ '.$this->deadline.' hours'));
        $amount = number_format((float) $this->order->get_total(), 2, '.', '');
        
        $charge_request = array(
            "method" => "bank_account",
            "amount" => $amount,
            "description" => sprintf("Cargo para %s", $this->order->get_billing_email()),
            "order_id" => $this->order->get_id(),
            'due_date' => $due_date,
            "origin_channel" => "PLUGIN_WOOCOMMERCE"
        );

        $openpay_customer = $this->getOpenpayCustomer();

        $result_json = $this->createOpenpayCharge($openpay_customer, $charge_request);

        if ($result_json != false) {
            $this->transaction_id = $result_json->id;
            $pdf_url = $this->pdf_url_base.'/'.$this->merchant_id.'/'.$result_json->id;
            //WC()->session->set('pdf_url', $pdf_url);
            //Save data for the ORDER
            if($this->is_sandbox){
                $this->order->update_meta_data('_openpay_customer_sandbox_id', $openpay_customer->id);
            }else{
                $this->order->update_meta_data('_openpay_customer_id', $openpay_customer->id);
            }
            $this->order->update_meta_data('_transaction_id', $result_json->id);
            $this->order->update_meta_data('_pdf_url', $pdf_url);
            return true;
        } else {
            return false;
        }
    }

    public function process_payment($order_id) {
        global $woocommerce;

        $this->order = new WC_Order($order_id);
        if ($this->processOpenpayCharge()) {
            $this->order->update_status('on-hold', 'En espera de pago');
            $this->order->reduce_order_stock();
            $woocommerce->cart->empty_cart();
            $this->order->add_order_note(sprintf("Payment will be processed by %s with Transaction Id: '%s'", $this->GATEWAY_NAME, $this->transaction_id));
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
        } else {
            $this->order->add_order_note(sprintf("%s SPEI Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));

            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), $notice_type = 'error');
            } else {
                $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
            }
        }
    }

    public function createOpenpayCharge($customer, $charge_request) {
        Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-WOOCMX/v2";
        Openpay::setUserAgent($userAgent);

        try {
            $charge = $customer->charges->create($charge_request);
            return $charge;
        } catch (Exception $e) {
            $this->error($e);
            return false;
        }
    }

    public function getOpenpayCustomer() {
        $customer_id = null;
        if (is_user_logged_in()) {
            if($this->is_sandbox){
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', true);
            }else{
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true);
            }
        }

        if ($this->isNullOrEmptyString($customer_id)) {
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

    public function createOpenpayCustomer() {
        $customerData = array(
            'name' => $this->order->get_billing_first_name(),
            'last_name' => $this->order->get_billing_last_name(),
            'email' => $this->order->get_billing_email(),
            'requires_account' => false,
            'phone_number' => $this->order->get_billing_phone()
        );

        if ($this->order->get_billing_address_1() && $this->order->get_billing_state() && $this->order->get_billing_city() && $this->order->get_billing_postcode() && $this->order->get_billing_country()) {
            $customerData['address'] = array(
                'line1' => substr($this->order->get_billing_address_1(), 0, 200),
                'line2' => substr($this->order->get_billing_address_2(), 0, 50),
                'state' => $this->order->get_billing_state(),
                'city' => $this->order->get_billing_city(),
                'postal_code' => $this->order->get_billing_postcode(),
                'country_code' => $this->order->get_billing_country()
            );
        }

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-WOOCMX/v2";
        Openpay::setUserAgent($userAgent);

        try {
            $customer = $openpay->customers->add($customerData);

            if (is_user_logged_in()) {
                if($this->is_sandbox){
                    update_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', $customer->id);
                }else{
                    update_user_meta(get_current_user_id(), '_openpay_customer_id', $customer->id);
                }
            }

            return $customer;
        } catch (Exception $e) {
            $this->error($e);
            return false;
        }
    }

    public function createWebhook($force_host_ssl = false) {

        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';
        $url = site_url('/', $protocol).'wc-api/Openpay_Spei';          

        $webhook_data = array(
            'url' => $url,
            'force_host_ssl' => $force_host_ssl,
            'event_types' => array(
                'verification',
                'charge.succeeded',
                'charge.created',
                'charge.cancelled',
                'charge.failed',
                'payout.created',
                'payout.succeeded',
                'payout.failed',
                'spei.received',
                'chargeback.created',
                'chargeback.rejected',
                'chargeback.accepted',
                'transaction.expired'
            )
        );
                       

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-WOOCMX/v2";
        Openpay::setUserAgent($userAgent);
        
        try {
            $webhook = $openpay->webhooks->add($webhook_data);
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), '_openpay_webhook_id', $webhook->id);
            }
            return $webhook;
        } catch (Exception $e) {
            $force_host_ssl = ($force_host_ssl == false) ? true : false; // Si viene con parámtro FALSE, solicito que se force el host SSL
            $this->errorWebhook($e, $force_host_ssl, $url);
            return false;
        }
    }

    public function error($e) {

        switch ($e->getErrorCode()) {
            /* ERRORES GENERALES */
            case '1000':
            case '1004':
            case '1005':
                $msg = 'Servicio no disponible.';
                break;            
            default: /* Demás errores 400 */
                $msg = 'La petición no pudo ser procesada.';
                break;
        }
        
        $error = $e->getErrorCode().'. '.$msg;

        if (function_exists('wc_add_notice')) {
            wc_add_notice($error, 'error');
        } else {
            $settings = new WC_Admin_Settings();
            $settings->add_error($error);
        }
    }
    
    
    public function errorWebhook($e, $force_host_ssl, $url) {

        switch ($e->getErrorCode()) {     
            case '1003':
                $msg = 'Puerto inválido, puertos válidos: 443, 8443 y 10443';
                break;      
            case '6001':
                $msg = 'El webhook ya existe, omite este mensaje.';
                return;
            case '6002':
            case '6003';    
                $msg = 'No es posible conectarse con el servicio de webhook, verifica la URL: '.$url;
                if($force_host_ssl == true){
                    $this->createWebhook(true);
                }                                
                break;
            default: /* Demás errores 400 */
                $msg = 'La petición no pudo ser procesada.';
                break;
        }
        
        $error = $e->getErrorCode().'. '.$msg;
        
        /**
         * Para solo mostrar un mensaje de error en backoffice y no 2, 
         * esto debido a que se vuelve a realizar la petición "createWebhook" con el parámetro "force_host_ssl"         
         **/         
        if(!$force_host_ssl){
            return;
        }

        if (function_exists('wc_add_notice')) {
            wc_add_notice($error, 'error');
        } else {
            $settings = new WC_Admin_Settings();
            $settings->add_error($error);
        }
    }

    /**
     * Checks if woocommerce has enabled available currencies for plugin
     *
     * @access public
     * @return bool
     */
    public function validateCurrency() {
        return in_array(get_woocommerce_currency(), $this->currencies);
    }

    public function isNullOrEmptyString($string) {
        return (!isset($string) || trim($string) === '');
    }

}

function openpay_spei_add_creditcard_gateway($methods) {
    array_push($methods, 'openpay_spei');
    return $methods;
}

function openpay_spei_template($template, $template_name, $template_path) {
    global $woocommerce;

    //$mid = $this->merchant_id;

    $_template = $template;
    if (!$template_path) {
        $template_path = $woocommerce->template_url;
    }

    $plugin_path = untrailingslashit(plugin_dir_path(__FILE__)).'/templates/woocommerce/';

    // Look within passed path within the theme - this is priority
    $template = locate_template(
            array(
                $template_path.$template_name,
                $template_name
            )
    );

    if (!$template && file_exists($plugin_path.$template_name))
        $template = $plugin_path.$template_name;

    if (!$template)
        $template = $_template;

    return $template;
}

add_filter('woocommerce_payment_gateways', 'openpay_spei_add_creditcard_gateway');
add_filter('woocommerce_locate_template', 'openpay_spei_template', 1, 3);
