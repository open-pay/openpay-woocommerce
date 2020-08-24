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

class Openpay_Stores extends WC_Payment_Gateway
{

    protected $GATEWAY_NAME = "Openpay Stores";
    protected $is_sandbox = true;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('MXN');  
    protected $logger = null;
    protected $country = '';
    protected $iva = 0;
    protected $show_map = false;

    public function __construct() {
        $this->id = 'openpay_stores';
        $this->method_title = __('Openpay Stores', 'openpay_stores');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger();      
        
        $this->country = $this->settings['country'];        
        $this->iva = $this->country == 'CO' ? $this->settings['iva'] : 0;     
        $this->show_map = $this->country == 'MX' ? $this->settings['show_map'] : false;     

        $this->title = 'Pago en efectivo en tiendas de conveniencia';
        $this->description = '';
        $this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->test_merchant_id = $this->settings['test_merchant_id'];
        $this->test_private_key = $this->settings['test_private_key'];
        
        $this->live_merchant_id = $this->settings['live_merchant_id'];
        $this->live_private_key = $this->settings['live_private_key'];        
        $this->deadline = $this->settings['deadline'];        

        $this->merchant_id = $this->is_sandbox ? $this->test_merchant_id : $this->live_merchant_id;        
        $this->private_key = $this->is_sandbox ? $this->test_private_key : $this->live_private_key;
        
        $pdf_url_base_mx = $this->is_sandbox ? 'https://sandbox-dashboard.openpay.mx/paynet-pdf' : 'https://dashboard.openpay.mx/paynet-pdf';
        $pdf_url_base_co = $this->is_sandbox ? 'https://sandbox-dashboard.openpay.co/paynet-pdf' : 'https://dashboard.openpay.co/paynet-pdf';        
        $this->pdf_url_base = $this->country === 'MX' ? $pdf_url_base_mx : $pdf_url_base_co;
        
        // tell WooCommerce to save options
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'webhook_handler'));     
                
        add_action('admin_enqueue_scripts', array($this, 'openpay_stores_admin_enqueue'), 10, 2);

        if (!$this->validateCurrency()) {
            $this->enabled = false;
        }        
    }
    
    public function openpay_stores_admin_enqueue($hook) {
        wp_enqueue_script('openpay_stores_admin_form', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '', true);
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

        if($json->transaction->method == 'store'){

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
            'country' => array(
                'type' => 'select',
                'title' => __('País', 'woothemes'),                             
                'default' => 'MX',
                'options' => array(
                    'MX' => 'México',
                    'CO' => 'Colombia',
                )
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
            ),
            'show_map' => array(
                'type' => 'checkbox',
                'title' => __('Mostrar mapa', 'woothemes'),
                'label' => __('Habilitar', 'woothemes'),
                'description' => __('Al selccionar esta opción, un mapa se desplegará mostrando las tiendas más cercanas al momento mostrar el recipo de pago (https://www.openpay.mx/docs/stores-map.html).', 'woothemes'),
                'default' => 'no',
                'id' => 'openpay_show_map',                
            ),
            'iva' => array(
                'type' => 'number',
                'required' => true,
                'title' => __('IVA', 'woothemes'),                
                'default' => '0',
                'id' => 'openpay_show_iva',                
            ),
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
            "method" => "store",
            "amount" => $amount,
            "currency" => strtolower(get_woocommerce_currency()),
            "description" => sprintf("Cargo para %s", $this->order->get_billing_email()),                        
            "order_id" => $this->order->get_id(),
            'due_date' => $due_date
        );
        
        if ($this->country === 'CO') {
            $charge_request['iva'] = $this->iva;
        }

        $openpay_customer = $this->getOpenpayCustomer();

        $result_json = $this->createOpenpayCharge($openpay_customer, $charge_request);

        if ($result_json != false) {
            $this->transaction_id = $result_json->id;
            $pdf_url = $this->pdf_url_base.'/'.$this->merchant_id.'/'.$result_json->payment_method->reference;
            //WC()->session->set('pdf_url', $pdf_url);
            //Save data for the ORDER
            if ($this->is_sandbox) {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', true);
            } else {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true);
            }
            update_post_meta($this->order->get_id(), '_transaction_id', $result_json->id);
            update_post_meta($this->order->get_id(), '_show_map', $this->show_map);               
            update_post_meta($this->order->get_id(), '_pdf_url', $pdf_url);            
            
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
            $this->order->add_order_note(sprintf("%s Store Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));

            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), $notice_type = 'error');
            } else {
                $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
            }
        }
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
        } else if ($this->country === 'CO') {
            $customer_data['customer_address'] = array(
                'department' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'additional' => substr($order->get_billing_address_1(), 0, 200).' '.substr($order->get_billing_address_2(), 0, 50)
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

    public function createWebhook($force_host_ssl = false) {

        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';
        $url = site_url('/', $protocol).'wc-api/Openpay_Stores';          

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
        if ($this->country === 'MX') {
            return in_array(get_woocommerce_currency(), $this->currencies);            
        } else if ($this->country === 'CO') {
            return get_woocommerce_currency() == 'COP';
        }
        
        return false;        
    }

    public function isNullOrEmptyString($string) {
        return (!isset($string) || trim($string) === '');
    }

}

function openpay_stores_add_creditcard_gateway($methods) {
    array_push($methods, 'openpay_stores');
    return $methods;
}

function openpay_stores_template($template, $template_name, $template_path) {
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

add_filter('woocommerce_payment_gateways', 'openpay_stores_add_creditcard_gateway');
add_filter('woocommerce_locate_template', 'openpay_stores_template', 1, 3);
//add_filter( 'woocommerce_email_attachments', 'attach_terms_conditions_pdf_to_email', 1, 3); 
//add_action('woocommerce_order_status_processing_to_completed', 'openpay_stores_order_status_completed');