<?php
class OpenQR_ConfigCredentials{

    public static function validateCredentials($post_data){
        $logger = wc_get_logger();
        $plugin_id = "openpay-qr";
        $mode = "production";
        $is_sandbox = 0;

        if (isset($post_data['woocommerce_'.$plugin_id.'_sandbox'])){
            $mode = isset($post_data['woocommerce_'.$plugin_id.'_sandbox']) ? 'sandbox':'production';
            $is_sandbox = $post_data['woocommerce_'.$plugin_id.'_sandbox'];
        }

        $merchant_id = $post_data['woocommerce_'.$plugin_id.'_'.$mode.'_merchant_id'];
        $SK = $post_data['woocommerce_'.$plugin_id.'_'.$mode.'_SK'];
        $country = $post_data['woocommerce_'.$plugin_id.'_country'];

        $settings = new WC_Admin_Settings();
        if($merchant_id == '' || $SK == ''){
            $settings->add_error('You need to enter "'.$mode.'" credentials if you want to use this plugin in this mode.');
            return;
        }

        try{
            $logger->info($merchant_id . " - " . $SK . " - " . $country . " - " .$is_sandbox);
            $instance = OpenQR_OpenpayInstance::getOpenpayInstance($merchant_id,$SK,$country,$is_sandbox);
            $instance->webhooks->getList(['limit'=>1]);
        } catch (Exception $e) {

            $logger->error($e->getMessage());
            $settings->add_error($e->getMessage());
            return;
        }
    }

}