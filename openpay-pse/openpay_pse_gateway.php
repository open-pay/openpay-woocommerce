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

class Openpay_Pse extends WC_Payment_Gateway {

    protected $GATEWAY_NAME = "Openpay PSE";
    protected $is_sandbox = true;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('COP');
    protected $test_merchant_id;
    protected $test_private_key;
    protected $live_merchant_id;
    protected $live_private_key;
    protected $iva;
    protected $merchant_id;
    protected $private_key;
    protected $images_dir;

    public function __construct() {
        $this->id = 'openpay_pse';
        $this->method_title = __('Openpay PSE', 'openpay_pse');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = 'Pago vía PSE';
        $this->description = '';
        $this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->test_merchant_id = $this->settings['test_merchant_id'];
        $this->test_private_key = $this->settings['test_private_key'];        
        $this->live_merchant_id = $this->settings['live_merchant_id'];
        $this->live_private_key = $this->settings['live_private_key'];
        $this->iva = $this->settings['iva'];

        $this->merchant_id = $this->is_sandbox ? $this->test_merchant_id : $this->live_merchant_id;        
        $this->private_key = $this->is_sandbox ? $this->test_private_key : $this->live_private_key;

        // tell WooCommerce to save options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_enqueue_scripts', array($this, 'openpay_pse_admin_enqueue'), 10, 2);
        add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'webhook_handler'));


        if (!$this->validateCurrency()) {
            $this->enabled = false;
        }
    }

    public function openpay_pse_admin_enqueue($hook) {
        wp_enqueue_script('openpay_pse_admin_form', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '', true);
    }

    public function process_admin_options() {
        $logger = wc_get_logger();
        $logger->info('process_admin_options');

        $post_data = $this->get_post_data();
        $mode = 'live';

        if ($post_data['woocommerce_' . $this->id . '_sandbox'] == '1') {
            $mode = 'test';
        }

        $this->merchant_id = $post_data['woocommerce_' . $this->id . '_' . $mode . '_merchant_id'];
        $this->private_key = $post_data['woocommerce_' . $this->id . '_' . $mode . '_private_key'];

        $env = ($mode == 'live') ? 'Production' : 'Sandbox';

        if ($this->merchant_id == '' || $this->private_key == '') {
            $settings = new WC_Admin_Settings();
            $settings->add_error('You need to enter "' . $env . '" credentials if you want to use this plugin in this mode.');
        } else {
            $this->createWebhook(false, $this->merchant_id, $this->private_key);
        }

        return parent::process_admin_options();
    }

    public function webhook_handler() {
        $logger = wc_get_logger();
        $logger->info('webhook_handler START');
        header('HTTP/1.1 200 OK');        
        $obj = file_get_contents('php://input');
        $json = json_decode($obj);

        $logger->info('webhook_handler notification object => '. $obj);
        $logger->info('webhook_handler idTransaction=> '.$json->transaction->id);

        $auth = $_SERVER["HTTP_AUTHORIZATION"];
        $auth_array = explode(" ", $auth);
        $un_pw = explode(":", base64_decode($auth_array[1]));
        $un = $un_pw[0];
        $pw = $un_pw[1];
        $openpay_user = $this->encrypt('decode',$un,get_option('openpay_wkey'),get_option('openpay_wiv'));
        $openpay_pwd = $this->encrypt('decode',$pw,get_option('openpay_wkey'),get_option('openpay_wiv'));

        $logger->info('USER DECRIPT => '.$openpay_user);
        $logger->info('PSWD DECRIPT => '.$openpay_pwd);

        if(($openpay_user != $this->merchant_id || $openpay_pwd != $this->private_key) && $json->transaction->method == 'bank_account'){
            $logger->error('Webhook Autentication failed');
            header('HTTP/1.1 401 Unauthorized'); 
            return;
        }

        if($json->transaction->method != 'bank_account'){
            $logger->info('Skipping Transaction: ' . $json->transaction->id . ' - method:' . $json->transaction->method );
            return;
        }

        if($json->transaction->method == 'bank_account'){
            $logger->info('webhook_handler paymentMethod => bank_account');

            $order_id = $json->transaction->order_id;
            $order = new WC_Order($order_id);

            $order_status = $order->get_status();
            $logger->info('webhook_handler order_status => ' . $order_status);
            if ($order_status == 'processing'){
                exit();
            }
            if ($json->type == 'charge.succeeded'){
                if ($order_status != 'processing'){
                    $payment_date = date("Y-m-d", strtotime($json->event_date));
                    $order->update_meta_data( 'openpay_payment_date', $payment_date );
                    $order->payment_complete();
                    $order->add_order_note(sprintf("Payment completed. Confirmed by Webhook"));
                    $order->update_meta_data('_transaction_id',$charge->id);
                    $logger->info('webhook_handler Status => completed');
                }
            } else if($json->type == 'charge.failed') {
                if ($order_status != 'failed'){
                    $order->add_order_note(sprintf("%s PSE Payment Failed with message: '%s'. Confirmed by Webhook", $this->GATEWAY_NAME, $json->transaction->error_message));
                    $order->update_status('failed', "Failed payment");
                    $logger->info('webhook_handler Status => failed');
                }
            } else if($json->type == 'transaction.expired'){
                if ($order_status != 'cancelled'){
                    $order->update_status('cancelled', 'Payment is due.');
                    $order->add_order_note(sprintf("Payment expired. Confirmed by Webhook"));
                    $logger->info('webhook_handler Status => expired');
                }
            }
        }
        $logger->info('webhook_handler END');
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
        $this->images_dir = plugin_dir_url(__FILE__) . '/assets/images/';
        include_once('templates/payment.php');
    }

    protected function processOpenpayCharge() {
        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';
        $redirect_url = site_url('/', $protocol) . '?wc-api=pse_confirm';
        $amount = number_format((float) $this->order->get_total(), 2, '.', '');

        $charge_request = array(
            'method' => 'bank_account',
            'currency' => strtolower(get_woocommerce_currency()),
            'amount' => $amount,
            "description" => sprintf("Items: %s", $this->getProductsDetail()),
            'order_id' => $this->order->get_id(),
            'iva' => $this->iva,
            'redirect_url' => $redirect_url
        );

        $openpay_customer = $this->getOpenpayCustomer();

        $result_json = $this->createOpenpayCharge($openpay_customer, $charge_request);

        if ($result_json != false) {
            if ($this->is_sandbox) {
                $this->order->update_meta_data( '_openpay_customer_sandbox_id', $openpay_customer->id);
            }else{
                $this->order->update_meta_data('_openpay_customer_id', $openpay_customer->id);
            }
            $this->order->update_meta_data('_openpay_pse_redirect_url', $result_json->payment_method->url);

            return true;
        } else {
            return false;
        }
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

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
        } else {
            $this->order->add_order_note(sprintf("%s PSE Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));

            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
            } else {
                $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
            }
        }
    }

    public function createOpenpayCharge($customer, $charge_request) {
        Openpay::getInstance($this->merchant_id, $this->private_key, 'CO');
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-WOOCCO/v2";
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
            $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, 'CO');
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
        
        if ($this->hasAddress($this->order)) {
            $customer_data = $this->formatAddress($customer_data, $this->order);
        }      

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, 'CO');
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        $userAgent = "Openpay-WOOCCO/v2";
        Openpay::setUserAgent($userAgent);

        try {
            $customer = $openpay->customers->add($customerData);

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
            $customer_data['customer_address'] = array(
                'department' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'additional' => substr($order->get_billing_address_1(), 0, 200).' '.substr($order->get_billing_address_2(), 0, 50)
            );
        return $customer_data;
    }

    public function createWebhook($force_host_ssl, $merchant_id, $secret_key) {
        $logger = wc_get_logger();
        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';
        $url = site_url('/', $protocol).'wc-api/Openpay_PSE';

        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, 'CO');
        Openpay::setProductionMode($this->is_sandbox ? false : true);

        
        $webhooks = $openpay->webhooks->getList([]);
        $webhookCreated = $this->isWebhookCreated($webhooks, $url);
            if ($webhookCreated) {
                $logger->info('Webhook was already created with url => '. $webhookCreated->url);
                return $webhookCreated;
            }

            update_option( 'openpay_wkey', $this->generate_password(), '', 'yes' );
            update_option( 'openpay_wiv', $this->generate_password(), '', 'yes' );           

        $webhook_data = array(
            'url' => $url,
            'force_host_ssl' => $force_host_ssl,
            'user' => $this->encrypt('encode',$this->merchant_id,get_option('openpay_wkey'),get_option('openpay_wiv')),
            'password' => $this->encrypt('encode',$this->private_key,get_option('openpay_wkey'),get_option('openpay_wiv')),
            'event_types' => array(
                'verification',
                'charge.succeeded',
                'charge.created',
                'charge.cancelled',
                'charge.failed',
                'payout.created',
                'payout.succeeded',
                'payout.failed',
                'chargeback.created',
                'chargeback.rejected',
                'chargeback.accepted',
                'transaction.expired'
            )
        );

        $userAgent = "Openpay-WOOCCO/v2";
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

        $error = $e->getErrorCode() . '. ' . $msg;

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

    public function getOpenpayInstance() {
        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, 'CO');
        Openpay::setProductionMode($this->is_sandbox ? false : true);
        return $openpay;
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

    public function encrypt($action='encode',$string=false, $openpay_wkey, $openpay_wiv){
        $logger = wc_get_logger();
        $action = trim($action);
        $output = false;
        $encrypt_method = 'AES-256-CBC';
    
        $secret_key = hash('sha256',$openpay_wkey);
        $secret_iv = substr(hash('sha256',$openpay_wiv),0,16);
    
        if ( $action && ($action == 'encode' || $action == 'decode') && $string )
        {
            $string = trim(strval($string));
    
            if ( $action == 'encode' )
            {
                $output = openssl_encrypt($string, $encrypt_method, $secret_key, 0, $secret_iv);
            };
    
            if ( $action == 'decode' )
            {
                $output = openssl_decrypt($string, $encrypt_method, $secret_key, 0, $secret_iv);
            };
        };
        return $output;
    }

    public function generate_password($length = 20){
        $chars =  'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'.
                  '0123456789`-=~!@#$%^&*()_+,./<>?;:[]{}\|';
        $str = '';
        $max = strlen($chars) - 1;
      
        for ($i=0; $i < $length; $i++)
          $str .= $chars[random_int(0, $max)];
      
        return $str;
      }
    
    public function isWebhookCreated($webhooks, $uri){
        foreach ($webhooks as $webhook) {
            if($webhook->url === $uri){
                return $webhook;
            }
        }
        return null;
    }

}

function openpay_pse_add_gateway($methods) {
    array_push($methods, 'openpay_pse');
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'openpay_pse_add_gateway');