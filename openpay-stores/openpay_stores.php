<?php
/*
  Plugin Name: Openpay Stores Plugin
  Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
  Description: Provides a cash payment gateway through Openpay for WooCommerce.
  Version: 1.0
  Author: Federico Balderas
  Author URI: http://foograde.com

  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html

  Openpay Docs: http://www.openpay.mx/docs/
 */


function openpay_stores_init_your_gateway() 
{
    if (class_exists('WC_Payment_Gateway'))
    {
        include_once('openpay_stores_gateway.php');
    }
}

add_action('plugins_loaded', 'openpay_stores_init_your_gateway', 0);
