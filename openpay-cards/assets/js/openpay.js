OpenPay.setId(wc_openpay_params.merchant_id);
OpenPay.setApiKey(wc_openpay_params.public_key);
OpenPay.setSandboxMode(wc_openpay_params.sandbox_mode);
var deviceSessionId = OpenPay.deviceData.setup();

jQuery(document).ready(function () {    
    jQuery('#device_session_id').val(deviceSessionId);
    
    //console.log("jQuery v"+jQuery.fn.jquery);    
    console.log("jQuery Migrate v"+jQuery.migrateVersion);
    console.log("jQuery v"+jQuery().jquery);

    //  BOOTSTRAP JS WITH LOCAL FALLBACK
    if(typeof(jQuery.fn.modal) === 'undefined') {        
        var bootstrap_script = document.createElement('script');
        bootstrap_script.setAttribute('type', 'text/javascript');
        bootstrap_script.setAttribute('src', wc_openpay_params.bootstrap_js);
        document.body.appendChild(bootstrap_script);
        jQuery("head").prepend('<link rel="stylesheet" href="'+wc_openpay_params.bootstrap_css+'" type="text/css" media="screen">');
    } else {
        console.log('Bootstrap loaded');
    }
    
    jQuery( "body" ).append('<div class="modal fade" role="dialog" id="card-points-dialog"> <div class="modal-dialog modal-sm"> <div class="modal-content"> <div class="modal-header"> <h4 class="modal-title">Pagar con Puntos</h4> </div> <div class="modal-body"> <p>¿Desea usar los puntos de su tarjeta para realizar este pago?</p> </div> <div class="modal-footer"> <button type="button" class="btn btn-success" data-dismiss="modal" id="points-yes-button">Si</button> <button type="button" class="btn btn-default" data-dismiss="modal" id="points-no-button">No</button> </div> </div> </div></div>');
    var $form = jQuery('form.checkout,form#order_review');
    var total = wc_openpay_params.total;        
    
    jQuery(document).on("change", "#openpay_month_interest_free", function() {        
        var monthly_payment = 0;
        var months = parseInt(jQuery(this).val());     

        if (months > 1) {
            jQuery("#total-monthly-payment").removeClass('hidden');
        } else {
            jQuery("#total-monthly-payment").addClass('hidden');
        }

        monthly_payment = total/months;
        monthly_payment = monthly_payment.toFixed(2);
        
        jQuery("#monthly-payment").text('$'+monthly_payment+' '+wc_openpay_params.currency);
    });
    
    jQuery(document).on("change", "#openpay_cc", function() {        
        if (jQuery('#openpay_cc').val() !== "new") {                                 
            jQuery('#save_cc').prop('checked', false);                
            jQuery('#save_cc').prop('disabled', true);                 

            jQuery('#openpay-holder-name').val("");
            jQuery('#openpay-card-number').val("");                                     
            jQuery('#openpay-card-expiry').val("");            
            jQuery('#openpay-card-cvc').val("");                                                         
                            
            jQuery('#payment_form_openpay_cards').hide();
        } else {                    
            jQuery('#payment_form_openpay_cards').show();            
            jQuery('#save_cc').prop('disabled', false);
        }
    });  
    
    jQuery('.wc-credit-card-form-card-number').cardNumberInput();
    jQuery('.wc-credit-card-form-card-expiry').payment('formatCardExpiry');
    jQuery('.wc-credit-card-form-card-cvc').payment('formatCardCVC');

    jQuery('body').on('updated_checkout', function () {
        console.log("Openpay updated_checkout");
        //jQuery('.wc-credit-card-form-card-number').payment('formatCardNumber');
        jQuery('.wc-credit-card-form-card-number').cardNumberInput();
        jQuery('.wc-credit-card-form-card-expiry').payment('formatCardExpiry');
        jQuery('.wc-credit-card-form-card-cvc').payment('formatCardCVC');
    });

    jQuery('body').on('click', 'form.checkout button:submit', function () {
        console.log("woocommerce_error");
        jQuery('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
        // Make sure there's not an old token on the form
        jQuery('form.checkout').find('[name=openpay_token]').remove();
    });

    // Bind to the checkout_place_order event to add the token
    jQuery('form.checkout').bind('checkout_place_order', function (e) {
        console.log("form.checkout");
        if (jQuery('input[name=payment_method]:checked').val() !== 'openpay_cards') {
            return true;
        }
        console.log("checkout_place_order");
        $form.find('.payment-errors').html('');
        $form.block({message: null, overlayCSS: {background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center", backgroundSize: "16px 16px", opacity: 0.6}});
        
        if (jQuery('#openpay_cc').val() !== 'new') {
            $form.append('<input type="hidden" name="openpay_token" value="' + jQuery('#openpay_cc').val() + '" />');
            return true;
        }

        // Pass if we have a token
        if ($form.find('[name=openpay_token]').length){
            console.log("openpay_token = true");
            return true;
        }else{
            console.log("openpay_token = false");
        }            

        openpayFormHandler();
        // Prevent the form from submitting with the default action
        return false;
    });

    function openpayFormHandler() {
        var holder_name = jQuery('#openpay-holder-name').val();
        var card = jQuery('#openpay-card-number').val();
        var cvc = jQuery('#openpay-card-cvc').val();
        var expires = jQuery('#openpay-card-expiry').payment('cardExpiryVal');        

        var str = expires['year'];
        var year = str.toString().substring(2, 4);


        var data = {
            holder_name: holder_name,
            card_number: card.replace(/ /g, ''),
            cvv2: cvc,
            expiration_month: expires['month'] || 0,
            expiration_year: year || 0        
        };

        if (jQuery('#billing_address_1').length) {                                
            if(jQuery('#billing_address_1').val() && jQuery('#billing_state').val() && jQuery('#billing_city').val() && jQuery('#billing_postcode').val()) {
                data.address = {};
                data.address.line1 = jQuery('#billing_address_1').val();
                data.address.line2 = jQuery('#billing_address_2').val();
                data.address.state = jQuery('#billing_state').val();
                data.address.city = jQuery('#billing_city').val();
                data.address.postal_code = jQuery('#billing_postcode').val();
                data.address.country_code = 'MX';
            }                                 
        } 

        OpenPay.token.create(data, success_callback, error_callback);
    }


    function success_callback(response) {
        //var $form = jQuery("form.checkout, form#order_review");
        var token = response.data.id;        
        $form.append('<input type="hidden" name="openpay_token" value="' + token + '" />');
        
        if (response.data.card.points_card && wc_openpay_params.use_card_points) {
            // Si la tarjeta permite usar puntos, mostrar el cuadro de diálogo
            jQuery("#card-points-dialog").modal("show");
        } else {
            // De otra forma, realizar el pago inmediatamente
            $form.submit();
        }       
    }

    jQuery("#points-yes-button").on('click', function () {        
        jQuery('#use_card_points').val('true');
        $form.submit();
    });



    jQuery("#points-no-button").on('click', function () {        
        jQuery('#use_card_points').val('false');
        $form.submit();
    });

    function error_callback(response) {
        //var $form = jQuery("form.checkout, form#order_review");
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
        jQuery('#openpay_cc').closest('div').before('<ul style="background-color: #e2401c; color: #fff;" class="woocommerce_error woocommerce-error"><li> ERROR ' + response.data.error_code + '. ' + msg + '</li></ul>');
        $form.unblock();
    }

});