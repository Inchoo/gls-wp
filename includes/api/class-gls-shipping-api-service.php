<?php

/**
 * Manages GLS API.
 *
 */

class GLS_Shipping_API_Service
{


	/**
	 * @var string API url
	 */
	private $api_url;

	private $service_settings;

	/**
	 * Constructor.
	 *
	 */
	public function __construct()
	{
		$this->service_settings = get_option("woocommerce_gls_shipping_method_settings");
		$this->api_url = $this->get_api_url('ParcelService', 'PrintLabels');
	}

	public function get_option($key)
	{
		return isset($this->service_settings[$key]) ? $this->service_settings[$key] : null;
	}


	public function get_api_url($serviceName, $methodName, $format = 'json')
	{
		$countryCode = $this->get_option('country');
		$mode = $this->get_option('mode');

		$baseUrl = $mode === 'production' ? 'https://api.mygls.' : 'https://api.test.mygls.';
		return $baseUrl . $countryCode . '/' . $serviceName . '.svc/' . $format . '/' . $methodName;
	}

	public function get_password()
	{
		$password = $this->get_option("password");
		if (!$password) {
			throw new Exception('Password not set for GLS API');
		}

		$passwordData = unpack('C*', hash('sha512', $password, true)) ?: []; // phpcs:ignore
		return array_values($passwordData);
	}

	private function generate_post_request($post_fields)
	{
		$post_fields['Username'] = $this->get_option("username");
		$post_fields['Password'] = $this->get_password();

		$params = array(
			'headers'     => array('Content-Type' => 'application/json'),
			'body'        => json_encode($post_fields),
			'method'      => 'POST',
			'timeout' 	  => 60,
			'data_format' => 'body',
		);
		return $params;
	}

	public function send_order($post_fields)
	{
		$params = $this->generate_post_request($post_fields);
		$response = wp_remote_post($this->api_url, $params);

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			$this->log_error($error_message, $post_fields);
			throw new Exception('Error communicating with GLS API: ' . $error_message);
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (!empty($body['PrintLabelsErrorList'])) {
			$error_message = $body['PrintLabelsErrorList'][0]['ErrorDescription'] ?? 'GLS API error.';
			$this->log_error($error_message, $post_fields);
			throw new Exception($error_message);
		}

		if ($this->get_option("logging") === 'yes') {
			$this->log_response($body, $response, $post_fields);
		}

		return $body;
	}

	private function log_error($error_message, $params)
	{
		error_log('** API request to: ' . $this->api_url . ' FAILED ** 
			Request Params: {' . json_encode($params) . '} 
			Error: ' . $error_message . ' 
			** END **');
	}

	private function log_response($body, $response, $params)
	{
		unset($body['Labels']);
		error_log('** API request to: ' . $this->api_url . ' SUCCESS ** 
				Request Params: {' . json_encode($params) . '} 
				Response Body: ' . json_encode($body) . ' 
				** END **');
	}
}
