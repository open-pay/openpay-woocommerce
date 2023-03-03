<?php
class OpenQR_OpenpayInstance
{
    public static function getOpenpayInstance($merchant_id,$sk,$country,$is_sandbox) {
        $openpay = Openpay::getInstance($merchant_id, $sk, $country );
        Openpay::setProductionMode(!$is_sandbox);
        $userAgent = "OpenpayQR-WC".$country."/v1";
        Openpay::setUserAgent($userAgent);
        return $openpay;
    }

}