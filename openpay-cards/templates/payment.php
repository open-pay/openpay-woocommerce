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

    #openpay_cards.opacity {
        opacity: 0.5;
    }

    #openpay_cards.opacity .ajax-loader.is-active {
        position: absolute;
        left: calc(50% - 15px);
        top: calc(50% - 15px);
        height:30px;
        width:30px;
        margin:0px auto;
        -webkit-animation: rotation .6s infinite linear;
        -moz-animation: rotation .6s infinite linear;
        -o-animation: rotation .6s infinite linear;
        animation: rotation .6s infinite linear;
        border-left:4px solid rgba(0,174,239,.15);
        border-right:4px solid rgba(0,174,239,.15);
        border-bottom:4px solid rgba(0,174,239,.15);
        border-top:4px solid rgba(0,174,239,.8);
        border-radius:100%;
    }

    .openpay_logo{
        float: inherit !important;
        margin-left: auto;
        margin-right: auto;
    }

    .payment_box.payment_method_openpay_cards{
        background-color: #F5F7F9 !important;
    }


    #payment .payment_methods li img{
        max-height:2.618em;
    }

    #openpay_cards{
        overflow: unset !important;
    }

    .tooltiptext {
        visibility: hidden;
        width: 179px;
        background-color: black;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px 5px;
        position: absolute;
        bottom: -17%;
        margin-left: 10px;
        z-index: 1;
    }

    .tooltiptext::after {
        content: "";
        position: absolute;
        top: 5%;
        left: 46.8%;
        margin-left: -55%;
        border-width: 8px;
        border-style: solid;
        border-color: transparent #000 transparent transparent;
    }

    .tooltip:hover .tooltiptext {
        visibility: visible;
    }

    .openpay-card-input-cvc {
        background-image: url('<?php echo $this->images_dir ?>card_cvc.svg') !important;
        background-repeat: no-repeat;
        background-position: right 0.618em center;
        background-size: 32px 20px;
    }

    @-webkit-keyframes rotation {
    from {-webkit-transform: rotate(0deg);}
    to {-webkit-transform: rotate(359deg);}
    }
    @-moz-keyframes rotation {
    from {-moz-transform: rotate(0deg);}
    to {-moz-transform: rotate(359deg);}
    }
    @-o-keyframes rotation {
    from {-o-transform: rotate(0deg);}
    to {-o-transform: rotate(359deg);}
    }
    @keyframes rotation {
    from {transform: rotate(0deg);}
    to {transform: rotate(359deg);}
    }

</style>

