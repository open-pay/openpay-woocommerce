<?php
add_action( "woocommerce_thankyou", "openqr_thankyou", 20 );
add_action('wp_ajax_nopriv_fetch_order_status', 'openqr_fetch_order_status');
add_action('wp_ajax_fetch_order_status','openqr_fetch_order_status');
add_action('wp_enqueue_scripts', 'openqr_thankyou');


function openqr_fetch_order_status(){
    $order = wc_get_order( $_REQUEST['order_id'] );
    $order_data = $order->get_data();
    echo $order_data['status'];
    die();
}

function openqr_thankyou($order_id){
    $order = wc_get_order( $order_id );
    if ( $order instanceof WC_Order ) {
        $order_id = $order->get_id();

        wp_enqueue_script('openqr_hook_thankyou', plugin_dir_url(__FILE__) . '/scripts/openqr_hook_thankyou.js');
        wp_localize_script('openqr_hook_thankyou', "params", array(
            "site_url" => site_url(),
            "order_number" => $order->get_order_number()
        ));
    }
}