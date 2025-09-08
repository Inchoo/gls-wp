<?php

/**
 * Handle WooCommerce Blocks Checkout integration for GLS Shipping.
 *
 * This class handles the WooCommerce Blocks checkout functionality,
 * including additional checkout fields, validation, and blocks integration.
 *
 * @since     1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GLS_Shipping_Checkout_Blocks
 *
 * Handles WooCommerce Blocks checkout integration for GLS shipping methods.
 */
class GLS_Shipping_Checkout_Blocks
{
    /**
     * Array of GLS shipping methods that require map selection.
     *
     * @var array
     */
    protected $map_selection_methods;

    /**
     * Constructor for the GLS_Shipping_Checkout_Blocks class.
     *
     * Sets up hooks for WooCommerce Blocks checkout functionality.
     */
    public function __construct()
    {
        $this->map_selection_methods = array(
            GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID,
            GLS_SHIPPING_METHOD_PARCEL_SHOP_ID,
            GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID,
            GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID
        );

        // WooCommerce Blocks hooks - using official Additional Checkout Fields API
        add_action('woocommerce_init', array($this, 'register_additional_checkout_fields'));
        add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
    }

    /**
     * Register additional checkout fields for WooCommerce Blocks
     */
    public function register_additional_checkout_fields()
    {
        // Only register if WooCommerce with Additional Checkout Fields API is available
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        // Register field to store pickup info using WooCommerce Additional Checkout Fields API
        woocommerce_register_additional_checkout_field(
            array(
                'id'            => 'gls-shipping/pickup-info',
                'label'         => 'GLS Pickup Information',
                'location'      => 'contact',
                'type'          => 'text',
                'required'      => false,
            )
        );
        
        // Add hook to save additional field data  
        add_action('woocommerce_set_additional_field_value', array($this, 'save_additional_field_value'), 10, 4);
        
        // Add hook to save data during order creation (works for both classic and blocks checkout)
        add_action('woocommerce_checkout_create_order', array($this, 'save_gls_pickup_on_order_creation'), 10, 2);
        
        // Add JSON validation hook  
        add_action('woocommerce_validate_additional_field', array($this, 'validate_gls_pickup_json'), 10, 3);
        
        // Add validation hook for contact fields - using exact WooCommerce syntax
        add_action('woocommerce_blocks_validate_location_contact_fields', function ($errors, $fields, $group) {
            // Check if GLS pickup method is selected
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
            if (!is_array($chosen_shipping_methods)) {
                $chosen_shipping_methods = [];
            }
            
            $pickup_method_selected = false;
            foreach ($chosen_shipping_methods as $method) {
                if (in_array($method, $this->map_selection_methods)) {
                    $pickup_method_selected = true;
                    break;
                }
            }
            
            // Only validate if GLS pickup method is selected
            if ($pickup_method_selected) {
                $pickup_field_value = isset($fields['gls-shipping/pickup-info']) ? $fields['gls-shipping/pickup-info'] : '';
                
                if (empty($pickup_field_value)) {
                    $errors->add('gls_pickup_required', __('Please select a pickup location by clicking the map button.', 'gls-shipping-for-woocommerce'));
                }
            }
        }, 10, 3);
        
        // Add CSS to hide the field
        add_action('wp_head', array($this, 'add_hide_field_css'));
    }
    
    /**
     * Save additional field value to order meta
     *
     * @param string $key The field key
     * @param mixed $value The field value
     * @param string $group The field group
     * @param WC_Order $wc_object The WooCommerce order object
     */
    public function save_additional_field_value($key, $value, $group, $wc_object)
    {
        if ('gls-shipping/pickup-info' === $key) {
            $wc_object->update_meta_data('_gls_pickup_info', $value);
            $wc_object->save();
        }
    }
    
    /**
     * Save GLS pickup info during order creation (works for both classic and blocks checkout)
     *
     * @param WC_Order $order The WooCommerce order object
     * @param array $data The checkout data
     */
    public function save_gls_pickup_on_order_creation($order, $data)
    {
        // Check if this is a GLS shipping method
        $shipping_methods = $order->get_shipping_methods();
        $is_gls_order = false;
        
        foreach ($shipping_methods as $shipping_method) {
            if (in_array($shipping_method->get_method_id(), $this->map_selection_methods)) {
                $is_gls_order = true;
                break;
            }
        }
        
        if (!$is_gls_order) {
            return;
        }
        
        // Get pickup info from the WooCommerce Additional Checkout Fields data
        if (isset($data['gls-shipping/pickup-info']) && !empty($data['gls-shipping/pickup-info'])) {
            $pickup_info = sanitize_text_field($data['gls-shipping/pickup-info']);
            $order->update_meta_data('_gls_pickup_info', $pickup_info);
        }
    }

    /**
     * Validate GLS pickup field JSON format and requirement
     *
     * @param WP_Error $errors The errors object
     * @param string $field_key The field key
     * @param mixed $field_value The field value
     * @return WP_Error
     */
    public function validate_gls_pickup_json($errors, $field_key, $field_value)
    {
        if ('gls-shipping/pickup-info' !== $field_key) {
            return $errors;
        }
        
        // Only validate JSON format if field has value (required validation is handled separately)
        if (!empty($field_value)) {
            // Validate JSON format
            $decoded = json_decode($field_value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors->add('gls_pickup_invalid_json', __('Invalid pickup location data. Please refresh the page and try again.', 'gls-shipping-for-woocommerce'));
                return $errors;
            }
            
            // Validate required JSON fields
            $required_fields = ['id', 'name', 'contact'];
            foreach ($required_fields as $required_field) {
                if (!isset($decoded[$required_field]) || empty($decoded[$required_field])) {
                    $errors->add('gls_pickup_incomplete', __('Incomplete pickup location data. Please select a location again.', 'gls-shipping-for-woocommerce'));
                    return $errors;
                }
            }
        }
        
        return $errors;
    }

    /**
     * Add CSS to hide the pickup info field
     */
    public function add_hide_field_css()
    {
        if (is_checkout()) {
            echo '<style>
                #contact-gls-shipping-pickup-info,
                input[name*="gls-shipping/pickup-info"],
                label[for*="gls-shipping-pickup-info"],
                .wc-block-components-text-input.wc-block-components-address-form__gls-shipping-pickup-info {
                    display: none !important;
                }
            </style>';
        }
    }

    /**
     * Register blocks integration
     */
    public function register_blocks_integration()
    {
        add_action(
            'woocommerce_blocks_loaded',
            array($this, 'register_gls_integration_with_blocks')
        );
    }

    /**
     * Register GLS integration with blocks
     */
    public function register_gls_integration_with_blocks()
    {
        // Check if the required classes exist
        if (
            function_exists('woocommerce_store_api_register_endpoint_data') &&
            class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry')
        ) {
            require_once GLS_SHIPPING_ABSPATH . 'includes/public/class-gls-shipping-blocks-integration.php';
            
            // Get the integration registry and register our integration
            $integration_registry = \Automattic\WooCommerce\Blocks\Package::container()->get(
                \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry::class
            );
            
            $integration_registry->register(new GLS_Shipping_Blocks_Integration());
        }
    }
}
