<?php
class OpenQR_ConfigForm
{
    public function __construct()
    {
        $this->enabled = array(
            'type' => 'checkbox',
            'title' => __('Habilitar/Deshabilitar', 'openpay-qr'),
            'label' => __('Habilitar', 'openpay-qr'),
            'default' => 'yes'
        );

        $this->sandbox = array(
        'type' => 'checkbox',
        'title' => __('Modo sandbox', 'openpay-qr'),
        'label' => __('Habilitar', 'openpay-qr'),
        'description' => __( 'Activa y desactiva el modo de pruebas (Modo Sandbox)', 'openpay-qr' ),
        'desc_tip'    => true,
        'default' => 'yes'
        );

        $this->country = array(
            'type' => 'select',
            'title' => __('País', 'openpay-qr'),
            'default' => 'CO',
            'options' => array(
                'CO' => 'Colombia',
                'PE' => 'Perú'
            )
        );

        $this->sandbox_merchant_id = array(
            'type' => 'text',
            'title' => __('ID de comercio sandbox', 'openpay-qr'),
            'description' => __('Obten tus llaves de prueba de tu cuenta Openpay.', 'openpay-qr'),
            'default' => __('', 'openpay-qr')
        );

        $this->sandbox_SK = array(
            'type' => 'text',
            'title' => __('Llave secreta de sandbox', 'openpay-qr'),
            'description' => __('Obten tus llaves de prueba de tu cuenta Openpay ("sk_").', 'openpay-qr'),
            'default' => __('', 'openpay-qr')
        );

        $this->sandbox_PK = array(
            'type' => 'text',
            'title' => __('Llave pública de sandbox', 'openpay-qr'),
            'description' => __('Obten tus llaves de prueba de tu cuenta Openpay ("pk_").', 'openpay-qr'),
            'default' => __('', 'openpay-qr')
        );

        $this->production_merchant_id = array(
            'type' => 'text',
            'title' => __('ID de comercio producción', 'openpay-qr'),
            'description' => __('Obten tus llaves de producción de tu cuenta Openpay.', 'openpay-qr'),
            'default' => __('', 'openpay-qr')
        );

        $this->production_SK = array(
            'type' => 'text',
            'title' => __('Llave secreta de producción', 'openpay-qr'),
            'description' => __('Obten tus llaves de producción de tu cuenta Openpay ("sk_").', 'openpay-qr'),
            'default' => __('', 'openpay-qr')
        );

        $this->production_PK = array(
            'type' => 'text',
            'title' => __('Llave pública de producción', 'openpay-qr'),
            'description' => __('Obten tus llaves de producción de tu cuenta Openpay ("pk_").', 'openpay-qr'),
            'default' => __('', 'openpay-qr')
        );

        $this->expiration_time = array(
        'type' => 'text',
        'title' => __('Tiempo de vencimiento', 'openpay-qr'),
        'description' => __('tiempo de vencimiento a partir de la creación del QR (Días).<br> 
                            <strong>Ejemplo:</strong> 1 día = Creado 20/03/2023 - Vence 21/03/2023 00:00 hrs.<br>
                            <strong>Ejemplo:</strong> 2 días = Creado 20/03/2023 - Vence 22/03/2023 00:00 hrs.', 'openpay-qr'),
        'default' => __('1', 'openpay-qr')
        );
    }
}