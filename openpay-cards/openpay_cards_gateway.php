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

class Openpay_Cards extends WC_Payment_Gateway
{

    protected $GATEWAY_NAME = "Openpay Cards";
    protected $is_sandbox = true;
    protected $order = null;
    protected $transaction_id = null;
    protected $transactionErrorMessage = null;
    protected $currencies = array('MXN', 'USD');

    public function __construct() {
        $this->id = 'openpay_cards';
        $this->method_title = __('Openpay Cards', 'openpay_cards');
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = 'Pago con tarjeta de crédito o débito';
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

        if ($this->is_sandbox) {
            $this->description .= __('SANDBOX MODE ENABLED. In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date.', 'openpay-woosubscriptions');
        }

        // tell WooCommerce to save options
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
        add_action('admin_notices', array(&$this, 'perform_ssl_check'));

        if (!$this->validateCurrency()) {
            $this->enabled = false;
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
                'title' => __('Enable/Disable', 'woothemes'),
                'label' => __('Enable Openpay Cards', 'woothemes'),
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

    public function admin_options() {
        include_once('templates/admin.php');
    }

    public function payment_fields() {
        $this->images_dir = WP_PLUGIN_URL."/".plugin_basename(dirname(__FILE__)).'/assets/images/';
        include_once('templates/payment.php');
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

        wp_enqueue_script('openpay_js', 'https://openpay.s3.amazonaws.com/openpay.v1.min.js', '', '', true);
        wp_enqueue_script('openpay_fraud_js', 'https://openpay.s3.amazonaws.com/openpay-data.v1.min.js', '', '', true);
        wp_enqueue_script('payment', WP_PLUGIN_URL."/".plugin_basename(dirname(__FILE__)).'/assets/js/jquery.payment.js', '', '', true);
        wp_enqueue_script('openpay', WP_PLUGIN_URL."/".plugin_basename(dirname(__FILE__)).'/assets/js/openpay.js', '', '1.0', true);


        $openpay_params = array(
            'merchant_id' => $this->merchant_id,
            'public_key' => $this->publishable_key,
            'sandbox_mode' => $this->is_sandbox
        );

        // If we're on the pay page we need to pass openpay.js the address of the order.
        if (is_checkout_pay_page() && isset($_GET['order']) && isset($_GET['order_id'])) {
            $order_key = urldecode($_GET['order']);
            $order_id = absint($_GET['order_id']);
            $order = new WC_Order($order_id);

            if ($order->id == $order_id && $order->order_key == $order_key) {
                $openpay_params['billing_first_name'] = $order->billing_first_name;
                $openpay_params['billing_last_name'] = $order->billing_last_name;
                $openpay_params['billing_address_1'] = $order->billing_address_1;
                $openpay_params['billing_address_2'] = $order->billing_address_2;
                $openpay_params['billing_state'] = $order->billing_state;
                $openpay_params['billing_city'] = $order->billing_city;
                $openpay_params['billing_postcode'] = $order->billing_postcode;
                $openpay_params['billing_country'] = $order->billing_country;
            }
        }

        wp_localize_script('openpay', 'wc_openpay_params', $openpay_params);
    }

    protected function processOpenpayCharge($device_session_id, $openpay_token) {

        WC()->session->__unset('pdf_url');

        // Get the credit card details submitted by the form
        $charge_request = array(
            "method" => "card",
            "amount" => (float) $this->order->get_total(),
            "currency" => strtolower(get_woocommerce_currency()),
            "source_id" => $openpay_token,
            "device_session_id" => $device_session_id,
            "description" => sprintf("Charge for %s", $this->order->billing_email),
            "order_id" => $this->order->id
        );

        $openpay_customer = $this->getOpenpayCustomer();

        $result_json = $this->createOpenpayCharge($openpay_customer, $charge_request);

        if ($result_json != false) {
            $this->transaction_id = $result_json->id;
            //Save data for the ORDER
            update_post_meta($this->order->id, '_openpay_customer_id', $openpay_customer->id);
            update_post_meta($this->order->id, '_transaction_id', $result_json->id);
            update_post_meta($this->order->id, '_key', $this->private_key);

            return true;
        } else {
            return false;
        }
    }

    public function process_payment($order_id) {
        global $woocommerce;
        $device_session_id = isset($_POST['device_session_id']) ? wc_clean($_POST['device_session_id']) : '';
        $openpay_token = $_POST['openpay_token'];
        
        $this->order = new WC_Order($order_id);
        if ($this->processOpenpayCharge($device_session_id, $openpay_token)) {
            /** WooCommerce will use either Completed or Processing status and handle stock. */
            $this->order->payment_complete();
            $woocommerce->cart->empty_cart();
            $this->order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", $this->GATEWAY_NAME, $this->transaction_id));
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($this->order)
            );
        } else {
            $this->order->add_order_note(sprintf("%s Credit Card Payment Failed with message: '%s'", $this->GATEWAY_NAME, $this->transactionErrorMessage));
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
            $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true);
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

    public function error($e) {
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
        $error = 'ERROR '.$e->getErrorCode().'. '.$msg;

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

}

function openpay_cards_add_creditcard_gateway($methods) {
    array_push($methods, 'openpay_cards');
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'openpay_cards_add_creditcard_gateway');

