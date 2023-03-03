<?php
/*
  Title:	Openpay QR Payment extension for WooCommerce
  Author:	Openpay
  URL:		http://www.openpay.mx
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<h3>
    <?php _e('Openpay QR', 'openpay-qr'); ?>
</h3>

<?php if(!OpenQR_Currencies::validateCurrency($this->currencies)): ?>
    <div class="inline error">
        <?php
        $countryName = OpenQR_ConfigCountries::getCountryName($this->country);
        echo OpenQR_Currencies::getCurrencyMessageError($countryName, $this->currencies);
        ?>
    </div>
<?php endif; ?>

<table class="form-table">
    <?php $this->generate_settings_html(); ?>
</table>