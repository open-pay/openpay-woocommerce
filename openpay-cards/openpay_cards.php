<?php
/*
  Plugin Name: Openpay Cards Plugin
  Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
  Description: Provides a Credit Card Payment Gateway through Openpay for WooCommerece. Compatible with WooCommerce 3.0.8 and Wordpress 4.8.
  Version: 1.3.0
  Author: Federico Balderas
  Author URI: http://www.openpay.mx

  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html

  Openpay Docs: http://www.openpay.mx/docs/
 */


function openpay_cards_init_your_gateway() 
{
    if (class_exists('WC_Payment_Gateway'))
    {
        include_once('openpay_cards_gateway.php');
    }
}

add_action('plugins_loaded', 'openpay_cards_init_your_gateway', 0);
