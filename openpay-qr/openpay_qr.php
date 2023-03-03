<?php
/**
 * Plugin Name: Openpay QR Plugin
 * Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
 * Description: Provides a QR payment method with Openpay for WooCommerce.
 * Version: 1.0.0
 * Author: Openpay
 * Author URI: http://www.openpay.mx
 * Developer: Openpay
 * Text Domain: openpay-qr
 *
 * WC requires at least: 3.0
 * WC tested up to: 6.8
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Openpay Docs: http://www.openpay.mx/docs/
 */

defined( 'ABSPATH' ) or exit;

// Comprobar que WooCommerce está activo
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function config_gateway_plugin_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=openpay-qr' ) . '">' . __( 'Configurar', 'openpay-qr' ) . '</a>'
    );

    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'config_gateway_plugin_links' );

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + Openpay QR gateway
 */
function oppenpay_qr_add_to_gateways( $gateways ) {
    $gateways[] = 'Openpay_QR';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'oppenpay_qr_add_to_gateways' );

/**
 * Provides a Payment Gateway;
 * We load it later to ensure WC is loaded first since we're extending it.
 */
function openpay_qr_init_your_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        include_once('openpay_qr_gateway.php');
    }
}
add_action('plugins_loaded', 'openpay_qr_init_your_gateway',11);