<?php

class OpenQR_error
{
    public static function showError(Exception $e)
    {
        global $woocommerce;
        /* 6001 el webhook ya existe */
        switch ($e->getCode()) {
            /* ERRORES GENERALES */
            case '1000':
            case '1004':
            case '1005':
                $msg = 'Servicio no disponible.';
                break;
            case '3012':
                $msg = 'Se requiere solicitar al banco autorización para realizar este pago.';
                break;
            default: /* Demás errores 400 */
                $msg = 'La petición no pudo ser procesada.';
                break;
        }
        $error = 'ERROR ' . $e->getErrorCode() . '. ' . $msg;
        if (function_exists('wc_add_notice')) {
            wc_add_notice($error, 'error');
        } else {
            $woocommerce->add_error(__('Payment error:', 'woothemes') . $error);
        }
    }
}