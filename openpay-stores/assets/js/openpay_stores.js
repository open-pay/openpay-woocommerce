jQuery(document).ajaxComplete(function() {
    if(jQuery('#payment .payment_box.payment_method_openpay_stores').is(":visible")){
        designCheckout();
    }

    jQuery("#payment .wc_payment_method.payment_method_openpay_stores").on("click", function() {
        designCheckout();
    });

    function designCheckout(){
        var openpay_stores_width = jQuery("#payment .wc_payment_method.payment_method_openpay_stores").width();
        if(openpay_stores_width > 576){
            jQuery("#payment .store-logos__puntored").css({"flex": "0 0 25%", "max-width": "25%"});
            jQuery("#payment .store-logos__via").css({
                "flex": "0 0 70%",
                "max-width": "70%",
                "justify-content": "left",
                "padding-left": "25px"
            });
            jQuery("#payment .store-logos__puntored > img").css({"margin": "0"});
        }
    }
});