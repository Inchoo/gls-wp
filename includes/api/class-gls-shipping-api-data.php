<?php

/**
 * Class GLS_Shipping_API_Data
 *
 * Handles data formatting for API calls to GLS Shipping service.
 */
class GLS_Shipping_API_Data
{
	/**
	 * @var \WC_Order $order The WooCommerce order instance.
	 */
	private $order;

	/**
	 * @var bool $is_parcel_delivery_service Flag to check if the order is for parcel delivery service.
	 */
	private $is_parcel_delivery_service = false;

	/**
	 * @var array|null $pickup_info Stores pickup information.
	 */
	private $pickup_info;

	/**
	 * @var array $shipping_method_settings Stores GLS shipping method settings.
	 */
	private $shipping_method_settings;

	/**
	 * Constructor for GLS_Shipping_API_Data.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 */
	public function __construct($order_id)
	{
		$this->order = wc_get_order($order_id);

		$this->shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");

		$shipping_methods = $this->order->get_shipping_methods();
		foreach ($shipping_methods as $shipping_method) {
			if ($shipping_method->get_method_id() === GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID || $shipping_method->get_method_id() === GLS_SHIPPING_METHOD_PARCEL_SHOP_ID) {
				$this->is_parcel_delivery_service = true;
				break;
			}
		}
	}

	/**
	 * Retrieves a specific setting option.
	 *
	 * @param string $key The key of the option to retrieve.
	 * @return mixed|null The value of the specified setting option.
	 */
	public function get_option($key)
	{
		return isset($this->shipping_method_settings[$key]) ? $this->shipping_method_settings[$key] : null;
	}

	/**
	 * Generates the service list for the API request.
	 *
	 * @return array List of services included in the shipping.
	 * @throws \Exception If pickup information is not found.
	 */
	public function get_service_list()
	{
		$express_service_is_valid = false;
		$service_list = [];
		// Parcel Shop Delivery Service
		if ($this->is_parcel_delivery_service) {

			$pickup_info = $this->order->get_meta('_gls_pickup_info', true);
			if (!$pickup_info) {
				throw new Exception("Pickup information not found!");
			} else {
				$this->pickup_info = json_decode($pickup_info, true);
			}

			$service_list[] = [
				'Code' => 'PSD',
				'PSDParameter' => [
					'StringValue' => $this->pickup_info['id'] ?? ''
				]
			];
		}

		// Guaranteed 24h Service
		if ($this->get_option('service_24h') === 'yes' && $this->order->get_shipping_country() !== 'RS') {
			$service_list[] = ['Code' => '24H'];
		}

		// Express Delivery Service
		$expressDeliveryTime = $this->get_option('express_delivery_service');
		if (!$this->is_parcel_delivery_service && $expressDeliveryTime && $this->isExpressDeliverySupported($expressDeliveryTime)) {
			$express_service_is_valid = true;
			$service_list[] = ['Code' => $expressDeliveryTime];
		}

		// Contact Service
		if (!$this->is_parcel_delivery_service && $this->get_option('contact_service') === 'yes') {
			$recipientPhoneNumber = $this->order->get_billing_phone();
			$service_list[] = [
				'Code' => 'CS1',
				'CS1Parameter' => [
					'Value' => $recipientPhoneNumber
				]
			];
		}

		// Flexible Delivery Service
		if (!$this->is_parcel_delivery_service && $this->get_option('flexible_delivery_service') === 'yes' && !$express_service_is_valid) {
			$recipientEmail = $this->order->get_billing_email();
			$service_list[] = [
				'Code' => 'FDS',
				'FDSParameter' => [
					'Value' => $recipientEmail
				]
			];
		}

		// Flexible Delivery SMS Service
		if (!$this->is_parcel_delivery_service && $this->get_option('flexible_delivery_sms_service') === 'yes' && $this->get_option('flexible_delivery_service') === 'yes' && !$express_service_is_valid) {
			$recipientPhoneNumber = $this->order->get_billing_phone();
			$service_list[] = [
				'Code' => 'FSS',
				'FSSParameter' => [
					'Value' => $recipientPhoneNumber
				]
			];
		}

		// SMS Service
		if ($this->get_option('sms_service') === 'yes') {
			$sm1Text = $this->get_option('sms_service_text');
			$recipientPhoneNumber = $this->order->get_billing_phone();
			$service_list[] = [
				'Code' => 'SM1',
				'SM1Parameter' => [
					'Value' => "{$recipientPhoneNumber}|$sm1Text"
				]
			];
		}

		// SMS Pre-advice Service
		if ($this->get_option('sms_pre_advice_service') === 'yes') {
			$recipientPhoneNumber = $this->order->get_billing_phone();
			$service_list[] = [
				'Code' => 'SM2',
				'SM2Parameter' => [
					'Value' => $recipientPhoneNumber
				]
			];
		}

		// Addressee Only Service
		if (!$this->is_parcel_delivery_service && $this->get_option('addressee_only_service') === 'yes') {
			$recipientName = $this->order->get_shipping_first_name() . ' ' . $this->order->get_shipping_last_name();
			$service_list[] = [
				'Code' => 'AOS',
				'AOSParameter' => [
					'Value' => $recipientName
				]
			];
		}

		if ($this->get_option('insurance_service') === 'yes' && $this->isInsuranceAllowed()) {
			$service_list[] = [
				'Code' => 'INS',
				'INSParameter' => [
					'Value' => $this->order->get_total()
				]
			];
		}

		return $service_list;
	}

