<?php

/**
 * Plugin Name: Openpay CoDi® Plugin
 * Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
 * Description: Plugin TEST
 * Version: 1.1.0
 * Author: Openpay
 * Author URI: http://www.openpay.mx
 * Developer: Openpay
 * Text Domain: openpay-codi
 *
 * WC requires at least: 3.0
 * WC tested up to: 5.1
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Openpay Docs: http://www.openpay.mx/docs/
 */

function openpay_codi_init_your_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        include_once('openpay_codi_gateway.php');
    }
}

add_action('plugins_loaded', 'openpay_codi_init_your_gateway', 0);
