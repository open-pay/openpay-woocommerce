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
    .step img{
        width: 40%;
        margin-bottom: 0.5em;
        margin-top: 1.2em;
        margin-right:initial;
    }

    .step{
        text-align: center;
    }

    .openpay_logo{
        float: inherit !important;
        margin-left: auto;
        margin-right: auto;
    }

    .payment_method_openpay_spei{
        background-color: #F5F7F9 !important;
    }

    .step>p{
        text-align: justify;
        color: #0063A8 ;
    }

    .steps_title{
        font-weight: 400;
        color: #0063A8 ;
        text-align: center;
    }

    @media screen and (max-width: 768px) and (min-width: 375px){
        .step img{
            width: 25%;
            margin-right: 1em;
            margin-bottom: initial;
            margin-top: initial;
        }

        .step{
            display:flex;
            align-items: center;
            margin-bottom: 1.3em;
        }
    }
</style>
<div class="row">
    <div class="col-md-4">
        <img src="<?php echo $this->images_dir ?>spei.png" style="width: 100%; max-height: inherit;">
        <small><a style="color: #11BFF8;" href="http://www.openpay.mx/bancos.html" target="_blank">Consulta los bancos soportados</a></small>
    </div>
    <div class="col-md-8">
        <h4>¿Qué es SPEI?</h4>
        <p>El SPEI es un sistema de pagos para permitir a los clientes de los bancos enviar y recibir transferencias electrónicas de dinero en cuestión de segundos.</p>            
    </div>
</div>
<div class="row">
    <div class="col-md-12" style="margin-top: 20px; margin-bottom: 10px;">
        <h4 class="steps_title">Pasos para tu pago por transferencia interbancaria</h4>
    </div>
    <div class="col-md-4 step">
        <img alt="Paso 1" src="https://img.openpay.mx/plugins/file.svg" style="display: inline; height: auto; float: none; max-height: none;" />
        <p>Haz clic en el botón "Realizar pedido", así tu compra quedará en espera de que realices tu pago.</p>
    </div>
    <div class="col-md-4 step" >
        <img alt="Paso 2" src="https://img.openpay.mx/plugins/spei.svg" style="display: inline; height: auto; float: none; max-height: none;">
        <p>Sigue la guía para realizar el pago SPEI a través del portal de tu banco.</p>
    </div>
    <div class="col-md-4 step" >
        <img alt="Paso 3" src="https://img.openpay.mx/plugins/mail.svg" style="display: inline; height: auto; float: none; max-height: none;">
        <p>Inmediatamente después de recibir tu pago te enviaremos un correo electrónico con la confirmación de pago.</p>
    </div>
</div>
<div style="height: 1px; clear: both; border-bottom: 1px solid #CCC; margin: 10px 0 10px 0;"></div>
<div style="text-align: center">
    <img class="openpay_logo" alt="" width="80px" src="https://img.openpay.mx/plugins/openpay_logo.svg">
</div>