jQuery(document).ready(function($) { //Agregado $ como parametro para que exista en la funcion.
	if ($("#id_openpay").length) { //En jQuery, se comprueba un selector al ver su tamaño
		console.log($("#id_openpay").val());
		
		OpenPay.setId($("#id_openpay").val());
		OpenPay.setApiKey($("#api_key_openpay").val());
		OpenPay.setSandboxMode($("#api_sandbox_mode").val() == 'true');
		var deviceSessionId = OpenPay.deviceData.setup("payment-form", "deviceIdHiddenFieldName");
	}
	$('#pay-button').on('click', function(event) {
	   var cardnumber, expiry, cardnumberOpenpay, expiryMonthOpenpay, expiryYearOpenpay;
       event.preventDefault();

       cardnumber = $("#cardplaceholder").val();
       expiry = $('#expiry').val();
       expiry = expiry.split('/');

       cardnumberOpenpay = $('#cardnumberOpenpay');
       expiryMonthOpenpay = $('#expiryMonthOpenpay');
       expiryYearOpenpay = $('#expiryYearOpenpay');

       cardnumberOpenpay.val(cardnumber.replace(/ /g,''));
       expiryMonthOpenpay.val(expiry[0].replace(/ /g,''));
       expiryYearOpenpay.val(expiry[1].replace(/ /g,''));
       
       $("#pay-button").prop( "disabled", true);
       
       OpenPay.token.extractFormAndCreate('payment-form', success_callbak, error_callbak);                
	});
	
	var success_callbak = function(response) {
              var token_id = response.data.id;
              $('#token_id').val(token_id);
              $('#payment-form').submit();
	};
	
	var error_callbak = function(response) {
     var desc = response.data.description != undefined ? 
        response.data.description : response.message;
     
     if (response.data.error_code == 2005){
    	 desc = "La fecha de expiración es incorrecta";
     } else if (response.data.error_code == 2004) {
    	 desc = "Número de tarjeta inválido";
     } else if (response.data.error_code == 2006) {
    	 desc = "Codigo de seguridad inválido";
     } else if (response.data.error_code == 1000) {
    	 desc = "Ocurrió un error al realizar el pago intenta más tarde";
     }
     alert(desc);
     $("#pay-button").prop("disabled", false);
	};
	
	$("#tiendas").click(function(){
		//alert("Generando código de barras");
	});
	
	$("#transferencia").click(function(){
		//alert("Información para transferencia");
	});
});

