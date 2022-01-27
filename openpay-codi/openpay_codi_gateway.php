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

class Openpay_Codi extends WC_Payment_Gateway
{

    protected $GATEWAY_NAME = "Openpay CoDi®";
    protected $is_sandbox = true;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('MXN', 'USD');

    public function __construct() {        
        $this->id = 'openpay_codi';
        $this->method_title = __('Openpay CoDi®', 'openpay_codi');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger();

        $this->title = 'Pago vía CoDi®';
        $this->description = '';        
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
        $this->codi_expiration = strcmp($this->settings['codi_expiration'], 'yes') == 0;
        $this->expiration_time = $this->settings['expiration_time'];
        $this->unit_time = $this->settings['unit_time'];
        
        $this->description .= __('CoDi® es una plataforma desarrollada por el Banco de México para facilitar las transacciones de pago y cobro a través de transferencias electrónicas, de forma rápida, segura y eficiente, a través de teléfonos móviles.', 'openpay_codi');
    
        if (!$this->validateCurrency()) {
            $this->enabled = false;
        }                        
        
        // https://developer.wordpress.org/reference/functions/add_action/
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'webhook_handler'));

        add_action('admin_notices', array(&$this, 'perform_ssl_check'));               
        
        add_action('admin_enqueue_scripts', array($this, 'openpay_codi_admin_enqueue'), 10, 2);
    }   

    public function openpay_codi_admin_enqueue($hook) {
        wp_enqueue_script('openpay_codi_admin_form', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '', true);
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
    
    public function perform_ssl_check() {
        if (!$this->is_sandbox && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') :
            echo '<div class="error"><p>'.sprintf(__('%s sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->GATEWAY_NAME, admin_url('admin.php?page=settings')).'</p></div>';
        endif;
    }

    public function webhook_handler() {
        header('HTTP/1.1 200 OK');        
        $obj = file_get_contents('php://input');
        $json = json_decode($obj);

        if($json->transaction->method == 'codi'){

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
            'test_publishable_key' => array(
                'type' => 'text',
                'title' => __('Llave pública de pruebas', 'woothemes'),
                'description' => __('Obten tus llaves de prueba de tu cuenta de Openpay ("pk_").', 'woothemes'),
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
            'live_publishable_key' => array(
                'type' => 'text',
                'title' => __('Llave pública de producción', 'woothemes'),
                'description' => __('Obten tus llaves de producción de tu cuenta de Openpay ("pk_").', 'woothemes'),
                'default' => __('', 'woothemes')
            ),         
            'codi_expiration' => array(
		        'title' => __('Fecha límite de pago', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Si', 'woothemes'),
                'description' => __('La fecha de expiración por defecto es 5 minutos después de la realización del cargo.', 'woocommerce'),
                'default' => 'no',
            ),
            'expiration_time' => array(
                'title' => __('Tiempo límite de pago', 'woocommerce'),
                'type' => 'number',
                'required' => true,
                'description' => __('Define el tiempo que tiene el cliente para realizar el pago.','woocommerce'),
                'default' => __('5', 'woocommerce')
            ),
            'unit_time' => array(
		        'title' => __('Unidad de tiempo', 'woocommerce'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Unidad de tiempo para definir la expiración de pago.', 'woocommerce'),
                'default' => 'minute',
                'desc_tip' => true,                
                'options' => array(
                    'minutes' => __('Minuto (s)', 'woocommerce'),
                    'hours' => __('Hora (s)', 'woocommerce'),
                    'day' => __('Día (s)', 'woocommerce')
                ),
            ),
        );
    }

    public function admin_options() {                
        include_once('templates/admin.php');
    }

    public function payment_fields() {
        //$this->images_dir = WP_PLUGIN_URL."/".plugin_basename(dirname(__FILE__)).'/assets/images/';    
        global $woocommerce;
        $this->images_dir = plugin_dir_url( __FILE__ ).'/assets/images/';    
        
        include_once('templates/payment.php');
    }
    
    private function getProductsDetail() {
        $order = $this->order;
        $products = [];
        foreach( $order->get_items() as $item_product ){                        
            $product = $item_product->get_product();                        
            $products[] = $product->get_name();
        }
        return substr(implode(', ', $products), 0, 249);
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
            $this->order->add_order_note(sprintf("%s  CoDi® Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));
            $this->order->set_status('failed');
            
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
            } else {
                $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
            }
        }
    }
    
    protected function processOpenpayCharge() {
        WC()->session->__unset('pdf_url');   
        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';                             
        $redirect_url_3d = site_url('/', $protocol).'?wc-api=openpay_confirm';
        $amount = number_format((float)$this->order->get_total(), 2, '.', '');
        $codi_options = array('mode' => 'QR_CODE');

        $charge_request = array(
            "method" => "codi",
            "amount" => $amount,
            "currency" => strtolower(get_woocommerce_currency()),
            "description" => sprintf("Items: %s", $this->getProductsDetail()),            
            //"order_id" => $this->order->get_id(),
            "codi_options" => $codi_options
        );

        if($this->codi_expiration){
            date_default_timezone_set('America/Mexico_City');
            $due_date = date('Y-m-d\TH:i:s', strtotime('+ '.$this->expiration_time.' '.$this->unit_time));
            $charge_request['due_date'] = $due_date;
        }
        
        $this->logger->debug('extra_data => '.json_encode(array('$amount' => $amount)));   
        
        $this->logger->info('processOpenpayCharge Order => '.$this->order->get_id());   
        
        $openpay_customer = $this->getOpenpayCustomer();

        $charge = $this->createOpenpayCharge($openpay_customer, $charge_request);

        if ($charge != false) {
            $this->transaction_id = $charge->id;
            //Save data for the ORDER
            if ($this->is_sandbox) {
                update_post_meta($this->order->get_id(), '_openpay_customer_sandbox_id', $openpay_customer->id);
            } else {
                update_post_meta($this->order->get_id(), '_openpay_customer_id', $openpay_customer->id);
            }
            update_post_meta($this->order->get_id(), '_transaction_id', $charge->id);           
            
            if ($charge->payment_method && $charge->payment_method->type == 'codi') {
                update_post_meta($this->order->get_id(), '_openpay_due_date', $charge->due_date);                
                update_post_meta($this->order->get_id(), '_openpay_barcode_base64', $charge->payment_method->barcode_base64);             
            }else{
                delete_post_meta($this->order->get_id(), '_openpay_due_date');
                delete_post_meta($this->order->get_id(), '_openpay_barcode_base64');
            }            
            return true;
        } else {
            return false;
        }
    }

    public function createOpenpayCharge($customer, $charge_request) {
        Openpay::getInstance($this->merchant_id, $this->private_key);        
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-CoDiMX/v1";
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
        } 
        
        try {
            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
            Openpay::setProductionMode($this->is_sandbox ? false : true);

            $userAgent = "Openpay-CoDiMX/v1";
            Openpay::setUserAgent($userAgent);

            return $openpay->customers->get($customer_id);
        } catch (Exception $e) {
            $this->error($e);
            return false;
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

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-CoDiMX/v1";
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
        $customer_data['address'] = array(
            'line1' => substr($order->get_billing_address_1(), 0, 200),
            'line2' => substr($order->get_billing_address_2(), 0, 50),
            'state' => $order->get_billing_state(),
            'city' => $order->get_billing_city(),
            'postal_code' => $order->get_billing_postcode(),
            'country_code' => $order->get_billing_country()
        );
        
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
        $url = site_url('/', $protocol).'wc-api/Openpay_CoDi';          

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
             
        $openpay = $this->getOpenpayInstance();
        
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

    public function error(Exception $e) {
        global $woocommerce;

        /* 6001 el webhook ya existe */
        switch ($e->getCode()) {
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
        $error = 'ERROR '.$e->getErrorCode().'. '.$msg;        
        $this->transactionErrorMessage = $error;
        if (function_exists('wc_add_notice')) {
            wc_add_notice($error, $notice_type = 'error');
        } else {
            $woocommerce->add_error(__('Payment error:', 'woothemes').$error);
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
    
    public function getOpenpayInstance() {
        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-CoDiMX/v1";
        Openpay::setUserAgent($userAgent);

        return $openpay;
    }

}

function openpay_codi_add_creditcard_gateway($methods) {
    array_push($methods, 'openpay_codi');
    return $methods;
}

function openpay_codi_template($template, $template_name, $template_path) {
    global $woocommerce;

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

if ( ! function_exists( 'openpay_thank_you_script' ) ) {
	function openpay_thank_you_script( $order_id ) {
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$order_id               = $order->get_id(); // order id
				$order_key              = $order->get_order_key(); // order key
				$order_total            = $order->get_total(); // order total
				$order_currency         = $order->get_currency(); // order currency
				$order_payment_method   = $order->get_payment_method(); // order payment method
				$order_shipping_country = $order->get_shipping_country(); // order shipping country
				$order_billing_country  = $order->get_billing_country(); // order billing country
                $order_status           = $order->get_status(); // order status
				/**
				 * full list methods and property that can be accessed from $order object
				 * https://docs.woocommerce.com/wc-apidocs/class-WC_Order.html
				 */
				?>
                <script type="text/javascript">
                    var due_date = '<?= get_post_meta($order_id, '_openpay_due_date', true)?>';
                    console.log(due_date);
                    var countDownDate = new Date(due_date).getTime();
                    fetchStatus(); 

                    function fetchStatus(){
                        jQuery.ajax({
                            url : '<?php echo site_url(); ?>/wp-admin/admin-ajax.php?action=fetch_order_status&order_id=<?php echo $order->get_order_number(); ?>',
                            type : 'post',      
                            error : function(response){
                                console.log(response);
                            },
                            success : function( response ){
                                console.log(response);
                                if(response === "processing"){
                                    clearInterval(x);
                                    document.getElementById("CoDiImage").src='<?= plugins_url() ?>'+'/openpay-codi/assets/images/check2.png';
                                    document.getElementById("CodiTimerTxt").innerHTML = "Su pago ha sido &nbsp;";
                                    document.getElementById("CoDiTimer").innerHTML = "completado";
                                    document.getElementById("CoDiTimer").style.color = "#34a964";
                                    document.getElementById("CoDiHeader").style.display = "none";
                                }
                            }
                        });
                    }

                    var x = setInterval(function() {
                        // Get today's date and time
                        var now = new Date().getTime();
                        // Find the distance between now and the count down date
                        var distance = countDownDate - now;

                        // Time calculations for days, hours, minutes and seconds
                        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                        var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                        var timer = "Completa tu compra en:&nbsp; ";

                        if(days > 0)
                            timer += days + "d ";
                        
                        if(hours > 0)
                            timer += ((hours < 10) ? "0" + hours : hours) + "h ";

                        if(minutes > 0)
                            timer += ((minutes < 10) ? "0" + minutes : minutes) + "m ";
                        
                        if(seconds >= 0)
                            timer += ((seconds < 10) ? "0" + seconds : seconds ) + "s ";

                        if(seconds % 9 == 0){
                            fetchStatus()
                        }
                           

                        // Display the result in the element with id="demo"
                        document.getElementById("CoDiTimer").innerHTML = timer;

                        // If the count down is finished, write some text
                        if (distance < 0) {
                            clearInterval(x);
                            document.getElementById("CodiTimerTxt").innerHTML = "Su pago ha &nbsp;";
                            document.getElementById("CoDiTimer").innerHTML = "expirado";
                        }
                    }, 1000);
                </script>
                <style>
                    .codi {
                        padding: 15px 0;
                    }
                    .codi__header {
                        text-align: center;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .codi__icon > img {
                        width: 40px;
                        height: 40px;
                    }
                    .codi__subtitle {
                        font-size: 14px;
                        color: #333;
                        text-align: center;
                    }
                    .codi__image {
                        margin: 0;
                        text-align: center;
                    }
                    .codi__image > img {
                        margin: 0 auto;
                        min-height: 100px;
                        height: 35%;
                        max-height: 210px;
                    }
                    .codi__information {
                        display: flex;
                        justify-content: center;
                        font-weight: bold;
                        font-size: 16px;
                    }
                    .codi__text.codi__text--small {
                        color: #C9C9C9;
                        padding-left: 5px;
                    }
                    .codi__text.codi__text--timer {
                        text-align: center;
                        color: #434343;
                        font-weight: bold;
                    }
                    .codi__expiration {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        font-size: 18px;
                    }
                    .codi__timer {
                        text-align: center;
                        color: #FF6F04;
                        font-weight: bolder;
                    }
                </style>
				<?php
			}
		}
	}
}
function woo_change_order_received_text( $str, $order ) {
    $new_str = 'Para completar tu pago deberás escanear el código QR.';
    return $new_str;
}
add_filter('woocommerce_payment_gateways', 'openpay_codi_add_creditcard_gateway');
add_filter('woocommerce_locate_template', 'openpay_codi_template', 1, 3);
add_action( "woocommerce_thankyou", "openpay_thank_you_script", 20 );
//add_filter('woocommerce_thankyou_order_received_text', 'woo_change_order_received_text', 10, 2 );

function fetch_order_status(){
    $order = wc_get_order( $_REQUEST['order_id'] );
    $order_data = $order->get_data();
    echo $order_data['status'];
    die();
}

add_action('wp_ajax_nopriv_fetch_order_status', 'fetch_order_status');
add_action('wp_ajax_fetch_order_status','fetch_order_status');