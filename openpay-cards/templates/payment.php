<?php
/*
  Title:	Openpay Payment extension for WooCommerce
  Author:	Openpay
  URL:		http://www.openpay.mx
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
    
    .openpay-select { 
        color: #444;
        line-height: 28px;
        border-radius: 2px !important;
        padding: 5px 10px !important;        
        border: solid 2px #e4e4e4;
    }    
    
    .hidden {
        visibility: hidden;
    }
</style>

<div style="overflow: hidden;">
    <div>
        <div style="width: 100%;">
            <h5>Tarjetas de crédito</h5>	
            <img alt="" src="<?php echo $this->images_dir ?>credit_cards.png" style="float: left !important;">	
        </div>
        <div style="width: 100%;">
            <h5>Tarjetas de débito</h5>	
            <img alt="" src="<?php echo $this->images_dir ?>debit_cards.png">	
        </div>
    </div>	
    <div style="height: 1px; clear: both; border-bottom: 1px solid #CCC; margin: 10px 0 10px 0;"></div>
<!--	<span class='payment-errors required'></span>-->
    <h3>Información de Pago</h3>
    <?php if ($this->is_sandbox): ?>
        <p><?php echo $this->description ?></p>
    <?php endif; ?>
    <div class="form-row form-row-wide">        
        <select name="openpay_cc" id="openpay_cc" class="openpay-select">
            <?php foreach($this->cc_options as $cc): ?>
                <option value="<?php echo $cc['value'] ?>"><?php echo $cc['name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
        
    <div id="payment_form_openpay_cards">    
        <div class="form-row form-row-wide">
            <label for="openpay-holder-name">Nombre del tarjetahabiente <span class="required">*</span></label>
            <input id="openpay-holder-name" style="font-size: 1.5em; padding: 8px;" class="input-text" type="text" autocomplete="off" placeholder="Nombre del tarjetahabiente" data-openpay-card="holder_name" />
        </div>	
        <div class="form-row form-row-wide">
            <label for="openpay-card-number">Número de tarjeta <span class="required">*</span></label>
            <input id="openpay-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" data-openpay-card="card_number" />
        </div>
        <div class="form-row form-row-first">
            <label for="openpay-card-expiry">Expira (MM/AA) <span class="required">*</span></label>
            <input id="openpay-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / AA" data-openpay-card="expiration_year" />
        </div>
        <div class="form-row form-row-last">
            <label for="openpay-card-cvc">CVV <span class="required">*</span></label>
            <input id="openpay-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="CVC" data-openpay-card="cvv2" />
        </div>        
        <div class="form-row form-row-wide <?php echo !$this->can_save_cc ? 'hidden' : '' ?>" style="margin-bottom: 20px;">
            <label for="save_cc" class="label">
                <input type="checkbox" name="save_cc" id="save_cc" /> <span style="font-weight: 600;">Guardar tarjeta</span>
            </label>    
        </div>        
    </div>    
        
    <?php if($this->show_months_interest_free): ?>
        <div class="form-row form-row-wide">
            <label for="openpay-card-number">Pago a meses sin intereses <span class="required">*</span></label>
            <select name="openpay_month_interest_free" id="openpay_month_interest_free" class="form-control">
                <option value="1">Pago de contado</option>
                <?php foreach($this->months as $key => $month): ?>
                    <option value="<?php echo $key ?>"><?php echo $month ?></option>
                <?php endforeach; ?>
            </select>
        </div>    
        <div id="total-monthly-payment" class="form-row form-row-wide hidden">
            <label>Estarías pagando mensualmente</label>
            <p class="openpay-total"><span id="monthly-payment"></span></p>
        </div>
    <?php endif; ?>
    <input type="hidden" name="device_session_id" id="device_session_id" />
    <input type="hidden" name="use_card_points" id="use_card_points" value="false" />
</div>
<div style="height: 1px; clear: both; border-bottom: 1px solid #CCC; margin: 10px 0 10px 0;"></div>
<div style="text-align: center">
    <img alt="" src="<?php echo $this->images_dir ?>openpay.png">	
</div>