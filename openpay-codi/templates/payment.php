<?php
/*
  Title:	Openpay Payment extension for WooCommerce
  Author:	Openpay
  URL:		http://www.openpay.mx
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<div class="row" id="steps-container">
    <div class="col-md-12" style="margin-top: 20px; margin-bottom: 10px;">
        <h4>¿Qué es CoDi®?</h4>
        <p><?php echo $this->description ?></p>
    </div>
    <div class="col-md-12" style="margin-top: 20px; margin-bottom: 10px;">
        <h4>Pasos para tu pago por CoDi®</h4>
    </div>
    <div class="col-md-4" style="text-align: center !important; margin-bottom: 5px">
        <img alt="Paso 1" src="<?php echo $this->images_dir ?>step1.png" style="display: inline; height: auto; float: none; max-height: none;" />
        <br/>
        <p>Haz clic en el botón "Realizar pedido", así tu compra quedará en espera de que realices tu pago.</p>
    </div>
    <div class="col-md-4" style="text-align: center !important; margin-bottom: 5px">
        <img alt="Paso 2" src="<?php echo $this->images_dir ?>step2.png" style="display: inline; height: auto; float: none; max-height: none;">	
        <br/>
        <p>Localiza la sección de CoDi® en tu app móvil, sigue los pasos para escanear el código QR para completar tu pago.</p>
    </div>
    <div class="col-md-4" style="text-align: center !important; margin-bottom: 5px">
        <img alt="Paso 3" src="<?php echo $this->images_dir ?>step3.png" style="display: inline; height: auto; float: none; max-height: none;">	
        <br/>
        <p>Inmediatamente después de recibir tu pago te enviaremos un correo electrónico con la confirmación de pago.</p>
    </div>
</div>
<div style="height: 1px; clear: both; border-bottom: 1px solid #CCC; margin: 10px 0 10px 0;"></div>
<div style="text-align: center">
	<img alt="" src="<?php echo $this->images_dir ?>openpay.png">	
</div>