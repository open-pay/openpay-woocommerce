<?php

/**
 * Plugin Name: Openpay SPEI Plugin
 * Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
 * Description: Provides an electronic funds transfer payment method with Openpay for WooCommerce.
 * Version: 1.10.1
 * Author: Openpay
 * Author URI: http://www.openpay.mx
 * Developer: Openpay
 * Text Domain: openpay-spei
 *
 * WC requires at least: 3.0
 * WC tested up to: 8.5.2
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Openpay Docs: http://www.openpay.mx/docs/
 */

function openpay_spei_init_your_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        include_once('openpay_spei_gateway.php');
    }
}

add_action('plugins_loaded', 'openpay_spei_init_your_gateway', 0);
add_filter('woocommerce_email_attachments', 'attach_spei_payment_receipt', 10, 3);
add_action('admin_enqueue_scripts', 'openpay_spei_load_scripts');
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

function attach_spei_payment_receipt($attachments, $email_id, $order) {
    // Avoiding errors and problems
    if (!is_a($order, 'WC_Order') || !isset($email_id)) {
        return $attachments;
    }
    
    $logger = wc_get_logger();    
    $upload_dir = wp_upload_dir();
    $order_id = $order->get_id();

    // Only for "customer_on_hold_order" email notification (for customers)
    if ($email_id == 'customer_on_hold_order' && $order->get_payment_method() == 'openpay_spei') {
        $pdf_url = $order->get_meta('_pdf_url');

        $logger->info('get_shipping_postcode: ' . $order->get_shipping_postcode());
        $logger->info('_openpay_customer_id: ' . $order->get_meta('_openpay_customer_id'));
        $logger->info('_pdf_url: ' . $pdf_url);
        $logger->info('email_id: ' . $email_id);
        $logger->info('order_id: ' . $order_id);
        $logger->info('basedir: ' . $upload_dir['basedir']);

        $pdf_path = $upload_dir['basedir'] . '/instrucciones_pago_' . $order_id . '.pdf';
        file_put_contents($pdf_path, file_get_contents($pdf_url));
        $attachments[] = $pdf_path;
    }

    return $attachments;
}
function openpay_spei_load_scripts() {
    wp_enqueue_script('speiScript', plugin_dir_url( __FILE__ ).'assets/js/speiScript.js');
}