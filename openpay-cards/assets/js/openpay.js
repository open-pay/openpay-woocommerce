OpenPay.setId(wc_openpay_params.merchant_id);
OpenPay.setApiKey(wc_openpay_params.public_key);
OpenPay.setSandboxMode(wc_openpay_params.sandbox_mode);

jQuery( document ).ready(function() {
    
    var $form = jQuery('form.checkout,form#order_review');
    
    jQuery('.wc-credit-card-form-card-number').cardNumberInput();
    jQuery('.wc-credit-card-form-card-expiry').payment('formatCardExpiry');
    jQuery('.wc-credit-card-form-card-cvc').payment('formatCardCVC');

    jQuery('body').on('updated_checkout', function () {
        //jQuery('.wc-credit-card-form-card-number').payment('formatCardNumber');
        jQuery('.wc-credit-card-form-card-number').cardNumberInput();
        jQuery('.wc-credit-card-form-card-expiry').payment('formatCardExpiry');
        jQuery('.wc-credit-card-form-card-cvc').payment('formatCardCVC');
    });
    
    jQuery('body').on('click', 'form#order_review input:submit', function(){
        if(jQuery('input[name=payment_method]:checked').val() != 'openpay_cards'){
            return true;
        }
        return false;
    });
    
    jQuery('body').on('click', 'form.checkout input:submit', function(){
        jQuery('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
        // Make sure there's not an old token on the form
        jQuery('form.checkout').find('[name=openpay_token]').remove();
    });
    
    // Bind to the checkout_place_order event to add the token
    jQuery('form.checkout').bind('checkout_place_order', function (e) {

        if (jQuery('input[name=payment_method]:checked').val() != 'openpay_cards') {
            return true;
        }
        $form.find('.payment-errors').html('');
        $form.block({message: null, overlayCSS: {background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center", backgroundSize: "16px 16px", opacity: 0.6}});

        // Pass if we have a token
        if ($form.find('[name=openpay_token]').length)
            return true;

        openpayFormHandler();
        // Prevent the form from submitting with the default action
        return false;
    });    

});

function openpayFormHandler() {
        
            var holder_name = jQuery('#openpay-holder-name').val();
            var card = jQuery('#openpay-card-number').val();
            var cvc = jQuery('#openpay-card-cvc').val();
            var expires = jQuery('#openpay-card-expiry').payment('cardExpiryVal');
            var $form = jQuery("form.checkout, form#order_review");
            
            var str = expires['year'];
            var year = str.toString().substring(2, 4);
            
            
            var data = {
                holder_name: holder_name,
                card_number: card.replace(/ /g,''),
                cvv2: cvc,
                expiration_month: expires['month'] || 0,
                expiration_year: year || 0,
                address: {}
            };
            
            if (jQuery('#billing_address_1').size() > 0) {
                data.address.line1 = jQuery('#billing_address_1').val();
                data.address.line2 = jQuery('#billing_address_2').val();
                data.address.state = jQuery('#billing_state').val();
                data.address.city = jQuery('#billing_city').val();
                data.address.postal_code = jQuery('#billing_postcode').val();
                data.address.country_code = 'MX';
            } else if (data.address.line1) {
                data.address.line1 = wc_openpay_params.billing_address_1;
                data.address.line2 = wc_openpay_params.billing_address_2;
                data.address.state = wc_openpay_params.billing_state;
                data.address.city = wc_openpay_params.billing_city;
                data.address.postal_code = wc_openpay_params.billing_postcode;
                data.address.country_code = 'MX';
            }
            
            OpenPay.token.create(data, success_callback, error_callback);            
    
}


function success_callback(response) {
    var $form = jQuery("form.checkout, form#order_review");
    var token = response.data.id;
    var device_session_id = OpenPay.deviceData.setup(".checkout", "device_session_id");
    jQuery('#device_session_id').val(device_session_id);
    $form.append('<input type="hidden" name="openpay_token" value="' + token + '" />');
    $form.submit();
};


function error_callback(response) {
    var $form = jQuery("form.checkout, form#order_review");
    var msg = "";
    switch (response.data.error_code) {
        case 1000:
            msg = "Servicio no disponible.";
            break;

        case 1001:
            msg = "Los campos no tienen el formato correcto, o la petición no tiene campos que son requeridos.";
            break;

        case 1004:
            msg = "Servicio no disponible.";
            break;

        case 1005:
            msg = "Servicio no disponible.";
            break;

        case 2004:
            msg = "El dígito verificador del número de tarjeta es inválido de acuerdo al algoritmo Luhn.";
            break;

        case 2005:
            msg = "La fecha de expiración de la tarjeta es anterior a la fecha actual.";
            break;

        case 2006:
            msg = "El código de seguridad de la tarjeta (CVV2) no fue proporcionado.";
            break;

        default: //Demás errores 400
            msg = "La petición no pudo ser procesada.";
            break;
    }

    // show the errors on the form
    jQuery('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
    jQuery('#openpay-holder-name').closest('p').before('<ul style="background-color: #e2401c; color: #fff;" class="woocommerce_error woocommerce-error"><li> ERROR ' + response.data.error_code + '. '+msg+'</li></ul>');
    $form.unblock();
    
};