<?php
/*
  Title:	Openpay Payment extension for WooCommerce
  Author:	Federico Balderas
  URL:		http://foograde.com
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<div style="overflow: hidden;">
	<div style="overflow: hidden;">
		<div style="width: 35%; float: left;">
			<h5>Tarjetas de crédito</h5>	
			<img alt="" src="<?php echo $this->images_dir ?>credit_cards.png">	
		</div>
		<div style="width: 65%; float: left;">
			<h5>Tarjetas de débito</h5>	
			<img alt="" src="<?php echo $this->images_dir ?>debit_cards.png">	
		</div>
	</div>	
	<div style="height: 1px; clear: both; border-bottom: 1px solid #CCC; margin: 10px 0 10px 0;"></div>
<!--	<span class='payment-errors required'></span>-->
	<?php if ($this->is_sandbox): ?>
		<p><?php echo $this->description ?></p>
	<?php endif; ?>
	<p class="form-row form-row-wide">
		<label for="openpay-holder-name">Nombre del tarjetahabiente <span class="required">*</span></label>
		<input id="openpay-holder-name" style="font-size: 1.5em; padding: 8px;" class="input-text" type="text" autocomplete="off" placeholder="Nombre del tarjetahabiente" data-openpay-card="holder_name" />
	</p>	
	<p class="form-row form-row-wide">
		<label for="openpay-card-number">Número de tarjeta <span class="required">*</span></label>
		<input id="openpay-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" data-openpay-card="card_number" />
	</p>
	<p class="form-row form-row-first">
		<label for="openpay-card-expiry">Expira (MM/YY) <span class="required">*</span></label>
		<input id="openpay-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / YY" data-openpay-card="expiration_year" />
	</p>
	<p class="form-row form-row-last">
		<label for="openpay-card-cvc">Código de seguridad <span class="required">*</span></label>
		<input id="openpay-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="CVC" data-openpay-card="cvv2" />
	</p>
	<input type="hidden" name="device_session_id" id="device_session_id" />
</div>
<div style="height: 1px; clear: both; border-bottom: 1px solid #CCC; margin: 10px 0 10px 0;"></div>
<div style="text-align: center">
	<img alt="" src="<?php echo $this->images_dir ?>openpay.png">	
</div>