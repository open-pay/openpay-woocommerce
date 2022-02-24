<?php

if (!class_exists('Openpay')) {
    require_once("lib/openpay/Openpay.php");
}

if(!class_exists('Utils')) {
    require_once("utils/utils.php");
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

    const VERSION_NUMBER_ADMIN_SCRIPT = '1.0.3';

    protected $GATEWAY_NAME = "Openpay Cards";
    protected $is_sandbox = true;
    protected $use_card_points = false;
    protected $save_cc = false;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('MXN', 'USD');
    protected $capture = true;
    protected $country = '';
    protected $merchant_classification = 'general';
    protected $affiliation_bbva = null;
    protected $iva = 0;
    protected $msi_options_pe = false;

    public function __construct() {        
        $this->id = 'openpay_cards';
        $this->method_title = __('Openpay Cards', 'openpay_cards');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger(); 
        $this->settings['current_currency'] = get_woocommerce_currency(); 
        
        $this->country = $this->settings['country'];
        $this->currencies = Utils::getCurrencies($this->country);       
        $this->iva = $this->country == 'CO' ? $this->settings['iva'] : 0; 
        $this->msi = $this->country == 'MX' ? $this->settings['msi'] : [];  
        $this->minimum_amount_interest_free = $this->country == 'MX' ? $this->settings['minimum_amount_interest_free'] : 0;

        $use_card_points = isset($this->settings['use_card_points']) ? (strcmp($this->settings['use_card_points'], 'yes') == 0) : false;
        $capture = isset($this->settings['capture']) ? (strcmp($this->settings['capture'], 'true') == 0) : true;
        $save_cc = isset($this->settings['save_cc']) ? (strcmp($this->settings['save_cc'], 'yes') == 0) : false;
        $msi_options_pe = isset($this->settings['msi_options_pe']) ? (strcmp($this->settings['msi_options_pe'], 'yes') == 0) : false;

        $this->charge_type = $this->country == 'MX' ? $this->settings['charge_type'] : 'direct';
        $this->use_card_points = $this->country == 'MX' ? $use_card_points : false;
        $this->capture = ($this->country == 'MX' || $this->country == 'PE' ) ? $capture : true;

        $this->title = 'Pago con tarjeta de crédito o débito';
        $this->description = '';        
        $this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->test_merchant_id = $this->settings['test_merchant_id'];
        $this->test_private_key = $this->settings['test_private_key'];
        $this->test_publishable_key = $this->settings['test_publishable_key'];
        $this->live_merchant_id = $this->settings['live_merchant_id'];
        $this->live_private_key = $this->settings['live_private_key'];
        $this->live_publishable_key = $this->settings['live_publishable_key'];        
        $this->merchant_classification = $this->settings['merchant_classification'];        
        $this->affiliation_bbva = $this->settings['affiliation_bbva'];        
        $this->merchant_id = $this->is_sandbox ? $this->test_merchant_id : $this->live_merchant_id;
        $this->publishable_key = $this->is_sandbox ? $this->test_publishable_key : $this->live_publishable_key;
        $this->private_key = $this->is_sandbox ? $this->test_private_key : $this->live_private_key;
        $this->save_cc = $save_cc;
        $this->msi_options_pe = $msi_options_pe;
        
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
        
        add_action('admin_enqueue_scripts', array($this, 'openpay_cards_admin_enqueue'), 10, 2);
    }   

    public function openpay_cards_admin_enqueue($hook) {
        wp_enqueue_script('openpay_cards_admin_form', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), self::VERSION_NUMBER_ADMIN_SCRIPT, true);
    }

    public function process_admin_options() {
        parent::process_admin_options();

        $post_data = $this->get_post_data(); 
        $mode = 'live';    
        d
        if(isset($post_data['woocommerce_'.$this->id.'_sandbox']) ){
            $mode = 'test';      
        }
        
        $this->merchant_id = $post_data['woocommerce_'.$this->id.'_'.$mode.'_merchant_id'];
        $this->affiliation_bbva = $post_data['woocommerce_'.$this->id.'_affiliation_bbva'];
        $this->private_key = $post_data['woocommerce_'.$this->id.'_'.$mode.'_private_key'];
        $this->country = $post_data['woocommerce_'.$this->id.'_country'];


        $env = ($mode == 'live') ? 'Producton' : 'Sandbox';

        $settings = new WC_Admin_Settings();
        if($this->merchant_id == '' || $this->private_key == ''){
            $settings->add_error('You need to enter "'.$env.'" credentials if you want to use this plugin in this mode.');
            return;
        }

        try{
            $this->setSettings($settings);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $settings->add_error($e->getMessage());
            return;
        }
       
        return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );      
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
    } 
    
    public function perform_ssl_check() {
        if (!$this->is_sandbox && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') :
            echo '<div class="error"><p>'.sprintf(__('%s sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->GATEWAY_NAME, admin_url('admin.php?page=settings')).'</p></div>';
        endif;
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'current_currency' => array (
                'type' => 'text',
                'title' => __('Moneda configurada', 'woothemes'),
                'default' => get_woocommerce_currency()
            ),
            'merchant_classification' => array(
                'type' => 'text',         
                'title' => __('Clasificación Comercio', 'woothemes'),
                'default' => 'GENERAL'
            ),
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
                'default' => 'yes'
            ),
            'country' => array(
                'type' => 'select',
                'title' => __('País', 'woothemes'),                             
                'default' => 'PE',
                'options' => array(
                    'MX' => 'México',
                    'CO' => 'Colombia',
                    'PE' => 'Perú',
                    'AR' => 'Argentina'
                )
            ),
            'affiliation_bbva' => array(
                'type' => 'text',         
                'title' => __('Número de afiliación', 'woothemes'),
                'description' => __('Número de afiliación BBVA', 'woothemes'),
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
            'msi_options_pe' => array(
                'type' => 'checkbox',
                'title' => __('Cuotas', 'woothemes'),
                'label' => __('Habilitar', 'woothemes'),
                'description' => __('Habilitar pagos a meses sin intereses.', 'woothemes'),
                'desc_tip' => true,
                'default' => 'no'
            ),
            'iva' => array(
                'type' => 'number',
                'required' => true,
                'title' => __('IVA', 'woothemes'),                
                'default' => '0',
                'id' => 'openpay_show_iva',                
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
    public function getOriginMerchant(){  
        $openpay = $this->getOpenpayInstance();
        return $openpay->getMerchantInfo();       
    }
    public function getInstallments() {
        $installments = [];
        for($i=2; $i <= 36; $i++) {
            $installments[$i] = $i.' cuotas';
        }
        
        return $installments;
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
        if ($this->country == 'MX') {     
            if($this->msi){       
                foreach ($this->msi as $msi) {
                    $months[$msi] = $msi . ' meses';
                }
            }
            
            if (count($months) > 0 && ($woocommerce->cart->total >= $this->minimum_amount_interest_free)) {
                $this->show_months_interest_free = true;
            }
        }
                             
        $this->show_installments = false;       
        
        if ($this->country == 'CO') {                        
            $this->installments = $this->getInstallments();
            $this->show_installments = true;
        }                
        
        $this->show_installments_pe = false;
        if($this->country == 'PE' && $this->msi_options_pe) $this->show_installments_pe = true;
        
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
            $openpay = $this->getOpenpayInstance();
            
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
         
        $scripts = Utils::getUrlScripts($this->country);
        $openpayJs = 'openpay_js';
        $openpayFraud = 'openpay_fraud_js';

        wp_enqueue_script($openpayJs, $scripts[$openpayJs], '', '', true);
        wp_enqueue_script($openpayFraud, $scripts[$openpayFraud], '', '', true);      
        wp_enqueue_script('payment', plugins_url('assets/js/jquery.payment.js', __FILE__), array( 'jquery' ), '', true);
        wp_enqueue_script('openpay', plugins_url('assets/js/openpay.js', __FILE__), array( 'jquery' ), '', true);        

        $openpay_params = array(
            'merchant_id' => $this->merchant_id,
            'public_key' => $this->publishable_key,
            'sandbox_mode' => $this->is_sandbox,
            'country' => $this->country,
            'total' => $woocommerce->cart->total,
            'show_months_interest_free' => false,
            'currency' => get_woocommerce_currency(),
            'bootstrap_css' => plugins_url('assets/css/bootstrap.css', __FILE__),
            'bootstrap_js' => plugins_url('assets/js/bootstrap.js', __FILE__),
            'use_card_points' => $this->use_card_points,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'msi_options_pe' => $this->msi_options_pe,
            'show_installments_pe' => false,
        );

        if ($this->msi && ($woocommerce->cart->total >= $this->minimum_amount_interest_free)) {
            $openpay_params['show_months_interest_free'] = true;
        }

        if ($woocommerce->cart->total >= $this->minimum_amount_interest_free) {
            $openpay_params['show_installments_pe'] = true;
        }

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
        $payment_plan = 0;        
        $use_card_points = $_POST['use_card_points'];
        $openpay_cc = $_POST['openpay_cc'];
        $save_cc = isset($_POST['save_cc']) ? true : false;
        
        if(isset($_POST['openpay_month_interest_free'])){
            $payment_plan = $_POST['openpay_month_interest_free'];
        }
        
        if(isset($_POST['openpay_installments'])){
            $payment_plan = $_POST['openpay_installments'];
        }

        if(isset($_POST['openpay_installments_pe'])){
            $payment_plan = $_POST['openpay_installments_pe'];
        }
        
        $this->order = new WC_Order($order_id);
        if ($this->processOpenpayCharge($device_session_id, $openpay_token, $payment_plan, $use_card_points, $openpay_cc, $save_cc)) {            
            $redirect_url = get_post_meta($order_id, '_openpay_3d_secure_url', true);     
            
            // Si no existe una URL de redireccionamiento y el cargo es inmediato, se marca la orden como completada
            if (!$redirect_url && $this->capture) {                
                $this->order->payment_complete();                            
                $this->order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", $this->GATEWAY_NAME, $this->transaction_id));                
            } else if (!$this->capture) {
                $this->order->update_status('on-hold');
                $this->order->add_order_note(sprintf("%s payment pre-authorized with Transaction Id of '%s'", $this->GATEWAY_NAME, $this->transaction_id));                
            }
                   
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
    
    protected function processOpenpayCharge($device_session_id, $openpay_token, $payment_plan, $use_card_points, $openpay_cc, $save_cc) {
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
            'capture' => $this->capture,
        );

        if($this->country === 'MX' && $this->merchant_classification == 'eglobal'){
            $charge_request['affiliation_bbva'] = $this->affiliation_bbva;
        }
        if ($this->country === 'CO') {
            $charge_request['iva'] = $this->iva;
        }
        
        $this->logger->debug('extra_data => '.json_encode(array('$openpay_cc' => $openpay_cc, '$save_cc' => $save_cc, '$capture' => $this->capture, '$charge_type' => $this->charge_type)));   
        
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
        
        if ($payment_plan > 1) {
            $charge_request['payment_plan'] = array('payments' => (int)$payment_plan);
        }   
        
        if ($this->charge_type == '3d') {
            $charge_request['use_3d_secure'] = true;
            $charge_request['redirect_url'] = $redirect_url_3d;
        }

        $charge = $this->createOpenpayCharge($openpay_customer, $charge_request, $redirect_url_3d);

        if ($charge != false) {
            $this->transaction_id = $charge->id;
            //Save data for the ORDER
            if ($this->is_sandbox) {
                update_post_meta($this->order->get_id(), '_openpay_customer_sandbox_id', $openpay_customer->id);
            } else {
                update_post_meta($this->order->get_id(), '_openpay_customer_id', $openpay_customer->id);
            }
            $captureString = ($this->capture) ? 'true' : 'false';
            update_post_meta($this->order->get_id(), '_transaction_id', $charge->id);            
            update_post_meta($this->order->get_id(), '_openpay_capture', $captureString);            
            
            if ($charge->payment_method && $charge->payment_method->type == 'redirect') {
                update_post_meta($this->order->get_id(), '_openpay_3d_secure_url', $charge->payment_method->url);                
            }else{
                delete_post_meta($this->order->get_id(), '_openpay_3d_secure_url');
            }            
            return true;
        } else {
            return false;
        }
    }

    public function createOpenpayCharge($customer, $charge_request, $redirect_url_3d) {
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
                }else{
                    delete_post_meta($this->order->get_id(), '_openpay_3d_secure_url');
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
            $openpay = $this->getOpenpayInstance();
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

        $openpay = $this->getOpenpayInstance();

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
        if ($this->country === 'MX' || $this->country === 'PE') {
            $customer_data['address'] = array(
                'line1' => substr($order->get_billing_address_1(), 0, 200),
                'line2' => substr($order->get_billing_address_2(), 0, 50),
                'state' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'postal_code' => $order->get_billing_postcode(),
                'country_code' => $order->get_billing_country()
            );
        } else if ($this->country === 'CO' ) {
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
        Openpay::setOriginMerchant($this->merchant_classification);
        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, $this->country);
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        if($this->merchant_classification === "eglobal")
            $userAgent = "BBVA-WOOC".$this->country."/v1";
        else
            $userAgent = "Openpay-WOOC".$this->country."/v2";

        Openpay::setUserAgent($userAgent);

        return $openpay;
    }

    private function setSettings($settings){
        switch ($this->country) {
            case 'MX':
                $merchantInfo = $this->getOriginMerchant();
                $this->merchant_classification = $merchantInfo->classification;
                $this->settings['merchant_classification'] = $merchantInfo->classification;

                if ($this->merchant_classification === 'eglobal') {
                    if($this->affiliation_bbva == ''){
                        $settings->add_error('The bbva affiliation field is required.');
                        $this->settings['charge_type'] = '3d';
                        $this->charge_type = '3d';
                        $this->country = 'MX';
                        $this->settings['country'] = 'MX';
                    }
                } else {
                    if($this->affiliation_bbva != ''){
                        $this->settings['charge_type'] = 'direct';
                        $this->charge_type = 'direct';
                        $this->settings['affiliation_bbva'] = '';
                        $this->affiliation_bbva = '';
                    }
                }
                
                break;
            case 'CO':
            case 'AR':
            case 'PE':
                $instance = $this->getOpenpayInstance();
                $instance->webhooks->getList(['limit'=>1]);
                break;
        }
    }

}

function openpay_cards_add_creditcard_gateway($methods) {
    array_push($methods, 'openpay_cards');
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'openpay_cards_add_creditcard_gateway');