	/**
	 * Checks if insurance is allowed for the order.
	 *
	 * @return bool Returns true if insurance is allowed, false otherwise.
	 */
	public function isExpressDeliverySupported($expressDeliveryTime)
	{
		require_once(ABSPATH . 'wp-admin/includes/file.php');

    	WP_Filesystem();
    	global $wp_filesystem;

		$countryToCheck = $this->get_option('country');
		$zipcodeToCheck = $this->order->get_shipping_postcode();

		$file_path = GLS_SHIPPING_ABSPATH . "includes/api/express-service.csv";
		$csv_data = $wp_filesystem->get_contents($file_path);

		if ($csv_data) {

			$lines = explode("\n", $csv_data);
			array_shift($lines);

			foreach ($lines as $line) {
				$data = str_getcsv($line);
	
				if (!empty($data)) {
					$country = $data[0];
					$zipcode = $data[1];
	
					if ($country === $countryToCheck && $zipcode === $zipcodeToCheck) {
						if ($expressDeliveryTime === "T12") {
							return $data[2] === "x";
						}
						if ($expressDeliveryTime === "T09") {
							return $data[3] === "x";
						}
						if ($expressDeliveryTime === "T10") {
							return $data[4] === "x";
						}
						return false;
					}
				}
			}
		}

		return false;
	}


	/**
	 * Checks if insurance is allowed for the order.
	 *
	 * @return bool Returns true if insurance is allowed, false otherwise.
	 */
	public function isInsuranceAllowed()
	{
		$packageValue = $this->order->get_total();
		$originCountry = $this->get_option('country');
		$destinationCountry = $this->order->get_shipping_country();

		return $this->checkInsuranceCriteria($packageValue, $originCountry, $destinationCountry);
	}

	/**
	 * Checks if the package meets the criteria for insurance based on value and origin/destination countries.
	 *
	 * @param float $packageValue Value of the package.
	 * @param string $originCountry Country of origin.
	 * @param string $destinationCountry Destination country.
	 * @return bool True if criteria are met, otherwise false.
	 */
	private function checkInsuranceCriteria($packageValue, $originCountry, $destinationCountry)
	{

		$type = $originCountry === $destinationCountry ? 'country_domestic_insurance' : 'country_export_insurance';

		$minMax = $this->getCode($type, $originCountry);

		if (!$minMax) {
			return false;
		}

		if ($packageValue >= $minMax['min'] && $packageValue <= $minMax['max']) {
			return true;
		}
		return false;
	}

