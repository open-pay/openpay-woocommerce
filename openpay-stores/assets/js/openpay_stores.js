jQuery(document).ready(function () {
    jQuery(document).on("click", "#payment .payment_method_openpay_stores", function() {
        var openpay_stores_width = jQuery(".payment_method_openpay_stores").width();
        console.log(openpay_stores_width);
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
    });
});