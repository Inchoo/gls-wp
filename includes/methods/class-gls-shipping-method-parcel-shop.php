<?php

function gls_shipping_method_parcel_shop_init()
{
	if (!class_exists('GLS_Shipping_Method_Parcel_Shop')) {
		class GLS_Shipping_Method_Parcel_Shop extends WC_Shipping_Method
		{

			/**
			 * Constructor for shipping class
			 *
			 * @access public
			 * @return void
			 */
			public function __construct()
			{
				$this->id                 = GLS_SHIPPING_METHOD_PARCEL_SHOP_ID;
				$this->method_title       = __('GLS Parcel Shop', 'gls-shipping-for-woocommerce');
				$this->method_description = __('Parcel Shop Delivery (PSD) service that ships parcels to the GLS Parcel Shop. GLS Parcel Shop can be selected from the interactive GLS Parcel Shop and GLS Locker finder map.', 'gls-shipping-for-woocommerce');

				$this->init();

				$this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
				$this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Delivery to GLC Parcel Shop', 'gls-shipping-for-woocommerce');
			}

			/**
			 * Init settings
			 *
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

			public function init_form_fields()
			{
				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable', 'gls-shipping-for-woocommerce'),
						'type' => 'checkbox',
						'description' => __('Enable this shipping globally.', 'gls-shipping-for-woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Title to be display on site', 'gls-shipping-for-woocommerce'),
						'default' => __('Delivery to GLS Parcel Shop', 'gls-shipping-for-woocommerce')
					),
					'shipping_price' => array(
						'title'       => __('Shipping Price', 'gls-shipping-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter the shipping price for this method.', 'gls-shipping-for-woocommerce'),
						'default'     => 0,
						'desc_tip'    => true,
					),
					'supported_countries' => array(
						'title'   => __('Supported Countries', 'gls-shipping-for-woocommerce'),
						'type'    => 'multiselect',
						'class'   => 'wc-enhanced-select',
						'css'     => 'width: 400px;',
						'options' => array(
							'HR' => __('Croatia', 'gls-shipping-for-woocommerce'),
							'CZ' => __('Czech Republic', 'gls-shipping-for-woocommerce'),
							'HU' => __('Hungary', 'gls-shipping-for-woocommerce'),
							'RO' => __('Romania', 'gls-shipping-for-woocommerce'),
							'SI' => __('Slovenia', 'gls-shipping-for-woocommerce'),
							'SK' => __('Slovakia', 'gls-shipping-for-woocommerce'),
							'RS' => __('Serbia', 'gls-shipping-for-woocommerce'),
						),
						'default' => array('HR', 'CZ', 'HU', 'RO', 'SI', 'SK'),
						'desc_tip' => true,
						'description' => __('Select countries to support for this shipping method.', 'gls-shipping-for-woocommerce'),
					),
				);
			}

			/**
			 * Calculate Shipping Rate
			 *
			 * @access public
			 * @param array $package
			 * @return void
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
						'calc_tax' => 'per_order'
					);

					// Register the rate
					$this->add_rate($rate);
				}
			}
		}
	}
}

add_action('woocommerce_shipping_init', 'gls_shipping_method_parcel_shop_init');
