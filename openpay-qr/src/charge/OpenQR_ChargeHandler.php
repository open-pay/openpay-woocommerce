<?php
include_once(dirname(__FILE__) . '/entities/OpenQR_Charge.php');
include_once(dirname(__FILE__) . '/entities/OpenQR_Customer.php');
class OpenQR_ChargeHandler
{
    protected $logger;
    protected $charge;
    protected $order;

    public function __construct($order_id){
        $this->logger = wc_get_logger();
        $this->charge = new OpenQR_Charge();
        $this->order = wc_get_order( $order_id );

        $this->charge->setAmount(number_format((float)$this->order->get_total(), 2, '.', ''));
        $this->charge->setCurrency(strtolower(get_woocommerce_currency()));
        $this->charge->setDescription( $this->buildDescription_WithProductsDetails() );
        $this->charge->setOrderId($this->order->get_id());
        $this->charge->setValidityDate($this->buildValidityDate_WithExpirationTime());
        $this->charge->setCustomer($this->buildCustomer_WithoutWCAccount());
        $this->logger->info( '$this->order' . json_encode( $this->order)  );
        $this->logger->info(json_encode( $this->charge) );
    }

    private function buildDescription_WithProductsDetails() {
        $products = [];
        foreach( $this->order->get_items() as $item_product ){
            $product = $item_product->get_product();
            $products[] = $product->get_name();
        }
        $products = substr(implode(', ', $products), 0, 249);
        return sprintf("Items: %s", $products);
    }

    private function buildValidityDate_WithExpirationTime(){
        $OpenQR_gateway = WC()->payment_gateways->payment_gateways()['openpay-qr'];
        $expiration_time = $OpenQR_gateway->settings['expiration_time'];
        return date('Y-m-d', strtotime('+ '.$expiration_time.' days'));
    }

    Private function buildCustomer_WithoutWCAccount(){
        $customer = new OpenQR_Customer();
        $customer->setName('Daniel');
    }
}