<div id="openpay_cards" style="overflow: hidden; position: relative;">
    <div class="ajax-loader"></div>
    <div>
            <div style="width: 100%;">
                <?php
                    $title = ($this->country !== 'PE') ? 'Tarjetas de crédito' : 'Tarjetas de crédito/débito aceptadas'; 
                ?>
                <h5><?php echo $title; ?></h5>
                <?php if($this->country == 'MX'): ?>
                    <?php if($this->merchant_classification != 'eglobal'): ?>
                        <img alt="" src="<?php echo $this->images_dir ?>credit_cards.png" style="float: left !important;">
                    <?php else:?>
                        <img alt="" src="<?php echo $this->images_dir ?>credit_cards_bbva.png" style="float: left !important;">
                    <?php endif; ?>
                <?php elseif($this->country == 'CO'): ?>
                    <img alt="" src="<?php echo $this->images_dir ?>credit_cards_co.png" style="float: left !important;">
                <?php elseif($this->country == 'PE'): ?>
                    <img alt="" width="200px" src="<?php echo $this->images_dir ?>credit_cards_pe.png" style="float: left !important; margin-bottom: 10px;">
                <?php endif; ?>
            </div>
            <div style="width: 100%;">
                <h5 class="<?php if($this->country == 'PE') echo 'hidden'; ?>">Tarjetas de débito</h5>
                <?php if($this->country == 'MX'): ?>
                    <img alt="" src="<?php echo $this->images_dir ?>debit_cards.png">
                <?php elseif($this->country == 'CO'): ?>
                    <img alt="" src="<?php echo $this->images_dir ?>debit_cards_co.png" style="float: left !important; margin-bottom: 10px;">
                <?php endif; ?>
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
        
    <div id="payment_form_openpay_cards"> <!--class="openpay_new_card"-->
        <!--
        <fieldset id="wc-openpay-cc-form" class="wc-credit-card-form wc-payment-form">
        -->
        <div class="form-row form-row-wide openpay-holder-name">
            <label for="openpay-holder-name">Nombre del títular <span class="required">*</span></label>
            <input id="openpay-holder-name" style="font-size: 1.2em; padding: 8px;" class="input-text" type="text" autocomplete="off" placeholder="Nombre del tarjetahabiente" data-openpay-card="holder_name" />
        </div>
        <div class="form-row form-row-wide openpay-card-number">
            <label for="openpay-card-number">Número de tarjeta <span class="required">*</span></label>
            <input id="openpay-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" data-openpay-card="card_number" />
        </div>
        <!--
        <p class="form-row form-row-wide">
            <label for="openpay-card-number">Número de tarjeta&nbsp;<span class="required">*</span></label>
            <input id="openpay-card-number" class="input-text wc-credit-card-form-card-number unknown" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="•••• •••• •••• ••••" name="openpay-card-number">
        </p>
        -->
        <div class="form-row form-row-first openpay-card-expiry">
            <label for="openpay-card-expiry">Expira (MM/AA) <span class="required">*</span></label>
            <input id="openpay-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / AA" data-openpay-card="expiration_year" />
        </div>
        <div class="form-row form-row-last openpay-card-cvc">
            <label for="openpay-card-cvc">CVV <span class="required">*</span></label>
            <input id="openpay-card-cvc" name="openpay-card-cvc" class="input-text wc-credit-card-form-card-cvc openpay-card-input-cvc" type="password" autocomplete="off" placeholder="CVC" data-openpay-card="cvv2" />
        </div>        
        <div class="form-row form-row-wide save_cc <?php echo !$this->can_save_cc ? 'hidden' : '' ?>" style="margin-bottom: 20px;">
            <label for="save_cc" class="label">
                <div class="tooltip">
                <input type="checkbox" name="save_cc" id="save_cc" />
                <span style="font-weight: 600;">Guardar tarjeta</span>
                <img  style=" float: none; display:unset; max-height: 1em;" alt="" src="<?php echo $this->images_dir ?>tooltip_symbol.svg">
                <span class="tooltiptext" >Al guardar los datos de tu tarjeta agilizarás tus pagos futuros y podrás usarla como método de pago guardado.</span>
                </div>
            </label>
        </div>
        <!--
        </fieldset>
        -->
    </div>    
        
    <?php if($this->show_months_interest_free): ?>
        <div class="form-row form-row-wide" style="display: none;">
            <label for="openpay-card-number">Pago a meses sin intereses <span class="required">*</span></label>
            <select name="openpay_month_interest_free" id="openpay_month_interest_free" class="openpay-select">
                <option value="1">Pago de contado</option>
                <?php foreach($this->months as $key => $month): ?>
                    <option value="<?php echo $key ?>"><?php echo $month ?></option>
                <?php endforeach; ?>
            </select>
        </div>    
        <div id="total-monthly-payment" class="form-row form-row-wide hidden">
            <label>Estarías pagando mensualmente</label>
            <p class="openpay-total"><span id="monthly-payment"></span></p>
            <div style="display: none"><?php echo WC()->cart->total?></div>
        </div>
    <?php endif; ?>
        
    <?php if($this->show_installments): ?>
        <div class="form-row form-row-wide" style="display: none;">
            <label for="openpay-card-number">Cuotas <span class="required">*</span></label>
            <select name="openpay_installments" id="openpay_installments" class="openpay-select">
                <option value="1">Sola una cuota</option>
                <?php foreach($this->installments as $key => $installments): ?>
                    <option value="<?php echo $key ?>"><?php echo $installments ?></option>
                <?php endforeach; ?>
            </select>
        </div>            
    <?php endif; ?>

    <?php if($this->show_installments_pe): ?>
        <div class="form-row form-row-wide" style="display: none;">
            <label id="installments_title" for="openpay-card-number">Cuotas<span class="required">*</span></label>
            <select name="openpay_installments_pe" id="openpay_installments_pe" class="openpay-select">
            </select>
            <input type="hidden" name="withInterest" id="withInterest"/>
        </div>            
    <?php endif; ?>
        
    <input type="hidden" name="device_session_id" id="device_session_id" />
    <input type="hidden" name="use_card_points" id="use_card_points" value="false" />
</div>
<div style="height: 1px; clear: both; border-bottom: 1px solid #CCC; margin: 10px 0 10px 0;"></div>
<div style="text-align: center">
    <?php if($this->merchant_classification != 'eglobal'): ?>
        <img class="openpay_logo" alt="" width="80px" src="https://img.openpay.mx/plugins/openpay_logo.svg">
        <div style="display: flex; margin: 15px 0;">
            <img  style="float: none; display:unset;max-height: 3em;" alt="" src="<?php echo $this->images_dir ?>security_symbol.svg">
            <p style="font-size: 13px; text-align: left; margin-left: 5px;">Tus pagos se realizan de forma segura con encriptación de 256 bits</p>
        </div>
    <?php else: ?>
        <img alt="" src="<?php echo $this->images_dir ?>bbva.png">
    <?php endif; ?>
</div>