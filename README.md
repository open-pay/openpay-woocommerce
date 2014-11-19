Openpay WordPress plugin
===========

WordPress Plugin for Openpay API services (version 1.0.0)

This is a plugin implementing the payment services for Openpay at openpay.mx


Requirements
------------
* Wordpress 
* Woocomerce activated

Installation
------------
* Install and configure Woocommerce Currency  to  Mexican Peso($) and save "save changes".
![Woocommerce Currency](https://raw.githubusercontent.com/open-pay/openpay-wordpress/master/install_images/WooCommerceSettings.png)

  * Download  the latest version of the plugin from the bin directory [bin](https://github.com/open-pay/openpay-wordpress/blob/master/bin).
  * Install the plugin.
  * On Wordpress -> Plugins -> Add New -> Upload and choose the woocommerce-openpay.zip downloaded in before step, and click on Install Now button.
![Add New ](https://raw.githubusercontent.com/open-pay/openpay-wordpress/master/install_images/PluginsAddNew.png)
![Upload](https://raw.githubusercontent.com/open-pay/openpay-wordpress/master/install_images/PluginsInstall.png)

 * Activate plugin, by click on Activate Plugin link.
![Activate](https://raw.githubusercontent.com/open-pay/openpay-wordpress/master/install_images/PluginActivate.png)

Enable & configure Openpay on Woocomerce
------------
* Go to Woocommerce->Settings -> Checkout and select Openpay, click en settings

![Activate](https://raw.githubusercontent.com/open-pay/openpay-wordpress/master/install_images/WooCommerceSettingsOpenpay.png)
*  Set up your OpenPay ID,  Public and Private key and save changes. Get Openpay Keys from the Openpay dashboard.


Configure a Webhook
------------
 Webhooks can be configured on the Openpay dashboard.

* Click settings and click “Configuraciones”.
![Webhooks](https://raw.githubusercontent.com/open-pay/openpay-wordpress/master/install_images/webhooks.png)
* Click on “Agregar” button in the webhooks sections.
![Add webhook](https://raw.githubusercontent.com/open-pay/openpay-wordpress/master/install_images/OpenPayWHAdd.png)

* Enter the "Response URL",  "Response URL" is in Woocommerce Openpay settings.
* Activate the webhook, in Woocomerce-> checkout-> Openpay settings, copy the "Código de verificación",  on Openpay dashboard clicking the green button “verificar” and paste the "Código de verificación".
![Activate webhook](https://raw.githubusercontent.com/open-pay/openpay-wordpress/master/install_images/OpenPayWHActivate.png)

