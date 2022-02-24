<?php

class Utils {
    public static function getCurrencies($countryCode) {
        $currencies = ['USD'];
        $countryCode = strtoupper($countryCode);
        switch ($countryCode) {
            case 'MX':
                $currencies[] = 'MXN';
                return $currencies;
            case 'CO':
                $currencies[] = 'COP';
                return $currencies;
            case 'PE':
                $currencies[] = 'PEN';
                return $currencies;
            case 'AR':
                $currencies[] = 'ARS';
                return $currencies;
            default:
                break;
        }
    }

    public static function getUrlScripts($country){
        $scripts = [
            'openpay_js' => '',
            'openpay_fraud_js' => ''
        ];
        $routeBaseOpenpayJs = '%s/openpay.v1.min.js';
        $routeBaseOpenpayFraud = '%s/openpay-data.v1.min.js';
 
        switch ($country) {
            case 'MX':
                $baseUrl = 'https://openpay.s3.amazonaws.com';
                $scripts['openpay_js'] = sprintf($routeBaseOpenpayJs, $baseUrl);
                $scripts['openpay_fraud_js'] = sprintf($routeBaseOpenpayFraud, $baseUrl);
                return $scripts;
            case 'CO':
                $baseUrl = 'https://resources.openpay.co';
                $scripts['openpay_js'] = sprintf($routeBaseOpenpayJs, $baseUrl);
                $scripts['openpay_fraud_js'] = sprintf($routeBaseOpenpayFraud, $baseUrl);
                return $scripts;
            case 'PE':
                $baseUrl = 'https://js.openpay.pe';
                $scripts['openpay_js'] = sprintf($routeBaseOpenpayJs, $baseUrl);
                $scripts['openpay_fraud_js'] = sprintf($routeBaseOpenpayFraud, $baseUrl);
                return $scripts;
            default:
                break;
        }
    }

    public static function getCountryName($countryCode) {
        switch ($countryCode){
            case 'MX':
                return 'Mexico';
            case 'CO':
                return 'Colombia';
            case 'PE':
                return 'Peru';
            case 'AR':
                return 'Argentina';
            default:
                break;
        }
    }

    public static function getMessageError($countryName, $currencies) {
        $format = 'Openpay Cards Plugin %s is only available for %s currencies.';
        $currenciesString = '';
        $numberCurrencies = count($currencies) - 1;
        $index = 0;
        foreach ($currencies as $currency) {
            if($index == $numberCurrencies) {
                $currenciesString = $currenciesString . $currency;
                break;
            }
            $currenciesString = $currenciesString . $currency.', ';
            $index++;
        }
        return sprintf($format, $countryName, $currenciesString);
    }
}