	/**
	 * Retrieves GLS carrier configuration data.
	 *
	 * @param string $type Type of configuration data to retrieve.
	 * @param int|string|null $code Specific code to retrieve data for.
	 * @return mixed Configuration data.
	 */
	public function getCode($type, $code = null)
	{
		$data = [
			'country_calling_code' => [
				'CZ' => '+420',
				'HR' => '+385',
				'HU' => '+36',
				'RO' => '+40',
				'SI' => '+386',
				'SK' => '+421',
				'RS' => '+381',
			],
			'country_domestic_insurance' => [
				'CZ' => ['min' => 20000, 'max' => 100000], // CZK
				'HR' => ['min' => 165.9, 'max' => 1659.04], // EUR
				'HU' => ['min' => 50000, 'max' => 500000], // HUF
				'RO' => ['min' => 2000, 'max' => 7000], // RON
				'SI' => ['min' => 200, 'max' => 2000], // EUR
				'SK' => ['min' => 332, 'max' => 2655], // EUR
				'RS' => ['min' => 40000, 'max' => 200000] // RSD
			],
			'country_export_insurance' => [
				'CZ' => ['min' => 20000, 'max' => 100000], // CZK
				'HR' => ['min' => 165.91, 'max' => 663.61], // EUR
				'HU' => ['min' => 50000, 'max' => 200000], // HUF
				'RO' => ['min' => 2000, 'max' => 7000], // RON
				'SI' => ['min' => 200, 'max' => 2000], // EUR
				'SK' => ['min' => 332, 'max' => 1000] // EUR
			]
		];

		if ($code === null) {
			return $data[$type] ?? [];
		}

		return $data[$type][$code] ?? null;
	}

	/**
	 * Gets the pickup address for the shipment.
	 *
	 * @return array The pickup address information.
	 */
	public function get_pickup_address()
	{
		$store_address = get_option('woocommerce_store_address');
		$store_address_2 = get_option('woocommerce_store_address_2');
		$store_city = get_option('woocommerce_store_city');
		$store_postcode = get_option('woocommerce_store_postcode');
		$store_raw_country = get_option('woocommerce_default_country');

		// Split the country and state
		$split_country = explode(":", $store_raw_country);
		$store_country = isset($split_country[0]) ? $split_country[0] : '';

		$pickup_address = [
			'Name' => get_bloginfo('name'),
			'Street' => $store_address . ' ' . $store_address_2,
			'City' => $store_city,
			'ZipCode' => $store_postcode,
			'CountryIsoCode' => $store_country,
			'ContactName' => get_bloginfo('name'),
			'ContactPhone' => $this->get_option('phone_number'),
			'ContactEmail' => get_option('admin_email')
		];

		return $pickup_address;
	}

	/**
	 * Gets the delivery address for the shipment.
	 *
	 * @return array The delivery address information.
	 */
	public function get_delivery_address()
	{
		$delivery_address = [
			'Name' => $this->order->get_shipping_company() ?: $this->order->get_shipping_first_name() . ' ' . $this->order->get_shipping_last_name(),
			'Street' => $this->order->get_shipping_address_1(),
			// 'HouseNumber',
			// 'HouseNumberInfo',
			'City' => $this->order->get_shipping_city(),
			'ZipCode' => $this->order->get_shipping_postcode(),
			'CountryIsoCode' => $this->order->get_shipping_country(),
			'ContactName' => $this->order->get_shipping_first_name() . ' ' . $this->order->get_shipping_last_name(),
			'ContactPhone' => $this->order->get_billing_phone(),
			'ContactEmail' => $this->order->get_billing_email()
		];

		return $delivery_address;
	}

	/**
	 * Generates post fields for the API request.
	 *
	 * @param int $count Number of packages.
	 * @return array The generated post fields for the API request.
	 */
	public function generate_post_fields($count = 1)
	{
		$clientReferenceFormat = $this->get_option('client_reference_format');
		$senderIdentityCardNumber = $this->get_option('sender_identity_card_number');
		$content = $this->get_option('content');
		$orderId = $this->order->get_id();
		$clientReference = str_replace('{{order_id}}', $orderId, $clientReferenceFormat);

		$parcel = [
			'ClientNumber' => (int)$this->get_option("client_id"),
			'ClientReference' => $clientReference,
			'Count' => $count
		];
		$parcel['PickupAddress'] = $this->get_pickup_address();
		$parcel['DeliveryAddress'] = $this->get_delivery_address();
		$parcel['ServiceList'] = $this->get_service_list();
		$parcel['Content'] = $this->order->get_shipping_address_2();

		if ($this->order->get_shipping_country() === 'RS') {
            $parcel['SenderIdentityCardNumber'] = $senderIdentityCardNumber;
            $parcel['Content'] = $content;
        }
		
		if ($this->order->get_payment_method() === 'cod') {
			$parcel['CODAmount'] = $this->order->get_total();
			$parcel['CODReference'] = $orderId;
		}

		$params = [
			'ParcelList' => [$parcel],
			'PrintPosition' => (int)$this->get_option("print_position") ?: 1,
			'TypeOfPrinter' => $this->get_option("type_of_printer") ?: 'A4_2x2',
			'ShowPrintDialog' => false
		];

		return $params;
	}
}
