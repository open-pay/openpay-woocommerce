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

class Openpay_Cards extends WC_Payment_Gateway
{

    protected $GATEWAY_NAME = "Openpay Cards";
    protected $is_sandbox = true;
    protected $use_card_points = false;
    protected $save_cc = false;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('MXN', 'USD');
    protected $capture = true;
    protected $msi = array();

    public function __construct() {        
        $this->id = 'openpay_cards';
        $this->method_title = __('Openpay Cards', 'openpay_cards');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger();
        
        $this->msi = $this->settings['msi'];
        $this->minimum_amount_interest_free = $this->settings['minimum_amount_interest_free'];
        
        $use_card_points = isset($this->settings['use_card_points']) ? (strcmp($this->settings['use_card_points'], 'yes') == 0) : false;
        $capture = isset($this->settings['capture']) ? (strcmp($this->settings['capture'], 'true') == 0) : true;
        $save_cc = isset($this->settings['save_cc']) ? (strcmp($this->settings['save_cc'], 'yes') == 0) : false;

        $this->title = 'Pago con tarjeta de crédito o débito';
        $this->description = '';        
        $this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->test_merchant_id = $this->settings['test_merchant_id'];
        $this->test_private_key = $this->settings['test_private_key'];
        $this->test_publishable_key = $this->settings['test_publishable_key'];
        $this->live_merchant_id = $this->settings['live_merchant_id'];
        $this->live_private_key = $this->settings['live_private_key'];
        $this->live_publishable_key = $this->settings['live_publishable_key'];
        $this->charge_type = $this->settings['charge_type'];
        $this->merchant_id = $this->is_sandbox ? $this->test_merchant_id : $this->live_merchant_id;
        $this->publishable_key = $this->is_sandbox ? $this->test_publishable_key : $this->live_publishable_key;
        $this->private_key = $this->is_sandbox ? $this->test_private_key : $this->live_private_key;
        $this->use_card_points = $use_card_points;
        $this->capture = $capture;
        $this->save_cc = $save_cc;

        if ($this->is_sandbox) {
            $this->description .= __('SANDBOX MODE ENABLED. In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date.', 'openpay-woosubscriptions');
        }
        
        if (!$this->validateCurrency()) {
            $this->enabled = false;
        }                        
        
        // https://developer.wordpress.org/reference/functions/add_action/
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action('admin_notices', array(&$this, 'perform_ssl_check'));        
        add_action('woocommerce_checkout_create_order', array($this, 'action_woocommerce_checkout_create_order'), 10, 2);                
    }       
    
    /**
     * Si el tipo de cargo esta configurado como Pre-autorización, el estatus de la orden es marcado como "on-hold"
     * 
     * @param type $order
     * @param type $data
     * 
     * @link https://docs.woocommerce.com/wc-apidocs/source-class-WC_Checkout.html#334
     */
    public function action_woocommerce_checkout_create_order($order, $data) {        
        $this->logger->debug('action_woocommerce_checkout_create_order => '.json_encode(array('$this->capture' => $this->capture)));   
        if (!$this->capture && $order->get_payment_method() == 'openpay_cards') {
            $order->set_status('on-hold', 'Pre-autorización');            
        }        
    } 
    
