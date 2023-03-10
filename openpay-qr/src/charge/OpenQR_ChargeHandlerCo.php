<?php

include_once("OpenQR_ChargeHandler.php");

class OpenQR_ChargeHandlerCo extends OpenQR_ChargeHandler
{
    public function __construct($order_id){
        parent::__construct($order_id);
    }

    protected function formatAddress($customer_data, $order){
        $customer_data['customer_address'] = array(
            'department' => $order->get_billing_state(),
            'city' => $order->get_billing_city(),
            'additional' => substr($order->get_billing_address_1(), 0, 200) . ' ' . substr($order->get_billing_address_2(), 0, 50)
        );
        return $customer_data;
    }
}