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
</style>

<select id="select-payment" class="form-control">
  <option value="card">Credit/Debit Card</option>
  <option value="store">Convenience store payment</option>
  <option value="bank">Bank wire/transfer</option>
</select>

<div id="acceptance">
  <ul id="accepted-cards">
    <li><img src="http://www.openpay.mx/img/costos/costo_tarjeta.gif"></li>
    <li><img src="http://www.openpay.mx/img/costos/costoamex.gif"></li>
  </ul>
  <ul id="stores">
    <li><img src="http://www.openpay.mx/img/costos/costotienda.gif"></li>
    <li><a target="_blank" href="http://www.openpay.mx/tiendas-de-conveniencia.html">ver tiendas afiliadas</a></li>
  </ul>
  <ul id="bank">
    <li><img src="http://www.openpay.mx/img/costos/costo_bancos.gif"></li>
  </ul>
</div>

<div class="form-container active" id="card-form">
  <form method="POST" action="<?php echo $_POST['actionURL']; ?>"  method="POST" id="payment-form">
      <input type="hidden" name="action" value="openpay_pay_creditcard" />
      <input type="hidden" name="order-id" value="<?php echo $_POST['order_id']; ?>" />

      <input type="hidden" name="token_id" id="token_id"/>

      <div class="card-wrapper"></div>

       <fieldset>
          <input placeholder="Card number" type="text" name="number" id="cardplaceholder">
          <input placeholder="Full name" type="text" name="name" data-openpay-card="holder_name">
          <input placeholder="MM/YY" type="text" name="expiry" id="expiry">
          <input placeholder="CVC" type="password" name="cvc" data-openpay-card="cvv2" id="cvc">
       </fieldset>
          <!-- OpenPay hidden Form -->
          <input placeholder="" type="hidden"  data-openpay-card="card_number" id="cardnumberOpenpay">
          <input placeholder="" type="hidden" data-openpay-card="expiration_month" id="expiryMonthOpenpay">
          <input placeholder="" type="hidden" data-openpay-card="expiration_year" id="expiryYearOpenpay">

    <button type="submit" id="pay-button" class="btn btn-default pull-right">Checkout</button>
  </form>
</div>

<div id= "store-form">
  <form method="POST" action="<?php echo $_POST['actionURL']; ?>" method="POST" id="payment-form">
    <input type="hidden" name="action" value="openpay_pay_store" />
    <input type="hidden" name="order-id" value="<?php echo $_POST['order_id']; ?>" />

    <button type="submit" id="tiendas" class="btn btn-default pull-right">Generate Barcode</button>
  </form>
</div>

<div id= "bankwire-form">
  <form method="POST" action="<?php echo $_POST['actionURL']; ?>" method="POST" id="payment-form">
    <input type="hidden" name="action" value="openpay_pay_transfer" />
    <input type="hidden" name="order-id" value="<?php echo $_POST['order_id']; ?>" />

    <button type="submit" id="transferencia" class="btn btn-default pull-right">Bank wire</button>
  </form>
</div>


<script src="//cdnjs.cloudflare.com/ajax/libs/card/0.0.2/js/card.min.js"></script>
<script type="text/javascript">$(".active form").card({ container: $(".card-wrapper") });</script>

<script type="text/javascript">
    var paymentSelection, cardForm, storeForm, bankForm, acceptedCards, store, banks;
    paymentSelection  = $('#select-payment');
    cardForm          = $('#card-form');
    storeForm         = $('#store-form');
    bankForm          = $('#bankwire-form');
    acceptedCards     = $('#accepted-cards');
    store             = $('#stores');
    bank              = $('#bank');

    /*$( "#cvc" ).on('input' ,function(){ $('.cvc').delay(1000).text('***'); });*/
	$( "#cvc" ).on('change',function () {
		$('.cvc').text('***');
	});
  
	$( "#cvc" ).keypress(function( event ) {
		$('.cvc').delay(1000).text('***');
	});
		

    if(paymentSelection.val() === 'card'){
      bank.fadeOut();
      store.fadeOut();
      storeForm.fadeOut();
      bankForm.fadeOut();
      cardForm.fadeIn();
      acceptedCards.fadeIn();
    }
    paymentSelection.change(function(){
      if($(this).val() === 'card'){
        bank.fadeOut();
        store.fadeOut();
        storeForm.fadeOut();
        bankForm.fadeOut();
        cardForm.fadeIn();;
        acceptedCards.fadeIn();
      }else if($(this).val() === 'store'){
        acceptedCards.fadeOut();
        cardForm.fadeOut();
        bankForm.fadeOut();
        bank.fadeOut();
        store.fadeIn();
        storeForm.fadeIn();
      }else if($(this).val() === 'bank'){
        acceptedCards.fadeOut();
        cardForm.fadeOut();
        storeForm.fadeOut();
        store.fadeOut();
        bank.fadeIn();
        bankForm.fadeIn();
      }
    });
</script>
