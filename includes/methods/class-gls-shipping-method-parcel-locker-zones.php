<?php

function gls_shipping_method_parcel_locker_zones_init()
{
	if (!class_exists('GLS_Shipping_Method_Parcel_Locker_Zones')) {
		class GLS_Shipping_Method_Parcel_Locker_Zones extends WC_Shipping_Method
		{

			/**
			 * Constructor for shipping class
			 *
			 * @access public
			 * @return void
			 */
			public function __construct($instance_id = 0)
			{
				parent::__construct();
				$this->instance_id 	  = absint( $instance_id );
				$this->id                 = GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID;
				$this->method_title       = __('GLS Parcel Locker', 'gls-shipping-for-woocommerce');
				$this->method_description = __('Parcel Shop Delivery (PSD) service that ships parcels to the GLS Locker. GLS Parcel Locker can be selected from the interactive GLS Parcel Shop and GLS Locker finder map.', 'gls-shipping-for-woocommerce');

				$this->supports = array('shipping-zones', 'instance-settings', 'instance-settings-modal');

				$this->init();

				$this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
				$this->title = isset($this->instance_settings['title']) ? $this->instance_settings['title'] : __('Delivery to GLC Parcel Locker', 'gls-shipping-for-woocommerce');
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
				$this->init_instance_settings();

				add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
			}

			public function init_form_fields()
			{
				$this->instance_form_fields = array(
					'title' => array(
						'title' => __('Title', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Title to be displayed on site', 'gls-shipping-for-woocommerce'),
						'default' => __('Delivery to GLS Parcel Shop', 'gls-shipping-for-woocommerce')
					),
					'shipping_price' => array(
						'title'       => __('Shipping Price', 'gls-shipping-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter the shipping price for this method.', 'gls-shipping-for-woocommerce'),
						'default'     => 0,
						'desc_tip'    => true,
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
				$price = $this->get_instance_option('shipping_price', '0');

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

add_action('woocommerce_shipping_init', 'gls_shipping_method_parcel_locker_zones_init');