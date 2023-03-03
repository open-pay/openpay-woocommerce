<?php
class OpenQR_Currencies
{
    public static function validateCurrency($currencies):bool {
        return in_array(get_woocommerce_currency(), $currencies);
    }

    public static function getCurrencies($countryCode):array {
        $currencies = ['USD'];
        $countryCode = strtoupper($countryCode);
        switch ($countryCode) {
            case 'CO':
                $currencies[] = 'COP';
                return $currencies;
            case 'PE':
                $currencies[] = 'PEN';
                return $currencies;
            default:
                break;
        }
    }

    public static function getCurrencyMessageError($countryName, $currencies):string {
        //$format = 'Openpay QR Plugin for %s is only available for %s currencies.';
        $format = 'El plugin de Openpay QR para %s está solo disponible para los tipos de moneda %s .';
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