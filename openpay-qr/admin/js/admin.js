jQuery(document).ready(function () {

    if(jQuery("#woocommerce_openpay-qr_sandbox").length) {
        is_sandbox();
        jQuery("#woocommerce_openpay-qr_sandbox").on("change", function (e) {
            is_sandbox();
        });
    }

    function is_sandbox(){
        var checked = jQuery("#woocommerce_openpay-qr_sandbox").is(':checked');
        if (checked == 0) {
            /*Show Production fields*/
            jQuery("#woocommerce_openpay-qr_production_merchant_id").closest("tr").show();
            jQuery("#woocommerce_openpay-qr_production_SK").closest("tr").show();
            jQuery("#woocommerce_openpay-qr_production_PK").closest("tr").show();
            /*Hide Sandbox fields*/
            jQuery("#woocommerce_openpay-qr_sandbox_merchant_id").closest("tr").hide();
            jQuery("#woocommerce_openpay-qr_sandbox_SK").closest("tr").hide();
            jQuery("#woocommerce_openpay-qr_sandbox_PK").closest("tr").hide();
        } else {
            /*show sandbox fields*/
            jQuery("#woocommerce_openpay-qr_sandbox_merchant_id").closest("tr").show();
            jQuery("#woocommerce_openpay-qr_sandbox_SK").closest("tr").show();
            jQuery("#woocommerce_openpay-qr_sandbox_PK").closest("tr").show();
            /*Hide production fields*/
            jQuery("#woocommerce_openpay-qr_production_merchant_id").closest("tr").hide();
            jQuery("#woocommerce_openpay-qr_production_SK").closest("tr").hide();
            jQuery("#woocommerce_openpay-qr_production_PK").closest("tr").hide();
        }
    }

});