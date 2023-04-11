<?php


class OpenQR_WebhookHandler
{
    private $logger;
    private $credentials;
    private $openpay_instance;
    private $openpay_transaction;
    private $openpay_customer;
    private $webhook_body;

    /**
     * OpenQR_Webhook constructor.
     */
    public function __construct()
    {
        $this->logger = wc_get_logger();
        $this->credentials = OpenQR_ConfigCredentials::getActualCredentials();
        $this->openpay_instance = OpenQR_OpenpayInstance::getOpenpayInstance(
            $this->credentials['merchant_id'],
            $this->credentials['SK'],
            $this->credentials['country'],
            $this->credentials['is_sandbox']
        );
        $this->getWebhookBody();
        $this->getOpenpayTransaction();
        $this->updateOrderStatus();
    }

    public function getWebhookBody(){
        header('HTTP/1.1 200 OK');
        $obj = file_get_contents('php://input');
        $this->webhook_body = json_decode($obj);
    }

    public function getOpenpayTransaction(){
        if($this->webhook_body->transaction->method == 'qr') {
            if (isset($this->webhook_body->transaction->customer_id)) {
                $this->openpay_customer = $this->openpay_instance->customers->get($this->webhook_body->transaction->customer_id);
                $this->logger->info("OpenQR_WebhookHandler.checkTransactionData.customer - " . json_encode($this->openpay_customer));
                $this->openpay_transaction = $this->openpay_customer->charges->get($this->webhook_body->transaction->id);
                $this->logger->info("OpenQR_WebhookHandler.checkTransactionData.charge - " . json_encode($this->openpay_transaction));
            } else {
                $this->openpay_transaction = $this->openpay_instance->charges->get($this->webhook_body->transaction->id);
                $this->logger->info("OpenQR_WebhookHandler.checkTransactionData.charge.else.id - " . json_encode($this->openpay_transaction->id));
                $this->logger->info("OpenQR_WebhookHandler.checkTransactionData.charge.else.status - " . json_encode($this->openpay_transaction->status));
            }
        }
    }

    public function updateOrderStatus(){
        $order_id = $this->webhook_body->transaction->order_id;
        $this->logger->info("OpenQR_WebhookHandler.checkTransactionData.order_id - " . json_encode($order_id));
        $order = new WC_Order($order_id);
        /*
             * (d444) Activar la validación de status en Openpay antes de liberar
             */
        if ($this->webhook_body->type == 'charge.succeeded' /*&& $this->openpay_transaction->status == 'completed'*/) {
            $this->logger->info("OpenQR_WebhookHandler.checkTransactionData.note");
            $payment_date = date("Y-m-d", $this->webhook_body->event_date);
            update_post_meta($order->get_id(), 'openpay_payment_date', $payment_date);
            $order->payment_complete();
            $order->add_order_note(sprintf("Payment completed."));
            /*
             * (d444) Activar la validación de status en Openpay antes de liberar
             */
        }else if($this->webhook_body->type == 'transaction.expired' /*&& $this->openpay_transaction->status == 'cancelled'*/){
            $order->update_status('cancelled', 'Order cancelled. Transaction has expired.');
        }
        else if($this->webhook_body->type == 'transaction.failed' /*&& $this->openpay_transaction->status == 'cancelled'*/){
            $order->update_status('failed', 'Transaction has failed.');
        }
    }

}