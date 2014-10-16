$(function(){
	if ($("#id_openpay")) {
		console.log($("#id_openpay").val());
		
		OpenPay.setId($("#id_openpay").val());
		OpenPay.setApiKey($("#api_key_openpay").val());
		OpenPay.setSandboxMode(true);
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
     alert("ERROR [" + response.status + "] " + desc);
     $("#pay-button").prop("disabled", false);
	};
	
	$("#tiendas").click(function(){
		//alert("Generando código de barras");
	});
	
	$("#transferencia").click(function(){
		//alert("Información para transferencia");
	});
});
