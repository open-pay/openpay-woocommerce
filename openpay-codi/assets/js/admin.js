jQuery(document).ready(function () {
    console.log('admin.js');
                              
    showOrHideElements();

    jQuery("#woocommerce_openpay_codi_codi_expiration").on("change", function(e){
        showOrHideElements(codi_mode)
    });
    
    function showOrHideElements() {
        codi_mode = jQuery('#woocommerce_openpay_codi_codi_expiration').is(':checked');
        console.log(codi_mode); 
        if(codi_mode) {
            jQuery("#woocommerce_openpay_codi_expiration_time").closest("tr").show();
            jQuery("#woocommerce_openpay_codi_unit_time").closest("tr").show();
        }else {
            jQuery("#woocommerce_openpay_codi_expiration_time").closest("tr").hide();
            jQuery("#woocommerce_openpay_codi_unit_time").closest("tr").hide();
        }
    }

    if(jQuery("#woocommerce_openpay_codi_sandbox").length){
        is_sandbox();

        jQuery("#woocommerce_openpay_codi_sandbox").on("change", function(e){
            is_sandbox();
        });
    }

    function is_sandbox(){
        sandbox = jQuery("#woocommerce_openpay_codi_sandbox").is(':checked');
        if(sandbox){
            jQuery("input[name*='live']").parent().parent().parent().hide();
            jQuery("input[name*='test']").parent().parent().parent().show();
        }else{
            jQuery("input[name*='test']").parent().parent().parent().hide();
            jQuery("input[name*='live']").parent().parent().parent().show();
        }
    }
});