jQuery(document).ready(function () {
    var country = jQuery('#woocommerce_openpay_stores_country').val();
    showOrHideElements(country);

    function showOrHideElements(country) {     
        if (country === 'CO') {
            jQuery("#woocommerce_openpay_stores_show_map").closest("tr").hide();
            jQuery("#woocommerce_openpay_stores_iva").closest("tr").show();
        } else if (country === 'MX') {
            jQuery("#woocommerce_openpay_stores_show_map").closest("tr").show();
            jQuery("#woocommerce_openpay_stores_iva").closest("tr").hide();
        } else if (country === 'PE') {
            jQuery("#woocommerce_openpay_stores_iva").closest("tr").hide();
            jQuery("#woocommerce_openpay_stores_show_map").closest("tr").hide();
        }
    }

    jQuery('#woocommerce_openpay_stores_country').change(function () {
        var country = jQuery(this).val();    

        showOrHideElements(country)
    });

    if(jQuery("#woocommerce_openpay_stores_sandbox").length){
        is_sandbox();

        jQuery("#woocommerce_openpay_stores_sandbox").on("change", function(e){
            is_sandbox();
        });
    }

    function is_sandbox(){
        jQuery(".form-table input[type=text]").each(function(e){
            var sandbox = jQuery("#woocommerce_openpay_stores_sandbox").is(':checked');
            var inputField = jQuery(this).attr("name").search("test");
            if(sandbox && inputField != -1) {
                jQuery(this).parent().parent().parent().show();
            }else if(!sandbox && inputField == -1){
                jQuery(this).parent().parent().parent().show();
            }else{
                jQuery(this).parent().parent().parent().hide();
            }
        });
    }
    
});