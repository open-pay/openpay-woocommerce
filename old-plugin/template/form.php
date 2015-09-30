<!-- Latest compiled and minified CSS -->
 <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
 <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/card/0.0.2/css/card.min.css">

<style type="text/css">
  #select-payment{
    margin: 20px 0;
  }

  .card-wrapper{
    margin: 20px 0;
  }  

  #acceptance ul{display: inline; list-style: none;}
  #acceptance ul li{display: inline;}

  ul#accepted-cards{ display: none;}
  ul#stores{ display: none;}
  ul#stores li{ display: block;}
  ul#bank{ display: none;}

  #card-form, #store-form, #bankwire-form{
    display: none;
  }

  #card-form fieldset{
    padding-left: 35px;
    margin-bottom: 25px;
  }

  #error{text-align: center; display: none;}
</style>

<div class="alert alert-danger alert-dismissible" role="alert" id="error">
  <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Cerrar</span></button>
  Ocurrió un error al procesar el pago, revise que la información es correcta e intente de nuevo.
</div>

<select id="select-payment" class="form-control">
  <option value="card">Tarjeta Débito/Crédito</option>
  <option value="store">Tiendas de conveniencia</option>
  <option value="bank">Transferencia Interbancaria</option>
</select>

<div id="acceptance">
  <ul id="accepted-cards">
    <li><img src="https://openpay.s3.amazonaws.com/images/costo_tarjeta.gif"></li>
    <li><img src="https://openpay.s3.amazonaws.com/images/costoamex.gif"></li>
  </ul>
  <ul id="stores">
    <li><img src="https://openpay.s3.amazonaws.com/images/costotienda.gif"></li>
    <li><a target="_blank" href="http://www.openpay.mx/tiendas-de-conveniencia.html">ver tiendas afiliadas</a></li>
  </ul>
  <ul id="bank">
    <li><img src="https://openpay.s3.amazonaws.com/images/costo_bancos.gif"></li>
  </ul>
</div>

<div class="form-container active" id="card-form">
  <form method="POST" action="<?php echo $_POST['actionURL']; ?>"  method="POST" id="payment-form">
      <input type="hidden" name="action" value="openpay_pay_creditcard" />
      <input type="hidden" name="order-id" value="<?php echo $_POST['order_id']; ?>" />

      <input type="hidden" name="token_id" id="token_id"/>

      <div class="card-wrapper"></div>

       <fieldset>
          <input placeholder="Número de tarjeta" type="text" name="number" id="cardplaceholder">
          <input placeholder="Nombre" type="text" name="name" data-openpay-card="holder_name">
          <input placeholder="MM/YY" type="text" name="expiry" id="expiry">
          <input placeholder="CVC" type="password" name="cvc" data-openpay-card="cvv2" id="cvc">
       </fieldset>
          <!-- OpenPay hidden Form -->
          <input placeholder="" type="hidden"  data-openpay-card="card_number" id="cardnumberOpenpay">
          <input placeholder="" type="hidden" data-openpay-card="expiration_month" id="expiryMonthOpenpay">
          <input placeholder="" type="hidden" data-openpay-card="expiration_year" id="expiryYearOpenpay">

    <button type="submit" id="pay-button" class="btn btn-default pull-right">Pagar</button>
  </form>
</div>

<div id= "store-form">
  <form method="POST" action="<?php echo $_POST['actionURL']; ?>" method="POST" id="payment-form">
    <input type="hidden" name="action" value="openpay_pay_store" />
    <input type="hidden" name="order-id" value="<?php echo $_POST['order_id']; ?>" />

    <button type="submit" id="tiendas" class="btn btn-default pull-right">Generar recibo de pago</button>
  </form>
</div>

<div id= "bankwire-form">
  <form method="POST" action="<?php echo $_POST['actionURL']; ?>" method="POST" id="payment-form">
    <input type="hidden" name="action" value="openpay_pay_transfer" />
    <input type="hidden" name="order-id" value="<?php echo $_POST['order_id']; ?>" />

    <button type="submit" id="transferencia" class="btn btn-default pull-right">Generar recibo de pago</button>
  </form>
</div>


<script src="//cdnjs.cloudflare.com/ajax/libs/card/0.0.2/js/card.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.2.0/js/bootstrap.min.js"></script>

<script type="text/javascript">jQuery(".active form").card({ container: jQuery(".card-wrapper") });</script>

<script type="text/javascript">
    var paymentSelection, cardForm, storeForm, bankForm, acceptedCards, store, banks, errorMessage;
    paymentSelection  = jQuery('#select-payment');
    cardForm          = jQuery('#card-form');
    storeForm         = jQuery('#store-form');
    bankForm          = jQuery('#bankwire-form');
    acceptedCards     = jQuery('#accepted-cards');
    store             = jQuery('#stores');
    bank              = jQuery('#bank');
    errorMessage      = jQuery('#error');

    /*$( "#cvc" ).on('input' ,function(){ $('.cvc').delay(1000).text('***'); });*/
	jQuery( "#cvc" ).on('change',function () {
		jQuery('.cvc').text('***');
	});
  
	jQuery( "#cvc" ).keypress(function( event ) {
		jQuery('.cvc').delay(1000).text('***');
	});
		
    if(jQuery.cookie('OpenpayError') == true){
      errorMessage.fadeIn();
    }
    if(paymentSelection.val() === 'card'){
      bank.fadeOut();
      store.fadeOut();
      storeForm.fadeOut();
      bankForm.fadeOut();
      cardForm.fadeIn();
      acceptedCards.fadeIn();
    }
    paymentSelection.change(function(){
      if(jQuery(this).val() === 'card'){
        bank.fadeOut();
        store.fadeOut();
        storeForm.fadeOut();
        bankForm.fadeOut();
        cardForm.fadeIn();;
        acceptedCards.fadeIn();
      }else if(jQuery(this).val() === 'store'){
        acceptedCards.fadeOut();
        cardForm.fadeOut();
        bankForm.fadeOut();
        bank.fadeOut();
        store.fadeIn();
        storeForm.fadeIn();
      }else if(jQuery(this).val() === 'bank'){
        acceptedCards.fadeOut();
        cardForm.fadeOut();
        storeForm.fadeOut();
        store.fadeOut();
        bank.fadeIn();
        bankForm.fadeIn();
      }
    });
</script>
