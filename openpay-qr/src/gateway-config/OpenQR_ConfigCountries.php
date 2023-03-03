<?php


class OpenQR_ConfigCountries
{

    public static function getCountryName($countryCode) {
        switch ($countryCode){
            case 'CO':
                return 'Colombia';
            case 'PE':
                return 'Perú';
            default:
                break;
        }
    }

}