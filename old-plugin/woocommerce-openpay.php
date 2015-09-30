<?php
/*
Plugin Name: OpenPay WooCommerce Plugin
Plugin URI: http://www.openpay.com.mx
Description: Plugin that allows you to enable Open Pay as a Payment Gateway in your site
Author: Red Core Technologies
Version: 1.0.3
Author URI: http://www.redcore.com.mx
*/
	
	add_action( 'plugins_loaded', 'woocommerce_openpay_init', 0 );
	function woocommerce_openpay_init() {
		
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		};
		
		DEFINE ('PLUGIN_DIR', plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) . '/' );
		DEFINE ('DIR_PATH', basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ )  . '/' );
		
		
		/**
		*	WooCommerce Gateway Documentation
		*	http://docs.woothemes.com/document/payment-gateway-api/
		*/
		class WC_OpenPay extends WC_Payment_Gateway {
			
			public function __construct() {
				global $woocommerce;

				$this->id			= 'openpay';//Required
				$this->method_title = __( 'Open Pay', 'woocommerce' );//Required
				$this->has_fields	 = false;//Required
				$this->icon		 = apply_filters( 'woocommerce_techprocess_icon', $woocommerce->plugin_url() . '-openpay/assets/images/icons/openpay.png' );//Required
				
				// Load the form fields.
				$this->init_form_fields(); //Required
				
				// Load the settings.
				$this->init_settings(); //Required
				
				
				$this->title				 	= $this->get_option('title');
				$this->description			 	= $this->get_option('description'); //Required
				$this->gatewayurl			 	= $this->get_option('gatewayurl');
				$this->responseurl			 	= $this->get_option('responseurl');
				$this->email			 		= $this->get_option('email');
				
				$this->openpay_private_key		= $this->get_option("openpay_private_key");
				$this->openpay_public_key		= $this->get_option("openpay_public_key");
				$this->openpay_id				= $this->get_option("openpay_id");
				$this->openpay_sandbox			= $this->get_option("openpay_sandbox") == 'no' ? false : true;
				$this->openpay_hours_before_cancel	= $this->get_option("openpay_hours");
				
				//must be lowercase
				//you MUST use the  NAME OF YOUR CLASS
				//Callback function should be named check_response but you may use a different one
				//Usage: http://myurl.com/?wc-api=WC_OpenPay 
				//  Where WC_OpenPay is the name of my class issued
				add_action('woocommerce_api_'.strtolower(get_class($this)), array($this, 'check_response') );
				add_action('woocommerce_receipt_openpay', array(&$this, 'receipt_page'));
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				
				//add_action( 'woocommerce_order_status_processing_to_cancelled', array( $this, 'restore_order_stock' ), 10, 1 );
				//add_action( 'woocommerce_order_status_completed_to_cancelled', array( $this, 'restore_order_stock' ), 10, 1 );
				add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'restore_order_stock' ), 10, 1 );
				

				if ( !$this->is_valid_for_use() ) $this->enabled = false;
			}
			
			/**
			 * Initialise Gateway Settings Form Fields
			 *
			 * @access public
			 * @return void
			 */
			function init_form_fields()
			{
				global $woocommerce;
				$order = new WC_Order( $order_id );
				$this->form_fields = array(
					'enabled' => array
								(
									'title' => __( 'Enable/Disable', 'woocommerce' ),
									'type' => 'checkbox',
									'label' => __( 'Enable Openpay', 'woocommerce' ),
									'default' => 'yes'
								),
					'openpay_sandbox' => array
								(
									'title' => __( 'Enable/Disable Sandbox', 'woocommerce' ),
									'type' => 'checkbox',
									'label' => __( 'Enable Sandbox Mode', 'woocommerce' ),
									'default' => 'no'
								),
					'title' => array
								(
									'title' => __( 'Title', 'woocommerce' ),
									'type' => 'text',
									'description' => __( 'This is the title the customer can see when checking out', 'woocommerce' ),
									'default' => __( 'Openpay', 'woocommerce' )
								),
					'description' => array
								(
									'title' => __( 'Description', 'woocommerce' ),
									'type' => 'text',
									'description' => __( 'This is the description the customer can see when checking out', 'woocommerce' ),
									'default' => __("Tarjetas de crédito y débito, Tienda de conveniencia, Transferencias interbancarias", 'woocommerce')
								),
					'email' => array
								(
									'title' => __( 'Email for send Notification', 'woocommerce' ),
									'type' => 'email',
									'description' => __( '', 'woocommerce' ),
									'default' => 'example@mail.com'
								),
					'responseurl' => array
								(
									'title' => __( 'Response URL', 'woocommerce' ),
									'type' => 'url',
									'disabled' => true,
									'description' => __( 'This is the URL which needs to be configured into the Merchant Administration Console - Response URL', 'woocommerce' ),
									'default' => __(home_url() . "/?wc-api=wc_openpay" , 'woocommerce')
								),
					// 'gatewayurl' => array
					// 			(
					// 				'title' => __( 'Gateway URL', 'woocommerce' ),
					// 				'type' => 'url',
					// 				'description' => __( 'This is obtained through the Merchant Administration Console - Gateway URL', 'woocommerce' ),
					// 				'default' => ''
					// 			),
					'openpay_id' => array
								(
									'title' => __( 'OpenPay ID', 'woocommerce' ),
									'type' => 'text',
									'description' => __( 'OpenPay Merchant ID' ),
									'default' => ''
								),
					'openpay_public_key' => array
								(
									'title' => __( 'OpenPay Public Key', 'woocommerce' ),
									'type' => 'text',
									'description' => __( 'OpenPay Public Key' ),
									'default' => ''
								),
					'openpay_private_key' => array
								(
									'title' => __( 'OpenPay Private Key', 'woocommerce' ),
									'type' => 'text',
									'description' => __( 'OpenPay  Private Key' ),
									'default' => ''
								),
						'openpay_hours' => array
						(
								'title' => __( 'Tiempo para pagar (hrs) ', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'Tiempo que tiene el cliente para pagar una orden de pago en tienda de conveniencia o banco' ),
								'default' => ''
						)
					);
			}
			
			function successful_request( $posted ) {
				global $woocommerce;
				
				$order_id_key=$posted['customerReference'];
				$order_id_key=explode("-",$order_id_key);
				$order_id=$order_id_key[0];
				$order_key=$order_id_key[1];
				$responseCode=$posted['responseCode'];
				$responseText=$posted['responseText'];
				$txnreference=$posted['txnreference'];

				$order = new WC_Order( $order_id );
			   
				error_log("order total ". $order->get_total());
				error_log("posted total ". $posted['amount']);
				
				
				if ( $order->order_key !== $order_key ) :
					echo 'Error: Order Key does not match invoice.';
					exit;
				endif;

				if ( $order->get_total() != $posted['amount'] ) {
					echo 'Error: Amount not match.';
					$order->update_status( 'on-hold', sprintf( __( 'Validation error: Techprocess amounts do not match (%s).', 'woocommerce' ), $posted['amount'] ) );
					exit;
				}

				error_log("response code" . $responseCode);
				// if TXN is approved
				if($responseCode=="00" || $responseCode=="08" || $responseCode=="77")
				{
					// Payment completed
					$order->add_order_note( __('payment completed', 'woocommerce') );

					// Mark order complete
					$order->payment_complete();

					  // Empty cart and clear session
					$woocommerce->cart->empty_cart();

					// Redirect to thank you URL
					wp_redirect( $this->get_return_url( $order ) );
					exit;
				}
				else // TXN has declined
				{	   
					// Change the status to pending / unpaid
					$order->update_status('pending', __('Payment declined', 'woothemes'));
				   
					// Add a note with the IPG details on it
					$order->add_order_note(__('Techprocess payment Failed - TransactionReference: ' . $txnreference . " - ResponseCode: " .$responseCode, 'woocommerce')); // FAILURE NOTE
				   
					// Add error for the customer when we return back to the cart
					$woocommerce->add_error(__('TRANSACTION DECLINED: ', 'woothemes') . $posted['responseText'] . "<br/>Reference: " . $txnreference);
				   
					// Redirect back to the last step in the checkout process
					wp_redirect( $woocommerce->cart->get_checkout_url());
					exit;
				}

			}

			/**
			 * Check if this gateway is enabled and available in the user's country
			 *
			 * @access public
			 * @return bool
			 */
			function is_valid_for_use() {
				if (!in_array(get_woocommerce_currency(), array('MXN'))) return false;
				return true;

			}

			/**
			 * Admin Panel Options
			 * - Options for bits like 'title' and availability on a country-by-country basis
			 *
			 * @since 1.0.0
			 */
			public function admin_options() {
				?>
		<h3><?php _e('OpenPay', 'woocommerce'); ?></h3>	   
		
		<table class="form-table">
		
		<?php
			if ( $this->is_valid_for_use() ) :
				
				
				// Generate the HTML For the settings form
				$this->generate_settings_html();
				
			else :
				?>
					<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'OpenPay está diseñado para México y solo soporta pago en pesos mexicanos. (MXN)', 'woocommerce' ); ?></p></div>
				<?php
			endif;
		
		
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="verification_code">Código de verificación</label></th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span>Open Pay Código de Verificación</span></legend>
					<label for="woocommerce_openpay_code_private">
					<input id="verification_code" type="text" disabled value="<?php echo get_option('openpay_code_private'); ?>" />
					<p class="description">Utilice este código para validar la página en OpenPay</p>
				</fieldset>
			</td>
		</tr>
		
		</table><!--/.form-table-->
		<?php
			}
			

			/**
			 * Process the payment and return the result
			 *
			 * @access public
			 * @param int $order_id
			 * @return array
			 */
			function process_payment( $order_id ){
				global $woocommerce;
				$order = new WC_Order( $order_id );

				return array
				(
					'result'	 => 'success',
					'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
				);
			}

			function receipt_page( $order )
			{
				echo '<p>'.__('Selecciona en el medio por el cual deseas realizar el pago:', 'woocommerce').'</p>';
				echo $this->generate_openpay_form( $order );
			}
			
			
			function generate_openpay_form( $order_id )
			{
				
				global $woocommerce;

				$postdata = http_build_query(
				    array(
				        'actionURL' => __(home_url()). "/?wc-api=wc_openpay",
				        'order_id' => $order_id,
				    )
				);
				
				

				$opts = array('http' =>
				    array(
				        'method'  => 'POST',
				        'header'  => 'Content-type: application/x-www-form-urlencoded',
				        'content' => $postdata
				    )
				);

				$context  = stream_context_create($opts);
				$formTemplate = file_get_contents(PLUGIN_DIR.'template/form.php', false, $context);	
				
				/*Load API KEY of openpay*/

				$sandbox_mode_string = $this->openpay_sandbox ? "true" : "false";

				$formTemplate = '<input type="hidden" name="id_openpay" id="id_openpay" value="'.$this->openpay_id.'">'
						. '<input type="hidden" id="api_key_openpay" name="api_key_openpay" value="'.$this->openpay_public_key.'">'
						. '<input type="hidden" id="api_sandbox_mode" name="api_sandbox_mode" value="'. $sandbox_mode_string . '">'
						.$formTemplate;		
				
				return $formTemplate;
			}
			
			/**
			*   This is for send email notification
			*   (From)email of the plugin, (to) email of the order
			*/
			function send_notification($order_id, $type){
				$order = new WC_Order( $order_id );
				$to_email = $order->billing_email;
				$headers = 'From: OpenPay <'.$this->email.'>' . "\r\n";
				wp_mail($to_email, $type, 'Mensaje', $headers );
			}
			
			
			/**
			*   This is the callback we declared
			*   For sanity we respond HTTP Status 200
			*   You should only print if necesary
			*/
			function check_response() {
				
				global $woocommerce;
				global $wpdb;
				
				error_log("check_response");
				
				// Load the settings.
 				$this->init_settings();
 				if($_POST){
 					error_log("check_response... " . $_POST['action']);
 					if($_POST['action']==='openpay_pay_creditcard'){
 						$this->openpay_pay_creditcard();
						$order = new WC_Order( $_POST["order-id"] );
						
						$order->update_status('on-hold', 'En espera de pago');
						$order->reduce_order_stock();
						$woocommerce->cart->empty_cart();
						//exit();
 						wp_redirect( $this->get_return_url( $order ) );
 					}elseif($_POST['action']==='openpay_pay_store'){
 						$this->openpay_pay_store();
						
						$order = new WC_Order( $_POST["order-id"] );
						
						$order->update_status('on-hold', 'En espera de pago');
						$order->reduce_order_stock();
						$woocommerce->cart->empty_cart();
 						exit();
 						wp_redirect( $this->get_return_url( $order ) );
 					}elseif ($_POST['action']==='openpay_pay_transfer') {
 						$this->openpay_pay_transfer();
						$order = new WC_Order( $_POST["order-id"] );
						
						$order->update_status('on-hold', 'En espera de pago');
						$order->reduce_order_stock();
						$woocommerce->cart->empty_cart();
 						exit();
 						wp_redirect( $this->get_return_url( $order ) );
 					}
 				}else{
 					
					header('Content-Type: application/json;charset=utf-8;');
					
					$data = json_decode(file_get_contents('php://input'));
					
					$order = new WC_Order( $data->transaction->order_id );
					
					$fetchOption = get_option('openpay_code_private');
					if(update_option('openpay_code_private', $data->verification_code)){
						$fetchOption2 = " Yes | ";
					}else{
						$fetchOption2 = " No | ";
					}
					$fetchOption2 .= get_option('openpay_code_private');
					
					$comma_delmited_list = "Response: ".print_r($data,true). "\n Data:".print_r($fetchOption,true)." ".print_r($fetchOption2,true);
					
					$fp = fopen("array.txt","a");
					if( $fp == false ){
						//do debugging or logging here
					}else{
						fwrite($fp,$comma_delmited_list);
						fclose($fp);
					}

					if($data->verification_code){
						wp_die( "Openpay IPN Request Verification Code ->".$data->verification_code, "Openpay IPN", array( 'response' => 200 ) );
					
					}else{
						
						$status = "";
						$note = "";
						$flag = 0;
						
						switch ($data->type) {
							case "charge.succeeded":
								$order->update_status('processing', "Pago recibido por " . $data->transaction->amount . " id de transacción Openpay ".  $data->transaction->id,false);
								//$order->payment_complete();
								break;				
							case "charge.cancelled":
								$order->cancel_order("La orden se cancelo por falta de pago, id Openpay  ".  $data->transaction->id);
								break;
							case "charge.refunded":
								$order->add_order_note("El pago se devolcio en Openpay, favor de marcar la orden como cancelada", false);
								//No se hace nada ya que se tiene que registrar el refund en woocomerce de forma manual						
								break;
							
						}
						
						$pays_table = $wpdb->prefix . 'woocommerce_openpay_pays';
						$wpdb->insert( 
							$pays_table, 
							array( 
								'ID_ORDER' => $data->transaction->order_id, 
								'ID_OPENPAY_CHARGE' =>  $data->transaction->id,
								'METHOD' =>  $data->transaction->method,
								'STATUS' =>  $data->transaction->status,
								'TYPE' =>  $data->type,
								'CREATED' =>  $data->event_date
							), 
							array( 
								'%d', 
								'%s', 
								'%s', 
								'%s',
								'%s'
							) 
						);
						
						
						wp_die( "Openpay IPN Request Failure", "Openpay IPN", array( 'response' => 200 ) );
					}
 				}
				
				
				
			}
			
			
			/**
			*   This is for get Code from the country
			*/
			function get_country_code($country){
				$countries = array
				(
					'AF' => 'Afghanistan',
					'AX' => 'Aland Islands',
					'AL' => 'Albania',
					'DZ' => 'Algeria',
					'AS' => 'American Samoa',
					'AD' => 'Andorra',
					'AO' => 'Angola',
					'AI' => 'Anguilla',
					'AQ' => 'Antarctica',
					'AG' => 'Antigua And Barbuda',
					'AR' => 'Argentina',
					'AM' => 'Armenia',
					'AW' => 'Aruba',
					'AU' => 'Australia',
					'AT' => 'Austria',
					'AZ' => 'Azerbaijan',
					'BS' => 'Bahamas',
					'BH' => 'Bahrain',
					'BD' => 'Bangladesh',
					'BB' => 'Barbados',
					'BY' => 'Belarus',
					'BE' => 'Belgium',
					'BZ' => 'Belize',
					'BJ' => 'Benin',
					'BM' => 'Bermuda',
					'BT' => 'Bhutan',
					'BO' => 'Bolivia',
					'BA' => 'Bosnia And Herzegovina',
					'BW' => 'Botswana',
					'BV' => 'Bouvet Island',
					'BR' => 'Brazil',
					'IO' => 'British Indian Ocean Territory',
					'BN' => 'Brunei Darussalam',
					'BG' => 'Bulgaria',
					'BF' => 'Burkina Faso',
					'BI' => 'Burundi',
					'KH' => 'Cambodia',
					'CM' => 'Cameroon',
					'CA' => 'Canada',
					'CV' => 'Cape Verde',
					'KY' => 'Cayman Islands',
					'CF' => 'Central African Republic',
					'TD' => 'Chad',
					'CL' => 'Chile',
					'CN' => 'China',
					'CX' => 'Christmas Island',
					'CC' => 'Cocos (Keeling) Islands',
					'CO' => 'Colombia',
					'KM' => 'Comoros',
					'CG' => 'Congo',
					'CD' => 'Congo, Democratic Republic',
					'CK' => 'Cook Islands',
					'CR' => 'Costa Rica',
					'CI' => 'Cote D\'Ivoire',
					'HR' => 'Croatia',
					'CU' => 'Cuba',
					'CY' => 'Cyprus',
					'CZ' => 'Czech Republic',
					'DK' => 'Denmark',
					'DJ' => 'Djibouti',
					'DM' => 'Dominica',
					'DO' => 'Dominican Republic',
					'EC' => 'Ecuador',
					'EG' => 'Egypt',
					'SV' => 'El Salvador',
					'GQ' => 'Equatorial Guinea',
					'ER' => 'Eritrea',
					'EE' => 'Estonia',
					'ET' => 'Ethiopia',
					'FK' => 'Falkland Islands (Malvinas)',
					'FO' => 'Faroe Islands',
					'FJ' => 'Fiji',
					'FI' => 'Finland',
					'FR' => 'France',
					'GF' => 'French Guiana',
					'PF' => 'French Polynesia',
					'TF' => 'French Southern Territories',
					'GA' => 'Gabon',
					'GM' => 'Gambia',
					'GE' => 'Georgia',
					'DE' => 'Germany',
					'GH' => 'Ghana',
					'GI' => 'Gibraltar',
					'GR' => 'Greece',
					'GL' => 'Greenland',
					'GD' => 'Grenada',
					'GP' => 'Guadeloupe',
					'GU' => 'Guam',
					'GT' => 'Guatemala',
					'GG' => 'Guernsey',
					'GN' => 'Guinea',
					'GW' => 'Guinea-Bissau',
					'GY' => 'Guyana',
					'HT' => 'Haiti',
					'HM' => 'Heard Island & Mcdonald Islands',
					'VA' => 'Holy See (Vatican City State)',
					'HN' => 'Honduras',
					'HK' => 'Hong Kong',
					'HU' => 'Hungary',
					'IS' => 'Iceland',
					'IN' => 'India',
					'ID' => 'Indonesia',
					'IR' => 'Iran, Islamic Republic Of',
					'IQ' => 'Iraq',
					'IE' => 'Ireland',
					'IM' => 'Isle Of Man',
					'IL' => 'Israel',
					'IT' => 'Italy',
					'JM' => 'Jamaica',
					'JP' => 'Japan',
					'JE' => 'Jersey',
					'JO' => 'Jordan',
					'KZ' => 'Kazakhstan',
					'KE' => 'Kenya',
					'KI' => 'Kiribati',
					'KR' => 'Korea',
					'KW' => 'Kuwait',
					'KG' => 'Kyrgyzstan',
					'LA' => 'Lao People\'s Democratic Republic',
					'LV' => 'Latvia',
					'LB' => 'Lebanon',
					'LS' => 'Lesotho',
					'LR' => 'Liberia',
					'LY' => 'Libyan Arab Jamahiriya',
					'LI' => 'Liechtenstein',
					'LT' => 'Lithuania',
					'LU' => 'Luxembourg',
					'MO' => 'Macao',
					'MK' => 'Macedonia',
					'MG' => 'Madagascar',
					'MW' => 'Malawi',
					'MY' => 'Malaysia',
					'MV' => 'Maldives',
					'ML' => 'Mali',
					'MT' => 'Malta',
					'MH' => 'Marshall Islands',
					'MQ' => 'Martinique',
					'MR' => 'Mauritania',
					'MU' => 'Mauritius',
					'YT' => 'Mayotte',
					'MX' => 'Mexico',
					'FM' => 'Micronesia, Federated States Of',
					'MD' => 'Moldova',
					'MC' => 'Monaco',
					'MN' => 'Mongolia',
					'ME' => 'Montenegro',
					'MS' => 'Montserrat',
					'MA' => 'Morocco',
					'MZ' => 'Mozambique',
					'MM' => 'Myanmar',
					'NA' => 'Namibia',
					'NR' => 'Nauru',
					'NP' => 'Nepal',
					'NL' => 'Netherlands',
					'AN' => 'Netherlands Antilles',
					'NC' => 'New Caledonia',
					'NZ' => 'New Zealand',
					'NI' => 'Nicaragua',
					'NE' => 'Niger',
					'NG' => 'Nigeria',
					'NU' => 'Niue',
					'NF' => 'Norfolk Island',
					'MP' => 'Northern Mariana Islands',
					'NO' => 'Norway',
					'OM' => 'Oman',
					'PK' => 'Pakistan',
					'PW' => 'Palau',
					'PS' => 'Palestinian Territory, Occupied',
					'PA' => 'Panama',
					'PG' => 'Papua New Guinea',
					'PY' => 'Paraguay',
					'PE' => 'Peru',
					'PH' => 'Philippines',
					'PN' => 'Pitcairn',
					'PL' => 'Poland',
					'PT' => 'Portugal',
					'PR' => 'Puerto Rico',
					'QA' => 'Qatar',
					'RE' => 'Reunion',
					'RO' => 'Romania',
					'RU' => 'Russian Federation',
					'RW' => 'Rwanda',
					'BL' => 'Saint Barthelemy',
					'SH' => 'Saint Helena',
					'KN' => 'Saint Kitts And Nevis',
					'LC' => 'Saint Lucia',
					'MF' => 'Saint Martin',
					'PM' => 'Saint Pierre And Miquelon',
					'VC' => 'Saint Vincent And Grenadines',
					'WS' => 'Samoa',
					'SM' => 'San Marino',
					'ST' => 'Sao Tome And Principe',
					'SA' => 'Saudi Arabia',
					'SN' => 'Senegal',
					'RS' => 'Serbia',
					'SC' => 'Seychelles',
					'SL' => 'Sierra Leone',
					'SG' => 'Singapore',
					'SK' => 'Slovakia',
					'SI' => 'Slovenia',
					'SB' => 'Solomon Islands',
					'SO' => 'Somalia',
					'ZA' => 'South Africa',
					'GS' => 'South Georgia And Sandwich Isl.',
					'ES' => 'Spain',
					'LK' => 'Sri Lanka',
					'SD' => 'Sudan',
					'SR' => 'Suriname',
					'SJ' => 'Svalbard And Jan Mayen',
					'SZ' => 'Swaziland',
					'SE' => 'Sweden',
					'CH' => 'Switzerland',
					'SY' => 'Syrian Arab Republic',
					'TW' => 'Taiwan',
					'TJ' => 'Tajikistan',
					'TZ' => 'Tanzania',
					'TH' => 'Thailand',
					'TL' => 'Timor-Leste',
					'TG' => 'Togo',
					'TK' => 'Tokelau',
					'TO' => 'Tonga',
					'TT' => 'Trinidad And Tobago',
					'TN' => 'Tunisia',
					'TR' => 'Turkey',
					'TM' => 'Turkmenistan',
					'TC' => 'Turks And Caicos Islands',
					'TV' => 'Tuvalu',
					'UG' => 'Uganda',
					'UA' => 'Ukraine',
					'AE' => 'United Arab Emirates',
					'GB' => 'United Kingdom',
					'US' => 'United States',
					'UM' => 'United States Outlying Islands',
					'UY' => 'Uruguay',
					'UZ' => 'Uzbekistan',
					'VU' => 'Vanuatu',
					'VE' => 'Venezuela',
					'VN' => 'Viet Nam',
					'VG' => 'Virgin Islands, British',
					'VI' => 'Virgin Islands, U.S.',
					'WF' => 'Wallis And Futuna',
					'EH' => 'Western Sahara',
					'YE' => 'Yemen',
					'ZM' => 'Zambia',
					'ZW' => 'Zimbabwe',
				);
				
				$code = "";
				
				foreach ($countries  as $k => $v) {
					if($v == $country){
						$code = $a[$k];
						break;
					}
				}
				
				return $code;
			}
			
			function openpay_pay_creditcard(){
				global $woocommerce;
				global $wpdb;
				require_once 'lib/Openpay.php'; 
				$order = new WC_Order( $_POST["order-id"] );
				
				$openpay = Openpay::getInstance($this->openpay_id, $this->openpay_private_key);
				Openpay::setSandboxMode($this->openpay_sandbox);
				
				//Custumer exist?
				$customers_table = $wpdb->prefix . 'woocommerce_openpay_customers';
				$customers_data = $wpdb->get_results('SELECT * FROM '.$customers_table.' WHERE ID_USER='.$order->user_id );
				if($customers_data){
					//exist Customer
					$customer = $openpay->customers->get($customers_data[0]->ID_OPENPAY_CUSTOMER);
				}else{
					//not exist Customer
					$customerData = array(
						 'external_id' => $order->user_id == null ? null : $order->user_id,
						 'name' => $order->billing_first_name,
						 'last_name' => $order->billing_last_name,
						 'email' => $order->billing_email,
						 'requires_account' => false,
						 'phone_number' => $order->billing_phone,
						 'address' => array(
							 'line1' => $order->billing_address_1,
							 'line2' => $order->billing_address_2,
							 'line3' => '',
							 'state' => $order->billing_state,
							 'city' => $order->billing_city,
							 'postal_code' => $order->billing_postcode,
							 'country_code' => $order->billing_country
						  )
					   );
					   
					$customer = $openpay->customers->add($customerData);
					
					$wpdb->insert( 
						$customers_table, 
						array( 
							'ID_USER' => $order->user_id, 
							'ID_OPENPAY_CUSTOMER' =>  $customer->id
						), 
						array( 
							'%d', 
							'%s' 
						) 
					);
					
				}
				
				//Getting order name and quantity
				foreach($order->get_items() as $item) 
				{
					$json['items'][] = array (
						'name' => $item['name'], // Here appear the name of the product
						'quantity' => $item['qty']); // here the quantity of the product
				}
				
				$item_string = json_encode($json).'';
				
				if(strlen($item_string)> 250){
					$item_string = substr($item_string,0,249);
				}

				$chargeData = array(
					'method' => 'card',
					'source_id' => $_POST["token_id"],
					'amount' => (float)$order->get_total(),
					'order_id' => $_POST["order-id"],
					'description' => $item_string, 
					'device_session_id' => $_POST["deviceIdHiddenFieldName"]
				);

				//Charge with customer
				//$charge = $openpay->charges->create($chargeData);
				setcookie("OpenpayError", false, time()+3600);
				try{
					$charge = $customer->charges->create($chargeData);
				}catch (exception $e) {
// 					setcookie("OpenpayError", true, time()+3600); 
					$location = $_SERVER['HTTP_REFERER'];
					wc_add_notice( __('', 'woothemes') . 'La tarjeta fue declinada, por favor verifique la información o intente con otra tarjeta', 'error' );
					wp_redirect( $location, 302 ); exit;
				}
				
				setcookie("OpenpayError", false, time()+3600);
				echo "se cobró con tarjeta de crédito";
			}
			
			protected function _addHoursToTime($time, $hours){
				$seconds = $hours * 60 * 60;
				$newTime = $time + $seconds;
				return $newTime;
			}
			
			function openpay_pay_store(){
				global $woocommerce;
				require_once 'lib/Openpay.php'; 
				$order = new WC_Order( $_POST["order-id"] );
				
				$openpay = Openpay::getInstance($this->openpay_id, $this->openpay_private_key);
				Openpay::setSandboxMode($this->openpay_sandbox);
				
				
			
				
				$chargeData = array(
					'method' => 'store',
					'amount' => (float)$order->get_total(),
					'order_id' => $_POST["order-id"],
					'description' => "Compra con Open Pay: " .$order->id );
				
				if($this->openpay_hours_before_cancel && is_numeric($this->openpay_hours_before_cancel)){
					$chargeData['due_date'] = date('c', $this->_addHoursToTime(time(), $this->openpay_hours_before_cancel));
				}
				
				$charge = $openpay->charges->create($chargeData);
				
				/*Date manager*/
				$date = $this->getDateasString($charge->operation_date);
				$dueDate = '';
				
				if ($charge->due_date) {
					$dueDate = '<p><strong>Fecha límite de pago: </strong>'. $this->getDateasString($charge->due_date) .'</p>';
				}
				
				
				echo '<meta charset="UTF-8">';
				echo '<link href="'.$woocommerce->plugin_url() . '-openpay/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css">';
				echo '<link href="'.$woocommerce->plugin_url() . '-openpay/assets/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css">';
				echo '<link href="'.$woocommerce->plugin_url() . '-openpay/assets/css/jumbotron-narrow.css" rel="stylesheet" type="text/css">';
				echo '<link href="'.$woocommerce->plugin_url() . '-openpay/assets/css/print.css" rel="stylesheet" type="text/css" media="print">';
				echo '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js" ></script>';				
				echo '<script src="'.$woocommerce->plugin_url() . '-openpay/assets/js/bootstrap.min.js" ></script>';
				
				echo '<div class="container">
      <div class="row">
		<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
			<img class="img-responsive center-block" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/logo.png" alt="Logo">
		</div>	
		<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">	
			<p class="Logo_paynet">Servicio a pagar</p>
			<img class="img-responsive center-block Logo_paynet" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/paynet_logo.png" alt="Logo">
		</div>	
      </div>

      <div class="row data">
	  
    	<div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
			<div class="Big_Bullet">
				<span></span>
			</div>
			<h1><strong>Código de barra</strong></h1> 
			<div class="col-lg-12 datos_pago">
				<!--<h4>30 de Noviembre 2014, a las 2:30 AM</h4>-->
				<img width="300" src="'. $charge->payment_method->barcode_url .'" alt="Código de Barras">
				<span>'. $charge->payment_method->reference.'</span>
				<br/>
				<p>En caso de que el escáner no sea capaz de leer el código de barras, escribir la referencia tal como se muestra.</p>
			</div>
        
        </div>
        <div class="col-xs-12 col-sm-4 col-md-4 col-lg-4 data_amount">
        	<h2>Total a pagar</h2>
            <h2 class="amount">$'. (float)$charge->amount.'<small> MXN</small></h2>
            <h2 class="S-margin">+8 pesos por comisión</h2>
        </div>
			
			
      </div>
	  
      <div class="row data">
		
    	<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
			<div class="Big_Bullet">
				<span></span>
			</div>
        	<h1><strong>Detalles de la compra</strong></h1> 
			<div class="col-lg-12 datos_tiendas">
			  <p><strong>Orden: </strong> '. $charge->order_id.'</p>
			  <p><strong>Fecha y hora: </strong>'. $date.'</p>
			' . $dueDate. '
			  <p><strong>Correo electrónico: </strong>'.$order->billing_email.'</p>
			</div>			
        </div>
			
			
      </div>
	  
      <div class="row data">
		
    	<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
			<div class="Big_Bullet">
				<span></span>
			</div>
			<h1><strong>Como realizar el pago</strong></h1> 
            <ol style="margin-left: 30px;">
            	<li>Acude a cualquier tienda afiliada</li>
                <li>Entrega al cajero el código de barras y menciona que realizarás un pago de servicio Paynet</li>
                <li>Realizar el pago en efectivo por $'. (float)$charge->amount.' MXN (más $8 pesos por comisión)</li>
                <li>Conserva el ticket para cualquier aclaración</li>
            </ol>
            <small>'.$this->description.'</small>		
        </div>
    	<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
			<h1><strong>Instrucciones para el cajero</strong></h1> 
            <ol>
            	<li>Ingresar al menú de Pago de Servicios</li>
                <li>Seleccionar Paynet</li>
                <li>Escanear el código de barras o ingresar el núm. de referencia</li>
                <li>Ingresa la cantidad total a pagar</li>
                <li>Cobrar al cliente el monto total más la comisión de $8 pesos</li>
                <li>Confirmar la transacción y entregar el ticket al cliente</li>
            </ol>
            <small>Para cualquier duda sobre como cobrar, por favor llamar al teléfono 01 800 300 08 08 en un horario de 8am a 9pm de lunes a domingo</small>
        </div>
			
			
      </div>
	  
      <div class="row marketing">

        <div class="col-lg-12" style="text-align:center;">
			<img width="50" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/7eleven.png" alt="7elven">
			<img width="90" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/extra.png" alt="7elven">
			<img width="90" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/farmacia_ahorro.png" alt="7elven">
			<img width="150" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/benavides.png" alt="7elven">
			<div class="link_tiendas">¿Quieres pagar en otras tiendas? visita: <a target="_blank" href="http://www.openpay.mx/tiendas-de-conveniencia.html">www.openpay.mx/tiendas</a></div>
        </div>
		<div class="col-lg-12" style="text-align:center; margin-top:5px;">
			  <a type="button" class="btn btn-success btn-lg" onclick="window.print();">Imprimir</a>
			  <a type="button" class="btn btn-success btn-lg" href="'.home_url().'">Sigue comprando</a>
			  
		</div>	  
      </div>
	  


      <div class="footer">
        <img class="img-responsive center-block" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/powered_openpay.png" alt="Powered by Openpay">
      </div>

    </div> <!-- /container -->';
				

			}
			
			
			function getDateasString($openpayDate){
				setlocale(LC_ALL, "es_ES");
				
				$date = str_replace("T","",$openpayDate);
// 				$date = substr($date, 0, -6);
// 				$date = new DateTime($date);
				$date = strftime('%d %B  %Y, %I:%M %p', strtotime(substr($date, 0, -6)));
				return $date;
			}
			
			function openpay_pay_transfer(){
				global $woocommerce;
				require_once 'lib/Openpay.php'; 
				$order = new WC_Order( $_POST["order-id"] );
				
				$openpay = Openpay::getInstance($this->openpay_id, $this->openpay_private_key);
				Openpay::setSandboxMode($this->openpay_sandbox);
				
				//agregar due date
				$chargeData = array(
					'method' => 'bank_account',
					'amount' => (float)$order->get_total(),
					'description' => "Compra con Open Pay: " .$order->id,
					'order_id' =>	$_POST["order-id"]	);

				if($this->openpay_hours_before_cancel && is_numeric($this->openpay_hours_before_cancel)){				
					$chargeData['due_date'] = date('c', $this->_addHoursToTime(time(), $this->openpay_hours_before_cancel));
				}
				
				$charge = $openpay->charges->create($chargeData);
				
				/*Date manager*/
				$date = $this->getDateasString($charge->operation_date); 
				$dueDate = '';
				//str_replace("T","",$charge->operation_date);
				//$date = substr($date, 0, -6);
				//$date = new DateTime($date);
				//$date = date_format($date, 'd F Y, g:i A');
				
				if ($charge->due_date) {
					$dueDate = '<strong>Fecha límite de pago: </strong> '. $this->getDateasString($charge->due_date);
				}
				echo '<meta charset="UTF-8">';
				echo '<link href="'.$woocommerce->plugin_url() . '-openpay/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css">';
				echo '<link href="'.$woocommerce->plugin_url() . '-openpay/assets/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css">';
				echo '<link href="'.$woocommerce->plugin_url() . '-openpay/assets/css/jumbotron-narrow.css" rel="stylesheet" type="text/css">';
				echo '<link href="'.$woocommerce->plugin_url() . '-openpay/assets/css/print.css" rel="stylesheet" type="text/css" media="print">';
				echo '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js" ></script>';				
				echo '<script src="'.$woocommerce->plugin_url() . '-openpay/assets/js/bootstrap.min.js" ></script>';
				
				
				echo '<div class="container">
      <div class="row header">
		<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
			<img style="background-color: #fff;" class="img-responsive center-block" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/logo.png" alt="Logo">
		</div>	
		<div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">	
			<p class="Yellow2">Esperamos tu pago</p>
		</div>	
      </div>

      <div class="row">
			<div class="col-xs-9 col-sm-8 col-md-8 col-lg-8">
				<h1><strong>Cantidad a pagar</strong></h1>
				<h2 class="amount">$'. (float)$charge->amount.'<small> MXN</small></h2>
				<h1><strong>Fecha:</strong></h1>
				<h1>'. $date.'</h1>'
				. $dueDate .		
			'</div>
			<div class="col-xs-3 col-sm-4 col-md-4 col-lg-4">
				<a href="http://www.openpay.mx/bancos.html" target="_blank"><img class="img-responsive spei" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/spei.gif"  alt="SPEI"></a>
			</div>
      </div>

      <div class="row marketing">
		<h1 style="padding-bottom:20px;"><strong>Datos para transferencia electrónica</strong></h1>
        <div class="col-lg-12 datos">
          <p><strong>Nombre del banco: </strong>'.$charge->payment_method->bank.'</p>
          <p><strong>CLABE: </strong>'. $charge->payment_method->clabe .'</p>
          <p><strong>Referencia numérica: </strong>'. $charge->payment_method->name .'</p>
          <p><strong>Orden: </strong>'. $charge->order_id.'</p>
        </div>

        <div class="col-lg-12">
          <p>Tu correo: <strong>'.$order->billing_email.'</strong></p>
          <p>¿Tienes alguna dudas o problema? Llámanos al teléfono</p>
          <h4>01 800 681 8161</h4>
          <p>O escríbenos a</p>
		  <h4>soporte@openpay.mx</h4>
		  <a type="button" class="btn btn-success btn-lg" onclick="window.print();">Imprimir</a>
		  <a type="button" class="btn btn-success btn-lg" href="'.home_url().'">Sigue comprando</a>
        </div>
      </div>

      <div class="footer">
        <img class="img-responsive center-block" src="'.$woocommerce->plugin_url() . '-openpay/assets/images/powered_openpay.png" alt="Powered by Openpay">
      </div>

    </div> <!-- /container -->';

				
			}
			
			public function restore_order_stock( $order_id ) {
				$order = new WC_Order( $order_id );
			
				if ( ! get_option('woocommerce_manage_stock') == 'yes' && ! sizeof( $order->get_items() ) > 0 ) {
					return;
				}
			
				foreach ( $order->get_items() as $item ) {
			
					if ( $item['product_id'] > 0 ) {
						$_product = $order->get_product_from_item( $item );
			
						if ( $_product && $_product->exists() && $_product->managing_stock() ) {
			
							$old_stock = $_product->stock;
			
							$qty = apply_filters( 'woocommerce_order_item_quantity', $item['qty'], $this, $item );
			
							$new_quantity = $_product->increase_stock( $qty );
			
							do_action( 'woocommerce_auto_stock_restored', $_product, $item );
			
							$order->add_order_note( sprintf( __( 'Item #%s stock incremented from %s to %s.', 'woocommerce' ), $item['product_id'], $old_stock, $new_quantity) );
			
							$order->send_stock_notifications( $_product, $new_quantity, $item['qty'] );
						}
					}
				}
			} // End restore_order_stock()
			
				
		}
		
		
	}
	
	
	function openpay__create_plugin_tables()
	{
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		$table_customers = $wpdb->prefix . 'woocommerce_openpay_customers';

		$sql = "CREATE TABLE $table_customers (
				ID_USER bigint(20) NOT NULL,
				ID_OPENPAY_CUSTOMER varchar(200) NOT NULL
		)";
		
		dbDelta( $sql );
		
		$table_pays = $wpdb->prefix . 'woocommerce_openpay_pays';

		$sql = "CREATE TABLE $table_pays (
				ID_ORDER bigint(20) NOT NULL,
				ID_OPENPAY_CHARGE varchar(200) NOT NULL,
				METHOD varchar(200) NOT NULL,
				STATUS varchar(200) NOT NULL,
				TYPE varchar(200) NOT NULL,
				CREATED varchar(200) NOT NULL
		)";
		
		dbDelta( $sql );
		
	}

	
	/**
	*	We define Open Pay as a legible option for a Payment Gateway in Woo commerce
	*/
	function woocommerce_openpay_add_gateway( $methods )
	{
		$methods[] = 'WC_OpenPay';
		return $methods;

	}
	
	/**
	*	Function after change status to 
	*/
	function openpay_pay_refunded($order_id) {
		
		global $woocommerce;
		global $wpdb;
		require_once 'lib/Openpay.php'; 
		
		$wc = new WC_OpenPay();
				
		$openpay = Openpay::getInstance($wc->openpay_id, $wc->openpay_private_key);
		Openpay::setSandboxMode($this->openpay_sandbox);
		
		$order = new WC_Order( $order_id );
		
		$customers_table = $wpdb->prefix . 'woocommerce_openpay_customers';
		$customers_data = $wpdb->get_results('SELECT * FROM '.$customers_table.' WHERE ID_USER='.$order->user_id );

		$customer = $openpay->customers->get($customers_data[0]->ID_OPENPAY_CUSTOMER);
		
		$customers_pays = $wpdb->prefix . 'woocommerce_openpay_pays';
		$pay_data = $wpdb->get_results('SELECT * FROM '.$customers_pays." WHERE ID_ORDER='".$order_id."' AND METHOD='card' AND STATUS='completed' " );
		
		$charge = $customer->charges->get($pay_data[0]->ID_OPENPAY_CHARGE);
		
		$refundData = array('description' => 'devolución' );
		$charge->refund($refundData);
	
		//wp_die( "Openpay IPN Request Verification Code ->".var_dump($charge), "Openpay IPN", array( 'response' => 200 ) );
	
	}
			
	add_filter('woocommerce_payment_gateways', 'woocommerce_openpay_add_gateway' );
	add_action('admin_action_openpay_pay_creditcard','openpay_pay_creditcard');		
	add_action('admin_action_openpay_pay_store','openpay_pay_store');		
	add_action('admin_action_openpay_pay_transfer','openpay_pay_transfer');		
	add_action("wp_enqueue_scripts", "openpay_javascript_enqueue", 11);
	add_action('woocommerce_order_status_refunded', 'openpay_pay_refunded');
	
	register_activation_hook( __FILE__, 'openpay__create_plugin_tables' );
	
	function openpay_javascript_enqueue() {
		wp_deregister_script( 'jquery' ); 
		wp_register_script('jquery', "//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js", false);
		wp_enqueue_script('jquery');
		
		
		wp_register_script('openpay', "https://openpay.s3.amazonaws.com/openpay.v1.min.js", false);
		wp_enqueue_script('openpay');
		
		wp_register_script('openpay-data', "https://openpay.s3.amazonaws.com/openpay-data.v1.min.js", false);
		wp_enqueue_script('openpay-data');
		
		wp_register_script('openpay-forms',PLUGIN_DIR.'assets/js/wc.openpay.js','jquery-ui');
		wp_enqueue_script('openpay-forms');
	}
	
	
	//$GLOBALS['wc_openpay'] = new WC_OpenPay();

	
	
?>
