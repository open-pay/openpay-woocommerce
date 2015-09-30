<?php
/*  
  Title:	Openpay Payment extension for WooCommerce
  Author:	Federico Balderas
  URL:		http://foograde.com
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
?>

<h3>
    <?php _e('Openpay Stores', 'woothemes'); ?>
</h3>

<p><?php _e('Openpay works by adding credit card fields on the checkout and then sending the details to Openpay for verification.', 'woothemes'); ?></p>

<table class="form-table">
    <?php $this->generate_settings_html(); ?>
</table>
