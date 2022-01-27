<?php
if (!class_exists('Openpay')) {
    require_once("lib/openpay/Openpay.php");
}
/*
  Title:    Openpay Payment extension for WooCommerce
  Author:   Openpay
  URL:      http://www.openpay.mx
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

 class Openpay_Checkout_Lending extends WC_Payment_Gateway{

    protected $GATEWAY_NAME = "Openpay Checkout Lending";
    protected $is_sandbox = true;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('MXN');  
    protected $logger = null;
    protected $country = 'MX';
    protected $is_privacy_terms_accepted = "false";

    public function __construct(){
        $this->id = 'openpay_checkout_lending'; 
        $this->method_title = __('Openpay Checkout Lending', 'openpay_checkout_lending'); 
        $this->has_fields = true;
        $this->init_form_fields();
        $this->init_settings();
        $this->title = 'Compra ahora, paga después';
        $this->description = '';
        $this->logger = wc_get_logger();
        $this->context = array( 'source' => 'Openpay_Checkout_Lending-log' );
        $this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->test_merchant_id = $this->settings['test_merchant_id'];
        $this->test_private_key = $this->settings['test_private_key'];
        $this->live_merchant_id = $this->settings['live_merchant_id'];
        $this->live_private_key = $this->settings['live_private_key']; 
        $this->merchant_id = $this->is_sandbox ? $this->test_merchant_id : $this->live_merchant_id;      
        $this->private_key = $this->is_sandbox ? $this->test_private_key : $this->live_private_key;

        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action('admin_enqueue_scripts', array($this, 'openpay_checkout_lending_admin_enqueue'), 10, 2);
        add_action( 'template_redirect', array($this,'checkout_lending_redirect_after_purchase'),0);
        add_action('woocommerce_api_checkout_lending', array($this, 'webhook_handler')); 
        add_action( 'woocommerce_checkout_update_order_meta', array($this,'save_terms_conditions_acceptance' ));

        if (!$this->validateCurrency()) {
            $this->enabled = false;
        }
    }

    function save_terms_conditions_acceptance( $order_id ) {
        if ( $_POST['terms'] ) update_post_meta( $order_id, 'terms', esc_attr( $_POST['terms'] ) );
        }

        public function payment_fields() {
            $this->images_dir = plugin_dir_url( __FILE__ ).'/assets/images/';          
            include_once('templates/payment.php');
        }

        public function admin_options() {
            include_once('templates/admin.php');
        }

    // Define and load settings fields
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
            )
        );
    }

    public function openpay_checkout_lending_admin_enqueue($hook) {
        wp_enqueue_script('openpay_checkout_lending_admin_form', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '', true);
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

        if($json->transaction->method == 'lending'){

            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, $this->country);
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
                $payment_date = date("Y-m-d", $json->event_date);
                update_post_meta($order->get_id(), 'openpay_payment_date', $payment_date);
                $order->payment_complete();
                $order->add_order_note(sprintf("Payment completed."));
            }else if($json->type == 'charge.failed' && $charge->status == 'failed'){
                $order->update_status('failed', 'Payment has failed.');
            }else if($json->type == 'charge.cancelled' && $charge->status == 'cancelled'){
                $order->update_status('cancelled', 'Payment has been cancelled.');
            }
        }
    }

    public function createWebhook($force_host_ssl = false) {

        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';
        $url = site_url('/', $protocol).'wc-api/Checkout_Lending';          

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
             
        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, $this->country );
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-WOOC".$this->country."/v2";
        Openpay::setUserAgent($userAgent);
        
        try {
            $webhook = $openpay->webhooks->add($webhook_data);
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), '_openpay_webhook_id', $webhook->id);
            }
            return $webhook;
        } catch (Exception $e) {
            $force_host_ssl = ($force_host_ssl == false) ? true : false;
            $this->errorWebhook($e, $force_host_ssl, $url);
            return false;
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
            default:
                $msg = 'La petición no pudo ser procesada.';
                break;
        }
        
        $error = $e->getErrorCode().'. '.$msg;
               
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

    public function process_payment($order_id) {
        global $woocommerce;
        $this->order = new WC_Order($order_id);

        if ( get_post_meta( $this->order->get_id(), 'terms', true ) == 'on' ) {
            $this->is_privacy_terms_accepted = "true";
            if ($this->processOpenpayCharge()) {
                $this->order->reduce_order_stock();
                $woocommerce->cart->empty_cart();
                $this->order->add_order_note(sprintf("Payment will be processed by %s with Transaction Id: '%s'", $this->GATEWAY_NAME, $this->transaction_id));            
                
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($this->order)
                );
            } else {
                $this->order->add_order_note(sprintf("%s Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));

                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), $notice_type = 'error');
                } else {
                    $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
                }
            }
        }else{
            $this->order->add_order_note(sprintf("%s Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));

                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Por favor, lee y acepta los términos y condiciones para proceder con tu pedido.'), $notice_type = 'error');
                } else {
                    $woocommerce->add_error(__('Por favor, lee y acepta los términos y condiciones para proceder con tu pedido.'), 'woothemes');
                }
        }
    }

    protected function processOpenpayCharge() {
        date_default_timezone_set('America/Mexico_City');
        $amount = number_format((float) $this->order->get_total(), 2, '.', '');
        $items = Array();

        foreach ( $this->order->get_items() as $item_id => $item ) {
            $product = wc_get_product($item->get_product_id());
            Array_push($items, Array(
                                    "name" => $item->get_name(),
                                    "description" => $product->get_description(),
                                    "quantity" => $item->get_quantity(),
                                    "price" => $product->get_price(),
                                    "tax" => 0,
                                    "sku" => $product->get_sku(),
                                    "discount" => 0,
                                    "currency" => get_woocommerce_currency()
                                ) );

        }

        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';
        $url_failed = site_url('/', $protocol).'?wc-api=checkout_lending_failed&order_id='.$this->order->get_id();
        
        $charge_request = array(
            "method" => "lending",
            "amount" => $amount,
            "currency" => strtolower(get_woocommerce_currency()),
            "description" => sprintf("Cargo %s para %s", $this->order->get_id(), $this->order->get_billing_email()),                        
            "order_id" => $this->order->get_id(),

            "lending_data" => Array(
                "is_privacy_terms_accepted" => $this->is_privacy_terms_accepted,
                "callbacks" => Array(
                    "on_success" => html_entity_decode( $this->order->get_checkout_order_received_url()),
                    "on_reject" => $url_failed,
                    "on_canceled" => html_entity_decode( $this->order->get_cancel_order_url()),
                    "on_failed" => $url_failed
                ),
                "shipping" => Array(
                    "name" => $this->order->get_shipping_first_name() ? $this->order->get_shipping_first_name() : $this->order->get_billing_first_name(),
                    "last_name" => $this->order->get_shipping_last_name() ? $this->order->get_shipping_last_name() : $this->order->get_billing_last_name() ,
                    "address" => Array(
                        "address" => ($this->order->get_shipping_address_1() ? $this->order->get_shipping_address_1() : $this->order->get_billing_address_1())  . " " . ($this->order->get_shipping_address_2() ? $this->order->get_shipping_address_2() : $this->order->get_billing_address_2()) ,
                        "state" => $this->order->get_shipping_state() ? $this->order->get_shipping_state() : $this->order->get_billing_state()  ,
                        "city" => $this->order->get_shipping_city() ? $this->order->get_shipping_city() : $this->order->get_billing_city() ,
                        "zipcode" => $this->order->get_shipping_postcode() ? $this->order->get_shipping_postcode() : $this->order->get_billing_postcode()  ,
                        "country" => $this->order->get_shipping_country() ? $this->order->get_shipping_country() : $this->order->get_billing_country() 
                    ),
                    "email" => $this->order->get_billing_email(),
                ),
                "billing" => Array(
                    "name" => $this->order->get_billing_first_name(),
                    "last name" => $this->order->get_billing_last_name(),
                    "address" => Array(
                        "address" => $this->order->get_billing_address_1() . " " . $this->order->get_billing_address_2(),
                        "state" => $this->order->get_billing_state(),
                        "city" => $this->order->get_billing_city(),
                        "zipcode" => $this->order->get_billing_postcode(),
                        "country" => $this->order->get_billing_country()
                    ),
                    "phone_number" => $this->order->get_billing_phone(),
                    "email" => $this->order->get_billing_email()
                )
            )
        );

        $openpay_customer = $this->getOpenpayCustomer();
        $result_json = $this->createOpenpayCharge($openpay_customer, $charge_request);

        if ($result_json) {
            global $woocommerce;
            if($result_json->error_code && $result_json->error_message!=null){
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__($result_json->error_message), $notice_type = 'error');
                } else {
                    $woocommerce->add_error(__($result_json->error_message), 'woothemes');
                }
            }else{

            $this->transaction_id = $result_json->id;
            if ($this->is_sandbox) {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', true);
            } else {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true);
            }
            update_post_meta($this->order->get_id(), '_transaction_id', $result_json->id);    

            if($result_json->payment_method && $result_json->payment_method->type == 'lending'){
                update_post_meta($this->order->get_id(), '_openpay_callback_url', $result_json->payment_method->callbackUrl);
            }else{
                delete_post_meta($this->order->get_id(),'_openpay_callback_url');
            }
            
            return true;
            }
        } 
            return false;
    }

    public function createOpenpayCharge($customer, $charge_request) {
        Openpay::getInstance($this->merchant_id, $this->private_key, $this->country);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-WOOC".$this->country."/v2";
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
            if ($this->is_sandbox) {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', true);
            } else {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true);
            }              
        }

        if ($this->isNullOrEmptyString($customer_id)) {
            return $this->createOpenpayCustomer();
        } else {
            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, $this->country);
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
        $customer_data = array(
            'name' => $this->order->get_billing_first_name(),
            'last_name' => $this->order->get_billing_last_name(),
            'email' => $this->order->get_billing_email(),
            'requires_account' => false,
            'phone_number' => $this->order->get_billing_phone()
        );
        
        if ($this->hasAddress($this->order)) {
            $customer_data = $this->formatAddress($customer_data, $this->order);
        }

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, $this->country);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-WOOC".$this->country."/v2";
        Openpay::setUserAgent($userAgent);

        try {
            $customer = $openpay->customers->add($customer_data);

            if (is_user_logged_in()) {
                if ($this->is_sandbox) {
                    update_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', $customer->id);
                } else {
                    update_user_meta(get_current_user_id(), '_openpay_customer_id', $customer->id);
                }                
            }

            return $customer;
        } catch (Exception $e) {
            $this->error($e);
            return false;
        }
    }

    private function formatAddress($customer_data, $order) {
        if ($this->country === 'MX') {
            $customer_data['address'] = array(
                'line1' => substr($order->get_billing_address_1(), 0, 200),
                'line2' => substr($order->get_billing_address_2(), 0, 50),
                'state' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'postal_code' => $order->get_billing_postcode(),
                'country_code' => $order->get_billing_country()
            );
        }
        return $customer_data;
    }

    public function hasAddress($order) {
        if($order->get_billing_address_1() && $order->get_billing_state() && $order->get_billing_postcode() && $order->get_billing_country() && $order->get_billing_city()) {
            return true;
        }
        return false;    
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

    public function validateCurrency() {
        if ($this->country === 'MX') {
            return in_array(get_woocommerce_currency(), $this->currencies);            
        }
        return false;        
    }

    public function validateTerms(){
        
    }

    public function isNullOrEmptyString($string) {
        return (!isset($string) || trim($string) === '');
    }

    public function getOpenpayInstance() {
        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, $this->country);
        Openpay::setProductionMode($this->is_sandbox ? false : true);
        $userAgent = "Openpay-WOOC".$this->country."/v2";

        Openpay::setUserAgent($userAgent);

        return $openpay;
    }

 }


 add_action( 'template_redirect','checkout_lending_redirect_after_purchase',0);

 function checkout_lending_redirect_after_purchase() {
    global $wp;
    if (is_checkout() && !empty($wp->query_vars['order-received']) ) {
        $order = new WC_Order($wp->query_vars['order-received']);
        $url = get_post_meta($order->get_id(),'_openpay_callback_url',true);
        delete_post_meta($order->get_id(),'_openpay_callback_url');

        if ($url && $order->get_status() == 'pending') {
            wp_redirect($url);
            exit();
        }
    }
}

