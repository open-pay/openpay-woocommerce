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
![Woocommerce Currency](https://github.com/open-pay/openpay-wordpress/install_images/WooCommerceSettings.png "Woocommerce Currency")

  * Download  [woocommerce-openpay.zip](https://github.com/open-pay/openpay-wordpress/bin/woocommerce-openpay.zip"woocommerce-openpay.zip")  plugin.
  * Install the plugin.
  * On Wordpress -> Plugins -> Add New -> Upload and choose the woocommerce-openpay.zip downloaded in before step, and click on Install Now button.
![Add New ](https://github.com/open-pay/openpay-wordpress/install_images/PluginsAddNew.png "Add New ")
![Upload](https://github.com/open-pay/openpay-wordpress/install_images/PluginsInstall.png "Upload")

 * Activate plugin, by click on Activate Plugin link.
![Activate](https://github.com/open-pay/openpay-wordpress/install_images/PluginActivate.png "Activate")

Enable & configure Openpay on Woocomerce
------------
* Go to Woocommerce->Settings -> Checkout and select Openpay, click en settings

![Activate](https://github.com/open-pay/openpay-wordpress/install_images//WooCommerceSettingsOpenpay.png "Activate")
*  Set up your OpenPay ID,  Public and Private key and save changes. Get Openpay Keys from the Openpay dashboard.


Configure a Webhook
------------
 Webhooks can be configured on the Openpay dashboard.

* Click settings and click “Configuraciones”.
![Webhooks](https://github.com/open-pay/openpay-wordpress/install_images/webhooks.png "Webhooks")
* Click on “Agregar” button in the webhooks sections.

![Add webhook](https://github.com/open-pay/openpay-wordpress/install_images/OpenPayWHAdd.png "Add webhook")
* Enter the "Response URL",  "Response URL" is in Woocommerce Openpay settings.
* Activatee the webhook, in Woocomerce-> checkout-> Openpay settings, copy the "Código de verificación",  on Openpay dashboard clicking the green button “verificar” and paste the "Código de verificación".
![Activate webhook](https://github.com/open-pay/openpay-wordpress/install_images/OpenPayWHActivate.png "Activate webhook")


