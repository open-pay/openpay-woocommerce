<?php

include_once("OpenQR_ChargeHandler.php");

class OpenQR_ChargeHandlerPe extends OpenQR_ChargeHandler
{
    public function __construct($order){
        parent::__construct($order);
    }
}