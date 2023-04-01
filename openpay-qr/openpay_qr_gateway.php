<?php
require_once("includes/OpenQR_Loader.php");
class Openpay_QR extends WC_Payment_Gateway
{
    private $logger;

    private $country;
    private $sandbox;
    private $currencies;
    private $is_sandbox;
    private $merchant_id;
    private $SK;


    /**
     * Constructor for the gateway.
     */
    public function __construct() {

        $this->id                 = 'openpay-qr';
        $this->icon               = apply_filters('woocommerce_offline_icon', '');
        $this->has_fields         = false;
        $this->method_title = __('Openpay QR', 'openpay-qr');
        $this->method_description = __( 'Recibe pagos a través de códigos QR. Orders are marked as "on-hold" when received.', 'openpay-qr' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        $this->logger = wc_get_logger();

        // Define user set variables
        $this->country      = $this->get_option( 'country');
        $this->sandbox     = $this->get_option( 'sandbox','yes');
        $this->currencies = OpenQR_Currencies::getCurrencies($this->country);

        /*$this->is_sandbox = strcmp($this->settings['sandbox'], 'yes') == 0;
        $this->merchant_id = $this->is_sandbox ? $this->get_option( 'sandbox_merchant_id') : $this->get_option( 'production_merchant_id');
        $this->SK = $this->is_sandbox ? $this->get_option( 'sandbox_SK') : $this->get_option( 'production_SK');*/


        $this->title        = "Openpay QR";
        $this->description  = "Genera tu código de pago QR";


        $this->logger->info(json_encode($this->currencies));
        $this->logger->info(json_encode(OpenQR_Currencies::validateCurrency($this->currencies)));


        if (!OpenQR_Currencies::validateCurrency($this->currencies)) {
            $this->enabled = false;
            $this->settings['enabled'] = 'no';
        }

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action('admin_enqueue_scripts', array($this, 'openpay_qr_admin_enqueue'), 10, 2);
    }


    /**
     * EN - Initialize Gateway Settings Form Fields
     * ES - Inicializa las configuraciones del formulario del método de pago.
     */
    public function init_form_fields() {
        $this->form_fields = (array) new OpenQR_ConfigForm();
    }

    /**
     * EN - Process Gateway Settings From Config Form when clicking save changes.
     * ES - Procesa las configuraciones del método de pago al guardar los cambios.
     */
    public function process_admin_options() {
        parent::process_admin_options();
        OpenQR_ConfigCredentials::validateCredentials($this->get_post_data());
    }

    /**
     * EN - Automatic validatión function -> validate_{seeting_name}_field.
     * ES - Función automatica de validación -> validate_{seeting_name}_field.
     */
    /*
     * public function validate_due_date_field($key,$value){
        if (!preg_match( '/^[1-9]{1}[0-9]{0,2}$/', $value )) {
            WC_Admin_Settings::add_error( 'Tiempo de vencimiento inválido, ingresa un valor numérico en un rango [1,999].' );
            $value = '1';
        }
        return $value;
    }
    */

    /**
     * EN - Load a template overwriting default template for Gateway settings form.
     * ES - Carga un template que sobreescribe el template por defecto para el formulario de configuraciones del método de pago.
     */
    public function admin_options() {
        include_once('templates/OpenQR_admin.php');
    }

    /**
     * EN - Enqueue the admin scripst to be loaded on gateway class constructor method.
     * ES - Encola los scripts para cargarlos en el metodo constructor del método de pago.
     */
    public function openpay_qr_admin_enqueue($hook) {
        wp_enqueue_script('openpay_qr_admin_form', plugins_url('admin/js/admin.js', __FILE__), array('jquery'), '1.0.0', true);
    }

    /**
     * EN - Process the payment and return the result. Redirect to confirmation page if process end successfully.
     * ES - Procesa el pago y devuelve un resultado.  Redirecciona a la página de confirmación si el proceso resulta con éxito.
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
        global $woocommerce;
        //$this->logger->info( json_encode( $order->get_total() ));
        // Create Openpay Instance y enviarla al handler
        switch ($this->country){
            case "CO":
                $chargeResult = new OpenQR_ChargeHAndlerCO($order_id);
                Break;
            case "PE":
                $chargeResult = new OpenQR_ChargeHandlerPe($order_id);
                break;
        }

        if (function_exists('wc_add_notice')) {
            wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), $notice_type = 'error');
        } else {
            $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
        }


        // Return thankyou redirect
       /* return array(
            'result' 	=> 'success',
            'redirect'	=> $this->get_return_url( $order )
        );*/
    }

}