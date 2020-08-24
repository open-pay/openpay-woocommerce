jQuery(document).ready(function () {
    var country = jQuery('#woocommerce_openpay_cards_country').val();
    console.log('admin.js', country);
    showOrHideElements(country);

    function showOrHideElements(country) {        
        if (country === 'CO') {            
            jQuery("#woocommerce_openpay_cards_iva").closest("tr").show();
            jQuery("#woocommerce_openpay_cards_installments").closest("tr").show();
            
            jQuery("#woocommerce_openpay_cards_charge_type").closest("tr").hide();
            jQuery("#woocommerce_openpay_cards_capture").closest("tr").hide();
            jQuery("#woocommerce_openpay_cards_use_card_points").closest("tr").hide();
            jQuery("#woocommerce_openpay_cards_msi").closest("tr").hide();      
            jQuery("#woocommerce_openpay_cards_minimum_amount_interest_free").closest("tr").hide();         
        } else if (country === 'MX') {            
            jQuery("#woocommerce_openpay_cards_iva").closest("tr").hide();  
            jQuery("#woocommerce_openpay_cards_installments").closest("tr").hide();
                          
            jQuery("#woocommerce_openpay_cards_charge_type").closest("tr").show();
            jQuery("#woocommerce_openpay_cards_capture").closest("tr").show();
            jQuery("#woocommerce_openpay_cards_use_card_points").closest("tr").show();
            jQuery("#woocommerce_openpay_cards_msi").closest("tr").show();         
            jQuery("#woocommerce_openpay_cards_minimum_amount_interest_free").closest("tr").show();                                 
        }
    }

    jQuery('#woocommerce_openpay_cards_country').change(function () {
        var country = jQuery(this).val();
        console.log('woocommerce_openpay_cards_country', country);        

        showOrHideElements(country)
    });

    if(jQuery("#woocommerce_openpay_cards_sandbox").length){
        is_sandbox();

        jQuery("#woocommerce_openpay_cards_sandbox").on("change", function(e){
            is_sandbox();
        });
    }

    function is_sandbox(){
        sandbox = jQuery("#woocommerce_openpay_cards_sandbox").is(':checked');
        if(sandbox){
            jQuery("input[name*='live']").parent().parent().parent().hide();
            jQuery("input[name*='test']").parent().parent().parent().show();
        }else{
            jQuery("input[name*='test']").parent().parent().parent().hide();
            jQuery("input[name*='live']").parent().parent().parent().show();
        }
    }
});