    public function perform_ssl_check() {
        if (!$this->is_sandbox && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') :
            echo '<div class="error"><p>'.sprintf(__('%s sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->GATEWAY_NAME, admin_url('admin.php?page=settings')).'</p></div>';
        endif;
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
            'charge_type' => array(
		'title' => __('¿Cómo procesar el cargo?', 'woocommerce'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('¿Qué es la autenticación selectiva? Es cuando Openpay detecta cierto riesgo de fraude y envía el cargo a través de 3D Secure.', 'woocommerce'),
                'default' => 'direct',
                'desc_tip' => true,
                'options' => array(
                    'direct' => __('Directo', 'woocommerce'),
                    'auth' => __('Autenticación selectiva', 'woocommerce'),
                    '3d' => __('3D Secure', 'woocommerce'),
                ),
            ),
            'capture' => array(
		'title' => __('Configuración del cargo', 'woocommerce'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',             
                'description' => __('Indica si el cargo se hace o no inmediatamente, con la pre-autorización solo se reserva el monto para ser confirmado o cancelado posteriormente. Las pre-autorizaciones no pueden ser utilizadas en combinación con pago con puntos Bancomer.', 'woocommerce'),                
                'default' => 'true',
                'desc_tip' => true,
                'options' => array(
                    'true' => __('Cargo inmediato', 'woocommerce'),
                    'false' => __('Pre-autorizar únicamente', 'woocommerce')
                ),
            ),
            'use_card_points' => array(
                'type' => 'checkbox',
                'title' => __('Pago con puntos', 'woothemes'),
                'label' => __('Habilitar', 'woothemes'),
                'description' => __('Recibe pagos con puntos Bancomer habilitando esta opción. Esta opción no se puede combinar con pre-autorizaciones.', 'woothemes'),
                'desc_tip' => true,
                'default' => 'no'
            ),
            'save_cc' => array(
                'type' => 'checkbox',
                'title' => __('Guardar tarjetas', 'woothemes'),
                'label' => __('Habilitar', 'woothemes'),
                'description' => __('Permite a los usuarios registrados guardar sus tarjetas de crédito para agilizar sus futuras compras.', 'woothemes'),
                'desc_tip' => true,
                'default' => 'no'
            ),
            'msi' => array(
                'title' => __('Meses sin intereses', 'woocommerce'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'css' => 'width: 400px;',
                'default' => '',
                'options' => $this->getMsi(),
                'custom_attributes' => array(
                    'data-placeholder' => __('Opciones', 'woocommerce'),
                ),
            ),
            'minimum_amount_interest_free' => array(
                'type' => 'number',
                'title' => __('Monto mínimo MSI', 'woothemes'),
                'description' => __('Monto mínimo para aceptar meses sin intereses.', 'woothemes'),
                'default' => __('1', 'woothemes')
            )
        );
    }

    public function getMsi() {
        return array('3' => '3 meses', '6' => '6 meses', '9' => '9 meses', '12' => '12 meses', '18' => '18 meses');
    }

    public function admin_options() {
        include_once('templates/admin.php');
    }

    public function payment_fields() {
        //$this->images_dir = WP_PLUGIN_URL."/".plugin_basename(dirname(__FILE__)).'/assets/images/';    
        global $woocommerce;
        $this->images_dir = plugin_dir_url( __FILE__ ).'/assets/images/';
                
        $this->show_months_interest_free = false;
        
        $months = array();          
        foreach ($this->msi as $msi) {
            $months[$msi] = $msi . ' meses';
        }
        if (count($months) > 0 && ($woocommerce->cart->total >= $this->minimum_amount_interest_free)) {
            $this->show_months_interest_free = true;
        }
        
        $this->months = $months;
        $this->cc_options = $this->getCreditCardList();
        $this->can_save_cc = $this->save_cc && is_user_logged_in();
        
        include_once('templates/payment.php');
    }
    
    private function createCreditCard($customer, $data) {
        try {
            return $customer->cards->add($data);            
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());            
            throw $e;
        }        
    }
    
    private function getCreditCardList() {
        if (!is_user_logged_in()) {            
            return array(array('value' => 'new', 'name' => 'Nueva tarjeta'));
        }        
                
        if($this->is_sandbox){
            $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', true); 
        }else{
            $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true); 
        }

        if ($this->isNullOrEmptyString($customer_id)) {
            return array(array('value' => 'new', 'name' => 'Nueva tarjeta'));
        } 
        
        $list = array(array('value' => 'new', 'name' => 'Nueva tarjeta'));        
        try {            
            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
            Openpay::setProductionMode($this->is_sandbox ? false : true);
            
            $customer = $openpay->customers->get($customer_id);
            
            $cards = $this->getCreditCards($customer);            
            foreach ($cards as $card) {                
                array_push($list, array('value' => $card->id, 'name' => strtoupper($card->brand).' '.$card->card_number));
            }
            
            return $list;            
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());            
            return $list;
        }        
    }
    
    private function getCreditCards($customer) {        
        try {
            return $customer->cards->getList(array(                
                'offset' => 0,
                'limit' => 10
            ));            
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());            
            throw $e;
        }        
    }

    /**
     * payment_scripts function.
     *
     * Outputs scripts used for openpay payment
     *
     * @access public
     */
    public function payment_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        global $woocommerce;
                
        wp_enqueue_script('openpay_js', 'https://openpay.s3.amazonaws.com/openpay.v1.min.js', '', '', true);
        wp_enqueue_script('openpay_fraud_js', 'https://openpay.s3.amazonaws.com/openpay-data.v1.min.js', '', '', true);        
        wp_enqueue_script('payment', plugins_url('assets/js/jquery.payment.js', __FILE__), array( 'jquery' ), '', true);
        wp_enqueue_script('openpay', plugins_url('assets/js/openpay.js', __FILE__), array( 'jquery' ), '', true);        


        $openpay_params = array(
            'merchant_id' => $this->merchant_id,
            'public_key' => $this->publishable_key,
            'sandbox_mode' => $this->is_sandbox,
            'total' => $woocommerce->cart->total,
            'currency' => get_woocommerce_currency(),
            'bootstrap_css' => plugins_url('assets/css/bootstrap.css', __FILE__),
            'bootstrap_js' => plugins_url('assets/js/bootstrap.js', __FILE__),
            'use_card_points' => $this->use_card_points
        );

        // If we're on the pay page we need to pass openpay.js the address of the order.
        if (is_checkout_pay_page() && isset($_GET['order']) && isset($_GET['order_id'])) {
            $order_key = urldecode($_GET['order']);
            $order_id = absint($_GET['order_id']);
            $order = new WC_Order($order_id);

            if ($order->get_id() == $order_id && $order->get_order_key() == $order_key) {
                $openpay_params['get_billing_first_name()'] = $order->get_billing_first_name();
                $openpay_params['get_billing_last_name()'] = $order->get_billing_last_name();
                $openpay_params['get_billing_address_1()'] = $order->get_billing_address_1();
                $openpay_params['get_billing_address_2()'] = $order->get_billing_address_2();
                $openpay_params['get_billing_state()'] = $order->get_billing_state();
                $openpay_params['get_billing_city()'] = $order->get_billing_city();
                $openpay_params['get_billing_postcode()'] = $order->get_billing_postcode();
                $openpay_params['get_billing_country()'] = $order->get_billing_country();
            }
        }

        wp_localize_script('openpay', 'wc_openpay_params', $openpay_params);
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
        $device_session_id = isset($_POST['device_session_id']) ? wc_clean($_POST['device_session_id']) : '';
        $openpay_token = $_POST['openpay_token'];
        $interest_free = null;
        $use_card_points = $_POST['use_card_points'];
        $openpay_cc = $_POST['openpay_cc'];
        $save_cc = isset($_POST['save_cc']) ? true : false;
        
        if(isset($_POST['openpay_month_interest_free'])){
            $interest_free = $_POST['openpay_month_interest_free'];
        }
        
        $this->order = new WC_Order($order_id);
        if ($this->processOpenpayCharge($device_session_id, $openpay_token, $interest_free, $use_card_points, $openpay_cc, $save_cc)) {            
            $redirect_url = get_post_meta($order_id, '_openpay_3d_secure_url', true);     
            
            // Si no existe una URL de redireccionamiento y el cargo es inmediato, se marca la orden como completada
            if (!$redirect_url && $this->capture) {                
                $this->order->payment_complete();                            
                $this->order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", $this->GATEWAY_NAME, $this->transaction_id));                
            } else if (!$this->capture) {
                $this->order->add_order_note(sprintf("%s payment pre-authorized with Transaction Id of '%s'", $this->GATEWAY_NAME, $this->transaction_id));                
            }
                             
            $woocommerce->cart->empty_cart();            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
        } else {
            $this->order->add_order_note(sprintf("%s Credit Card Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));
            $this->order->set_status('failed');
            $this->order->save();
            
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
            } else {
                $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
            }
        }
    }
    
    protected function processOpenpayCharge($device_session_id, $openpay_token, $interest_free, $use_card_points, $openpay_cc, $save_cc) {
        WC()->session->__unset('pdf_url');   
        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';                             
        $redirect_url_3d = site_url('/', $protocol).'?wc-api=openpay_confirm';
        $amount = number_format((float)$this->order->get_total(), 2, '.', '');
                
        $charge_request = array(
            "method" => "card",
            "amount" => $amount,
            "currency" => strtolower(get_woocommerce_currency()),
            "source_id" => $openpay_token,
            "device_session_id" => $device_session_id,
            "description" => sprintf("Items: %s", $this->getProductsDetail()),            
            "order_id" => $this->order->get_id(),
            'use_card_points' => $use_card_points,
            'capture' => $this->capture
        );
        
        $this->logger->debug('extra_data => '.json_encode(array('$openpay_cc' => $openpay_cc, '$save_cc' => $save_cc, '$capture' => $this->capture)));   
        
        $this->logger->info('processOpenpayCharge Order => '.$this->order->get_id());   
        
        $openpay_customer = $this->getOpenpayCustomer();
        
        if ($save_cc === true && $openpay_cc == 'new') {
            $card_data = array(            
                'token_id' => $openpay_token,            
                'device_session_id' => $device_session_id
            );
            $card = $this->createCreditCard($openpay_customer, $card_data);

            // Se reemplaza el "source_id" por el ID de la tarjeta
            $charge_request['source_id'] = $card->id;                                                            
        }     
        
        if ($interest_free > 1) {
            $charge_request['payment_plan'] = array('payments' => (int)$interest_free);
        }   
        
        if ($this->charge_type == '3d') {
            $charge_request['use_3d_secure'] = true;
            $charge_request['redirect_url'] = $redirect_url_3d;
        }

        $charge = $this->createOpenpayCharge($openpay_customer, $charge_request, $redirect_url_3d);

        if ($charge != false) {
            $this->transaction_id = $charge->id;
            //Save data for the ORDER
            update_post_meta($this->order->get_id(), '_openpay_customer_id', $openpay_customer->id);
            update_post_meta($this->order->get_id(), '_transaction_id', $charge->id);            
            update_post_meta($this->order->get_id(), '_openpay_capture', ($this->capture ? 'true' : 'false'));            
            
            if ($charge->payment_method && $charge->payment_method->type == 'redirect') {
                update_post_meta($this->order->get_id(), '_openpay_3d_secure_url', $charge->payment_method->url);                
            }            
            return true;
        } else {
            return false;
        }
    }

    public function createOpenpayCharge($customer, $charge_request, $redirect_url_3d) {
        Openpay::getInstance($this->merchant_id, $this->private_key);        
        Openpay::setProductionMode($this->is_sandbox ? false : true);
        
        try {
            $charge = $customer->charges->create($charge_request);
            return $charge;
        } catch (Exception $e) {           
            // Si cuenta con autenticación selectiva y hay detección de fraude se envía por 3D Secure
            if ($this->charge_type == 'auth' && $e->getCode() == '3005') {
                $charge_request['use_3d_secure'] = true;
                $charge_request['redirect_url'] = $redirect_url_3d;
                $charge = $customer->charges->create($charge_request);
                
                $this->logger->info('createOpenpayCharge Auth Order => '.$this->order->get_id()); 
                
                
                if ($charge->payment_method && $charge->payment_method->type == 'redirect') {
                    $this->logger->info('createOpenpayCharge update_post_meta => '.$charge->payment_method->url); 
                    update_post_meta($this->order->get_id(), '_openpay_3d_secure_url', $charge->payment_method->url);                
                }            
                
                return $charge;
            }
            
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
        } 
        
        try {
            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
            Openpay::setProductionMode($this->is_sandbox ? false : true);
            return $openpay->customers->get($customer_id);
        } catch (Exception $e) {
            $this->error($e);
            return false;
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
        
        if($this->hasAddress($this->order)) {
            $customerData['address'] = array(
                'line1' => substr($this->order->get_billing_address_1(), 0, 200),
                'line2' => substr($this->order->get_billing_address_2(), 0, 50),
                'line3' => '',
                'state' => $this->order->get_billing_state(),
                'city' => $this->order->get_billing_city(),
                'postal_code' => $this->order->get_billing_postcode(),
                'country_code' => $this->order->get_billing_country()
            );
        }

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

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
    
    public function hasAddress($order) {
        if($order->get_billing_address_1() && $order->get_billing_state() && $order->get_billing_postcode() && $order->get_billing_country() && $order->get_billing_city()) {
            return true;
        }
        return false;    
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
        return $openpay;
    }

}

function openpay_cards_add_creditcard_gateway($methods) {
    array_push($methods, 'openpay_cards');
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'openpay_cards_add_creditcard_gateway');

