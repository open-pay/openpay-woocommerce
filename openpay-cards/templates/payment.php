<?php
/*
  Title:	Openpay Payment extension for WooCommerce
  Author:	Federico Balderas
  URL:		http://foograde.com
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<style>
    .form-row{
        margin: 0 0 6px !important;
        padding: 3px !important;
    }
    
    .form-row select{
        width: 100% !important;
    }
    
    
    .openpay-total{
        font-size: 2em;
        font-weight: bold;        
    }
</style>

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
    <div class="form-row form-row-wide">
        <label for="openpay-holder-name">Nombre del tarjetahabiente <span class="required">*</span></label>
        <input id="openpay-holder-name" style="font-size: 1.5em; padding: 8px;" class="input-text" type="text" autocomplete="off" placeholder="Nombre del tarjetahabiente" data-openpay-card="holder_name" />
    </div>	
    <div class="form-row form-row-wide">
        <label for="openpay-card-number">Número de tarjeta <span class="required">*</span></label>
        <input id="openpay-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" data-openpay-card="card_number" />
    </div>
    <div class="form-row form-row-first">
        <label for="openpay-card-expiry">Expira (MM/YY) <span class="required">*</span></label>
        <input id="openpay-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / YY" data-openpay-card="expiration_year" />
    </div>
    <div class="form-row form-row-last">
        <label for="openpay-card-cvc">Código de seguridad <span class="required">*</span></label>
        <input id="openpay-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="CVC" data-openpay-card="cvv2" />
    </div>
    <?php if($this->show_months_interest_free): ?>
        <div class="form-row form-row-first">
            <label for="openpay-card-number">Pago a meses sin intereses <span class="required">*</span></label>
            <select name="openpay_month_interest_free" id="openpay_month_interest_free" class="form-control">
                <option value="1">Pago de contado</option>
                <?php foreach($this->months as $key => $month): ?>
                    <option value="<?php echo $key ?>"><?php echo $month ?></option>
                <?php endforeach; ?>
            </select>
        </div>    
        <div id="total-monthly-payment" class="form-row form-row-last hidden">
            <label>Estarías pagando mensualmente</label>
            <p class="openpay-total"><span id="monthly-payment"></span></p>
        </div>
    <?php endif; ?>
    <input type="hidden" name="device_session_id" id="device_session_id" />
</div>
<div style="height: 1px; clear: both; border-bottom: 1px solid #CCC; margin: 10px 0 10px 0;"></div>
<div style="text-align: center">
    <img alt="" src="<?php echo $this->images_dir ?>openpay.png">	
</div>