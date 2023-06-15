jQuery(document).ready(function () {
    jQuery('#woocommerce_openpay_cards_merchant_classification').closest("tr").hide();

    var country = jQuery('#woocommerce_openpay_cards_country').val();
    var merchantOrigin = jQuery('#woocommerce_openpay_cards_merchant_classification').val();
    showOrHideElements(country, merchantOrigin);

    function showOrHideElements(country, merchantOrigin) {
        if (country === 'CO' || country === 'PE') {
            if(country === 'CO') {
                /*Shown Elements*/
                jQuery("#woocommerce_openpay_cards_iva").closest("tr").show();
                jQuery("#woocommerce_openpay_cards_charge_type_co_pe").closest("tr").show();
                /*Peru Hidden Elements*/
                jQuery("#woocommerce_openpay_cards_capture").closest("tr").hide();
                jQuery("#woocommerce_openpay_cards_show_installments_pe").closest("tr").hide();
                jQuery("#woocommerce_openpay_cards_save_cc option[value='2']").hide();
                jQuery("#woocommerce_openpay_cards_save_cc_description").hide();
            }
            if (country === 'PE') {
                /*Shown Elements*/
                jQuery("#woocommerce_openpay_cards_capture").closest("tr").show();
                jQuery("#woocommerce_openpay_cards_show_installments_pe").closest("tr").show();
                jQuery("#woocommerce_openpay_cards_save_cc option[value='2']").show();
                jQuery("#woocommerce_openpay_cards_save_cc_description").show();
                jQuery("#woocommerce_openpay_cards_charge_type_co_pe").closest("tr").show();
                /*Colombia Hidden Elements*/
                jQuery("#woocommerce_openpay_cards_iva").closest("tr").hide();
            }
            /*Mexico Hidden Elements*/
            jQuery("#woocommerce_openpay_cards_charge_type").closest("tr").hide();
            jQuery("#woocommerce_openpay_cards_affiliation_bbva").closest("tr").hide();
            jQuery("#woocommerce_openpay_cards_use_card_points").closest("tr").hide();
            jQuery("#woocommerce_openpay_cards_msi").closest("tr").hide();      
            jQuery("#woocommerce_openpay_cards_minimum_amount_interest_free").closest("tr").hide();    
        } else if (country === 'MX') {
            /*Hidden Elements*/
            jQuery("#woocommerce_openpay_cards_iva").closest("tr").hide();
            jQuery("#woocommerce_openpay_cards_show_installments_pe").closest("tr").hide();
            jQuery("#woocommerce_openpay_cards_save_cc option[value='2']").hide();
            jQuery("#woocommerce_openpay_cards_save_cc_description").hide();
            jQuery("#woocommerce_openpay_cards_charge_type_co_pe").closest("tr").hide();
            /*Shown Elements*/
            jQuery("#woocommerce_openpay_cards_charge_type").closest("tr").show();
            jQuery("#woocommerce_openpay_cards_use_card_points").closest("tr").show();
            jQuery("#woocommerce_openpay_cards_msi").closest("tr").show();         
            jQuery("#woocommerce_openpay_cards_minimum_amount_interest_free").closest("tr").show();

            if(merchantOrigin === 'eglobal'){
                /*Shown Elements*/
                jQuery("#woocommerce_openpay_cards_affiliation_bbva").closest("tr").show();
                /*Hidden Elements*/
                jQuery("#woocommerce_openpay_cards_charge_type").closest("tr").hide();
                jQuery("#woocommerce_openpay_cards_capture").closest("tr").hide();
                jQuery("#woocommerce_openpay_cards_country").closest("tr").hide();
            }else{
                /*Hidden Elements*/
                jQuery("#woocommerce_openpay_cards_affiliation_bbva").closest("tr").hide();
                /*Shown Elements*/
                jQuery("#woocommerce_openpay_cards_charge_type").closest("tr").show();
                jQuery("#woocommerce_openpay_cards_capture").closest("tr").show();
                jQuery("#woocommerce_openpay_cards_country").closest("tr").show();
            }
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