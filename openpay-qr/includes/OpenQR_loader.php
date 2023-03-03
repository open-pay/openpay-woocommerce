<?php

if (!class_exists('Openpay')) {
    require_once("lib/openpay/Openpay.php");
}

if(!class_exists('OpenQR_ConfigCountries')) {
    require_once(dirname(__FILE__) . "/../src/gateway-config/OpenQR_ConfigCountries.php");
}

if(!class_exists('OpenQR_ConfigCredentials')) {
    require_once(dirname(__FILE__) . "/../src/gateway-config/OpenQR_ConfigCredentials.php");
}

if(!class_exists('OpenQR_ConfigForm')) {
    require_once(dirname(__FILE__) . "/../src/gateway-config/OpenQR_ConfigForm.php");
}

if(!class_exists('OpenQR_Currencies')) {
    require_once(dirname(__FILE__) . "/../src/gateway-config/OpenQR_Currencies.php");
}

if(!class_exists('OpenQR_OpenpayInstance')) {
    require_once(dirname(__FILE__) . "/OpenQR_OpenpayInstance.php");
}

if(!class_exists('OpenQR_ChargeHandlerCo')) {
    require_once(dirname(__FILE__) . "/../src/charge/OpenQR_ChargeHandlerCo.php");
}

if(!class_exists('OpenQR_ChargeHandlerPe')) {
    require_once(dirname(__FILE__) . "/../src/charge/OpenQR_ChargeHandlerPe.php");
}
