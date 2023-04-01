<?php
include_once(dirname(__FILE__) . '/entities/OpenQR_Charge.php');
class OpenQR_ChargeHandler
{
    protected $logger;
    protected $OpenQR_gateway;
    protected $credentials;
    protected $openpay_instance;
    protected $charge;
    protected $order;

    public function __construct($order_id){
        $this->logger = wc_get_logger();
        $this->order = wc_get_order( $order_id );
        $this->credentials = OpenQR_ConfigCredentials::getActualCredentials();
        $this->openpay_instance = OpenQR_OpenpayInstance::getOpenpayInstance(
            $this->credentials['merchant_id'],
            $this->credentials['SK'],
            $this->credentials['country'],
            $this->credentials['is_sandbox']
        );

        $this->buildCharge();

        if (is_user_logged_in()) {
            $customer = $this->getOpenpayCustomer();
            $customer = $customer ?: $this->createOpenpayCustomer();
        }
        $this->logger->info("OpenQR_ChargeHandler.construct.customer - " . json_encode($customer));
        $charge_request_response = $this->sendOpenpayChargeRequest($customer, $this->charge);
        $this->saveChargeData($charge_request_response);
    }

    /*################################################################################################################*/
    /*###########################                             CHARGE                        ##########################*/
    /*################################################################################################################*/
    protected function buildCharge(){
        $this->charge = new OpenQR_Charge();
        $this->charge->setAmount(number_format((float)$this->order->get_total(), 2, '.', ''));
        $this->charge->setCurrency(strtolower(get_woocommerce_currency()));
        $this->charge->setDescription( $this->buildDescription_WithProductsDetails() );
        $this->charge->setOrderId($this->order->get_id());
        $this->charge->setDueDate($this->buildDueDate());

        if (!is_user_logged_in()) {
            $this->charge->setCustomer($this->buildCustomer());
        }

        $this->logger->info( json_encode((array)$this->charge) );

    }

    protected function buildDescription_WithProductsDetails() {
        $products = [];
        foreach( $this->order->get_items() as $item_product ){
            $product = $item_product->get_product();
            $products[] = $product->get_name();
        }
        $products = substr(implode(', ', $products), 0, 249);
        return sprintf("Items: %s", $products);
    }

    protected function buildDueDate(){
        $this->OpenQR_gateway = WC()->payment_gateways->payment_gateways()['openpay-qr'];
        $default_expiration_time = 2;
        //$expiration_time = $this->OpenQR_gateway->settings['due_date'];
        $this->logger->info("OpenQR_ChargeHandler.buildDueDate.current_date - " . date('Y-m-d\TH:i:s'));
        return date('Y-m-d\TH:i:s', strtotime('+ '.$default_expiration_time.' days'));
    }

    protected function sendOpenpayChargeRequest($customer, $charge_request) {
        try {
            if($customer){
                $this->logger->info("OpenQR_ChargeHandler.sendOpenpayChargeRequest - USER LOGGED IN");
                $charge_request_response = $customer->charges->create((array)$charge_request);
            }else{
                $this->logger->info("OpenQR_ChargeHandler.sendOpenpayChargeRequest - USER NOT LOGGED IN");
                $charge_request_response = $this->openpay_instance->charges->create((array)$charge_request);
            }
            $this->logger->info("OpenQR_ChargeHandler.sendOpenpayChargeRequest.charge_request_response - " . json_encode((array)$charge_request_response));
            return $charge_request_response;
        } catch (Exception $e) {
            $this->logger->error("OpenQR_ChargeHandler.sendOpenpayChargeRequest.error - " . $e->getMessage());
            OpenQR_Error::showError($e);
            return false;
        }
    }

    protected function saveChargeData($charge_response){
        if ($charge_response->payment_method && $charge_response->payment_method->type == 'qr') {
            update_post_meta($this->order->get_id(), '_openqr_due_date', $charge_response->payment_method->due_date);
            update_post_meta($this->order->get_id(), '_openqr_barcode_base64', $charge_response->payment_method->barcode_base64);
        }
    }

    /*################################################################################################################*/
    /*###########################                            CUSTOMER                       ##########################*/
    /*################################################################################################################*/
    protected function getOpenpayCustomer() {
        $customer_id = null;
        if (is_user_logged_in()) {
            if ($this->credentials['is_sandbox']) {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', true);
            } else {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_id', true);
            }
        }

        if ($this->isNullOrEmptyString($customer_id)) {
            // Return false if customer is not register previously.
            return false;
        }

        try {
            // Consulta el objeto customer creado con anterioridad.
            return $this->openpay_instance->customers->get($customer_id);
        } catch (Exception $e) {
            $this->logger->error("OpenQR_ChargeHandler.getOpenpayCustomer.error - " . $e->getMessage());
            OpenQR_Error::showError($e);
            return false;
        }
    }

    protected function createOpenpayCustomer() {
        $customer_data = $this->buildCustomer();
        try {
            $this->logger->info("OpenQR_ChargeHandler.createOpenpayCustomer.customer_data - " . json_encode($customer_data));
            $customer = $this->openpay_instance->customers->add($customer_data);

            if (is_user_logged_in()) {
                if ($this->credentials['is_sandbox']) {
                    update_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', $customer->id);
                } else {
                    update_user_meta(get_current_user_id(), '_openpay_customer_id', $customer->id);
                }
            }

            return $customer;
        } catch (Exception $e) {
            OpenQR_Error::showError($e);
            return false;
        }
    }

    protected function buildCustomer(){
        try {
        $customer_data = array(
            'name' => $this->order->get_billing_first_name(),
            'last_name' => $this->order->get_billing_last_name(),
            'email' => $this->order->get_billing_email(),
            'requires_account' => false,
            'phone_number' => $this->order->get_billing_phone()
        );
        $this->logger->info("1-OpenQR_ChargeHandler.buildCustomer.customer_data - " . json_encode($customer_data));

        if ($this->hasAddress($this->order)) {
            $customer_data = $this->formatAddress($customer_data, $this->order);
        }
        return $customer_data;
        } catch (Exception $e) {
            OpenQR_Error::showError($e);
            return false;
        }
    }

    protected function isNullOrEmptyString($string):bool {
        return (!isset($string) || trim($string) === '');
    }

    protected function hasAddress($order):bool {
        if($order->get_billing_address_1() && $order->get_billing_state() && $order->get_billing_postcode() && $order->get_billing_country() && $order->get_billing_city()) {
            return true;
        }
        return false;
    }


}