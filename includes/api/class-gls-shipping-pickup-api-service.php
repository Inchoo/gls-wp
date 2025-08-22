<?php

/**
 * Handles GLS Pickup API requests
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Pickup_API_Service
{
    private $service_settings;

    public function __construct()
    {
        $this->service_settings = get_option("woocommerce_gls_shipping_method_settings");
    }

    /**
     * Get option value (supports multiple accounts)
     */
    public function get_option($key)
    {
        // Check if we're using multiple accounts mode
        $account_mode = isset($this->service_settings['account_mode']) ? $this->service_settings['account_mode'] : 'single';
        
        if ($account_mode === 'multiple') {
            $active_account = $this->get_active_account();
            if ($active_account && isset($active_account[$key])) {
                return $active_account[$key];
            }
        }
        
        return isset($this->service_settings[$key]) ? $this->service_settings[$key] : null;
    }
    
    /**
     * Get the active account from multiple accounts
     */
    private function get_active_account()
    {
        $accounts = isset($this->service_settings['gls_accounts_grid']) ? $this->service_settings['gls_accounts_grid'] : array();
        
        if (empty($accounts)) {
            return false;
        }
        
        // Find the active account
        foreach ($accounts as $account) {
            if (!empty($account['active']) && $account['active']) {
                return $account;
            }
        }
        
        // Return first account as fallback
        return reset($accounts);
    }

    /**
     * Get API URL for pickup requests
     */
    private function get_pickup_api_url()
    {
        $countryCode = $this->get_option('country');
        $mode = $this->get_option('mode');

        $baseUrl = $mode === 'production' ? 'https://api.mygls.' : 'https://api.test.mygls.';
        return $baseUrl . $countryCode . '/ParcelService.svc/json/CreatePickupRequest';
    }

    /**
     * Get password as byte array
     */
    private function get_password()
    {
        $password = $this->get_option("password");
        if (!$password) {
            throw new Exception('Password not set for GLS API');
        }

        $passwordData = unpack('C*', hash('sha512', $password, true)) ?: [];
        return array_values($passwordData);
    }

    /**
     * Convert date to .NET format
     */
    private function convert_date_to_dotnet_format($date_string)
    {
        $timestamp = strtotime($date_string . ' 09:00:00'); // Set to 9 AM
        return '/Date(' . ($timestamp * 1000) . ')/';
    }

    /**
     * Create pickup request
     */
    public function create_pickup_request($pickup_data)
    {
        try {
            // Validate API credentials
            $username = $this->get_option("username");
            $client_number = $this->get_option("client_id");
            
            if (!$username || !$client_number) {
                throw new Exception(__('GLS API credentials not configured.', 'gls-shipping-for-woocommerce'));
            }

            // Prepare request data
            $request_data = array(
                'Username' => $username,
                'Password' => $this->get_password(),
                'ClientNumber' => $client_number,
                'Count' => $pickup_data['package_count'],
                'PickupTimeFrom' => $this->convert_date_to_dotnet_format($pickup_data['pickup_date_from']),
                'PickupTimeTo' => $this->convert_date_to_dotnet_format($pickup_data['pickup_date_to']),
                'Address' => array(
                    'Name' => $pickup_data['address_name'],
                    'ContactName' => $pickup_data['contact_name'],
                    'ContactPhone' => $pickup_data['contact_phone'],
                    'ContactEmail' => $pickup_data['contact_email'],
                    'Street' => $pickup_data['street'],
                    'HouseNumber' => $pickup_data['house_number'],
                    'City' => $pickup_data['city'],
                    'ZipCode' => $pickup_data['zip_code'],
                    'CountryIsoCode' => $pickup_data['country_code']
                )
            );

            // Make API request
            $api_url = $this->get_pickup_api_url();
            $response = $this->send_pickup_request($api_url, $request_data);

            // Log the response if logging is enabled
            if ($this->get_option("logging") === 'yes') {
                $this->log_pickup_response($response, $request_data);
            }

            return $response;

        } catch (Exception $e) {
            // Log error if logging is enabled
            if ($this->get_option("logging") === 'yes') {
                $this->log_pickup_error($e->getMessage(), $pickup_data);
            }
            
            throw $e;
        }
    }

    /**
     * Send pickup request to GLS API
     */
    private function send_pickup_request($api_url, $request_data)
    {
        $params = array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($request_data),
            'method' => 'POST',
            'timeout' => 60,
            'data_format' => 'body',
        );

        $response = wp_remote_post($api_url, $params);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            throw new Exception('Error communicating with GLS API: ' . $error_message);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code !== 200) {
            throw new Exception('GLS API returned error code: ' . $response_code);
        }

        // Check for API errors
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            $error_message = isset($body['ErrorDescription']) ? $body['ErrorDescription'] : 'Unknown API error';
            throw new Exception('GLS API Error: ' . $error_message);
        }

        return $body;
    }

    /**
     * Log pickup response
     */
    private function log_pickup_response($response, $request_data)
    {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'action' => 'pickup_request',
            'request' => $request_data,
            'response' => $response
        );

        error_log('GLS Pickup API Response: ' . wp_json_encode($log_entry));
    }

    /**
     * Log pickup error
     */
    private function log_pickup_error($error_message, $pickup_data)
    {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'action' => 'pickup_request_error',
            'error' => $error_message,
            'request_data' => $pickup_data
        );

        error_log('GLS Pickup API Error: ' . wp_json_encode($log_entry));
    }
}
