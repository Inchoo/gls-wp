<?php

function gls_shipping_method_init()
{
	if (!class_exists('GLS_Shipping_Method')) {
		class GLS_Shipping_Method extends WC_Shipping_Method
		{

			/**
			 * Constructor for the GLS Shipping Method.
			 *
			 * Sets up the GLS shipping method by initializing the method ID, title, and description.
			 * Also sets the default values for 'enabled' and 'title' settings and calls the init method.
			 * @access public
			 * @return void
			 */
			public function __construct()
			{
				$this->id                 = GLS_SHIPPING_METHOD_ID;
				$this->method_title       = __('GLS Delivery to Address', 'gls_croatia');
				$this->method_description = __('Parcels are shipped to the customer’s address.', 'gls_croatia');

				$this->init();

				$this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
				$this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Delivery to Address', 'gls_croatia');
			}

			/**
			 * Init settings
			 *
			 * Loads the WooCommerce settings API, sets up form fields for the shipping method,
			 * and registers an action hook for updating shipping options.
			 * @access public
			 * @return void
			 */
			function init()
			{
				// Load the settings API
				$this->init_form_fields();
				$this->init_settings();

				add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
			}

			/**
			 * Initializes form fields for the GLS Shipping Method settings.
			 *
			 * Defines the structure and default values for the settings form fields
			 * used in the WooCommerce admin panel.
			 * @access public
			 * @return void
			 */
			public function init_form_fields()
			{
				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable', 'gls_croatia'),
						'type' => 'checkbox',
						'description' => __('Enable this shipping.', 'gls_croatia'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'gls_croatia'),
						'type' => 'text',
						'description' => __('Title to be display on site', 'gls_croatia'),
						'default' => __('Delivery to Address', 'gls_croatia')
					),
					'shipping_price' => array(
						'title'       => __('Shipping Price', 'gls_croatia'),
						'type'        => 'text',
						'description' => __('Enter the shipping price for this method.', 'gls_croatia'),
						'default'     => 0,
						'desc_tip'    => true,
					),
					'supported_countries' => array(
						'title'   => __('Supported Countries', 'gls_croatia'),
						'type'    => 'multiselect',
						'class'   => 'wc-enhanced-select',
						'css'     => 'width: 400px;',
						'options' => WC()->countries->get_countries(),
						'default' => array('HR', 'CZ', 'HU', 'RO', 'SI', 'SK', 'RS'),
						'desc_tip' => true,
						'description' => __('Select countries to support for this shipping method.', 'gls_croatia'),
					),
					'main_section' => array(
						'title'       => __('GLS API Settings', 'gls_croatia'),
						'type'        => 'title',
						'description' => __('API Settings for all of the GLS Shipping Options.', 'gls_croatia'),
					),
					'client_id' => array(
						'title'       => __('Client ID', 'gls_croatia'),
						'type'        => 'text',
						'description' => __('Enter your GLS Client ID.', 'gls_croatia'),
						'desc_tip'    => true,
					),
					'username' => array(
						'title'       => __('Username', 'gls_croatia'),
						'type'        => 'text',
						'description' => __('Enter your GLS Username.', 'gls_croatia'),
						'desc_tip'    => true,
					),
					'password' => array(
						'title'       => __('Password', 'gls_croatia'),
						'type'        => 'password',
						'description' => __('Enter your GLS Password.', 'gls_croatia'),
						'desc_tip'    => true,
					),
					'country' => array(
						'title'       => __('Country', 'gls_croatia'),
						'type'        => 'select',
						'description' => __('Select the country for the GLS service.', 'gls_croatia'),
						'desc_tip'    => true,
						'options'     => array(
							'HR' => __('Croatia', 'gls_croatia'),
							'CZ' => __('Czech Republic', 'gls_croatia'),
							'HU' => __('Hungary', 'gls_croatia'),
							'RO' => __('Romania', 'gls_croatia'),
							'SI' => __('Slovenia', 'gls_croatia'),
							'SK' => __('Slovakia', 'gls_croatia'),
							'RS' => __('Serbia', 'gls_croatia'),
						),
					),
					'mode' => array(
						'title'       => __('Mode', 'gls_croatia'),
						'type'        => 'select',
						'description' => __('Select the mode for the GLS API.', 'gls_croatia'),
						'desc_tip'    => true,
						'options'     => array(
							'production' => __('Production', 'gls_croatia'),
							'sandbox'    => __('Sandbox', 'gls_croatia'),
						),
					),
					'logging' => array(
						'title'       => __('Enable Logging', 'gls_croatia'),
						'type'        => 'checkbox',
						'label'       => __('Enable logging of GLS API requests and responses', 'gls_croatia'),
						'default'     => 'no',
					),
					'client_reference_format' => array(
						'title' => __('Order Reference Format', 'gls_croatia'),
						'type' => 'text',
						'description' => __('Enter the format for order reference. Use {{order_id}} where you want the order ID to be inserted.', 'gls_croatia'),
						'default' => 'Order:{{order_id}}',
						'desc_tip' => true,
					),
					'sub_section' => array(
						'title'       => __('GLS Services', 'gls_croatia'),
						'type'        => 'title',
						'description' => __('Enable/Disable each of GLS Services for your store.', 'gls_croatia'),
					),
					'service_24h' => array(
						'title'   => __('Guaranteed 24h Service (24H)', 'gls_croatia'),
						'type'    => 'checkbox',
						'label'   => __('Enable 24H', 'gls_croatia'),
						'description' => __('Not available in Serbia.', 'gls_croatia'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'express_delivery_service' => array(
						'title'   => __('Express Delivery Service (T09, T10, T12)', 'gls_croatia'),
						'type'    => 'select',
						'label'   => __('Express Delivery Service Time', 'gls_croatia'),
						'description' => __('Availability depends on the country. Can’t be used with FDS and FSS services.', 'gls_croatia'),
						'options'     => array(
							'' => 'Disabled',
							'T09' => '09:00',
							'T10' => '10:00',
							'T12' => '12:00',
						),
						'desc_tip' => true,
					),
					'contact_service' => array(
						'title'   => __('Contact Service (CS1)', 'gls_croatia'),
						'type'    => 'checkbox',
						'label'   => __('Enable CS1', 'gls_croatia'),
						'default' => 'no',
					),
					'flexible_delivery_service' => array(
						'title'   => __('Flexible Delivery Service (FDS)', 'gls_croatia'),
						'type'    => 'checkbox',
						'label'   => __('Enable FDS', 'gls_croatia'),
						'description' => __('Can’t be used with T09, T10, and T12 services.', 'gls_croatia'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'flexible_delivery_sms_service' => array(
						'title'   => __('Flexible Delivery SMS Service (FSS)', 'gls_croatia'),
						'type'    => 'checkbox',
						'label'   => __('Enable FSS', 'gls_croatia'),
						'description' => __('Not available without FDS service.', 'gls_croatia'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'sms_service' => array(
						'title'   => __('SMS Service (SM1)', 'gls_croatia'),
						'type'    => 'checkbox',
						'label'   => __('Enable SM1', 'gls_croatia'),
						'description' => __('SMS service with a maximum text length of 130.', 'gls_croatia'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'sms_service_text' => array(
						'title'   => __('SMS Service Text', 'gls_croatia'),
						'type'    => 'text',
						'label'   => __('SM1 Service Text', 'gls_croatia'),
						'description' => __('SMS Service Text. Variables that can be used in the text of the SMS: #ParcelNr#, #COD#, #PickupDate#, #From_Name#, #ClientRef#.', 'gls_croatia'),
						'desc_tip' => true,
					),
					'sms_pre_advice_service' => array(
						'title'   => __('SMS Pre-advice Service (SM2)', 'gls_croatia'),
						'type'    => 'checkbox',
						'label'   => __('Enable SM2', 'gls_croatia'),
						'default' => 'no',
					),
					'addressee_only_service' => array(
						'title'   => __('Addressee Only Service (AOS)', 'gls_croatia'),
						'type'    => 'checkbox',
						'label'   => __('Enable AOS', 'gls_croatia'),
						'default' => 'no',
					),
					'insurance_service' => array(
						'title'   => __('Insurance Service (INS)', 'gls_croatia'),
						'type'    => 'checkbox',
						'label'   => __('Enable INS', 'gls_croatia'),
						'description' => __('Available within specific limits based on the country.', 'gls_croatia'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'phone_number' => array(
						'title'   => __('Store Phone Number', 'gls_croatia'),
						'type'    => 'text',
						'label'   => __('Store Phone Number', 'gls_croatia'),
						'description' => __('Store Phone number that will be sent to GLS as a contact information.', 'gls_croatia'),
						'desc_tip' => true,
					),
					'sub_section2' => array(
						'title'       => __('', 'gls_croatia'),
						'type'        => 'title',
					),
				);
			}

			/**
			 * Calculates the shipping rate based on the package details.
			 *
			 * Determines if the destination country is supported and applies the set shipping rate.
			 *
			 * @param array $package Details of the package being shipped.
			 */
			public function calculate_shipping($package = array())
			{
				$supported_countries = $this->get_option('supported_countries');
				$price = $this->get_option('shipping_price', '0');

				if (in_array($package['destination']['country'], $supported_countries)) {
					$rate = array(
						'id'       => $this->id,
						'label'    => $this->title,
						'cost'     => $price,
						'calc_tax' => 'per_item'
					);

					// Register the rate
					$this->add_rate($rate);
				}
			}
		}
	}
}

add_action('woocommerce_shipping_init', 'gls_shipping_method_init');
