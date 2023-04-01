<?php

include_once("OpenQR_ChargeHandler.php");

class OpenQR_ChargeHandlerPe extends OpenQR_ChargeHandler
{
    public function __construct($order_id){
        parent::__construct($order_id);
    }

    protected function formatAddress($customer_data, $order){
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
}