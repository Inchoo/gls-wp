<?php

/**
 * Handles GLS pickup scheduling functionality
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Pickup
{

    public function __construct()
    {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_pickup_admin_menu'));
        
        // Handle AJAX pickup request
        add_action('wp_ajax_gls_schedule_pickup', array($this, 'handle_pickup_request'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_pickup_scripts'));
    }

    /**
     * Add GLS Pickup admin menu
     */
    public function add_pickup_admin_menu()
    {
        add_submenu_page(
            'woocommerce',
            __('GLS Pickup', 'gls-shipping-for-woocommerce'),
            __('GLS Pickup', 'gls-shipping-for-woocommerce'),
            'manage_woocommerce',
            'gls-pickup',
            array($this, 'pickup_admin_page')
        );
    }

    /**
     * Enqueue pickup scripts
     */
    public function enqueue_pickup_scripts($hook)
    {
        if ($hook !== 'woocommerce_page_gls-pickup') {
            return;
        }

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
        
        // Get addresses for JavaScript
        $all_addresses = GLS_Shipping_Sender_Address_Helper::get_all_addresses_with_store_fallback();
        
        $translation_array = array(
            'ajaxNonce' => wp_create_nonce('gls-pickup-nonce'),
            'adminAjaxUrl' => admin_url('admin-ajax.php'),
            'addresses' => $all_addresses,
        );
        
        wp_localize_script('jquery', 'glsPickupData', $translation_array);
        
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                // Initialize datepickers
                $("#pickup_date_from, #pickup_date_to").datepicker({
                    dateFormat: "yy-mm-dd",
                    minDate: 0,
                    showButtonPanel: true
                });
                
                // Handle pickup form submission
                $("#gls-pickup-form").on("submit", function(e) {
                    e.preventDefault();
                    
                    const $form = $(this);
                    const $submitBtn = $("#submit-pickup");
                    const $responseDiv = $("#pickup-response");
                    
                    // Disable submit button
                    $submitBtn.prop("disabled", true).text("' . __('Scheduling...', 'gls-shipping-for-woocommerce') . '");
                    $responseDiv.html("");
                    
                    // Get selected address data
                    const selectedIndex = $("#sender_address_select").val();
                    const selectedAddress = addresses[selectedIndex];
                    
                    // Prepare form data
                    const formData = {
                        action: "gls_schedule_pickup",
                        nonce: "' . wp_create_nonce('gls-pickup-nonce') . '",
                        package_count: $("#package_count").val(),
                        pickup_date_from: $("#pickup_date_from").val(),
                        pickup_time_from: $("#pickup_time_from").val(),
                        pickup_date_to: $("#pickup_date_to").val(),
                        pickup_time_to: $("#pickup_time_to").val(),
                        sender_address_index: selectedIndex,
                        // Address data from selected address
                        contact_name: selectedAddress.contact_name || selectedAddress.name || "",
                        contact_phone: selectedAddress.phone || "",
                        contact_email: selectedAddress.email || "",
                        address_name: selectedAddress.name || "",
                        street: selectedAddress.street || "",
                        house_number: selectedAddress.house_number || "",
                        city: selectedAddress.city || "",
                        zip_code: selectedAddress.postcode || "",
                        country_code: selectedAddress.country || "HR"
                    };
                    
                    // Submit AJAX request
                    $.ajax({
                        url: "' . admin_url('admin-ajax.php') . '",
                        type: "POST",
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                $responseDiv.html(\'<div class="notice notice-success"><p><strong>' . __('Pickup scheduled successfully!', 'gls-shipping-for-woocommerce') . '</strong></p>\' + JSON.stringify(response.data, null, 2) + \'</div>\');
                            } else {
                                $responseDiv.html(\'<div class="notice notice-error"><p><strong>' . __('Error:', 'gls-shipping-for-woocommerce') . '</strong> \' + response.data.message + \'</p></div>\');
                            }
                        },
                        error: function() {
                            $responseDiv.html(\'<div class="notice notice-error"><p>' . __('Network error occurred.', 'gls-shipping-for-woocommerce') . '</p></div>\');
                        },
                        complete: function() {
                            $submitBtn.prop("disabled", false).text("' . __('Schedule Pickup', 'gls-shipping-for-woocommerce') . '");
                        }
                    });
                });

                // Set minimum date to today
                const today = new Date().toISOString().split("T")[0];
                $("#pickup_date_from, #pickup_date_to").attr("min", today);
                
                // Address selection handler
                const addresses = glsPickupData.addresses;
                $("#sender_address_select").on("change", function() {
                    const selectedIndex = $(this).val();
                    const selectedAddress = addresses[selectedIndex];
                    
                    if (selectedAddress) {
                        // Show address details
                        const addressText = selectedAddress.name + "<br>" +
                            selectedAddress.street + " " + (selectedAddress.house_number || "") + "<br>" +
                            selectedAddress.postcode + " " + selectedAddress.city + "<br>" +
                            selectedAddress.country + "<br>" +
                            "Phone: " + (selectedAddress.phone || "N/A") + "<br>" +
                            "Email: " + (selectedAddress.email || "N/A");
                        
                        $("#address-details-text").html(addressText);
                        $("#selected-address-details").show();
                    } else {
                        // Hide address details if no valid address selected
                        $("#selected-address-details").hide();
                    }
                });
                
                // Trigger initial population
                $("#sender_address_select").trigger("change");
            });
        ');
    }

    /**
     * Render pickup admin page
     */
    public function pickup_admin_page()
    {
        // Get all addresses (including store fallback as first option)
        $all_addresses = GLS_Shipping_Sender_Address_Helper::get_all_addresses_with_store_fallback();
        // Use first address (store) as default for field population
        $store_address = !empty($all_addresses) ? $all_addresses[0] : array();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('GLS Pickup Scheduling', 'gls-shipping-for-woocommerce'); ?></h1>
            <p><?php esc_html_e('Schedule a pickup request with GLS to collect packages from your location.', 'gls-shipping-for-woocommerce'); ?></p>
            
            <form id="gls-pickup-form" method="post">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="package_count"><?php esc_html_e('Number of Packages', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="number" id="package_count" name="package_count" min="1" value="1" required class="regular-text" />
                                <p class="description"><?php esc_html_e('Total number of packages to be collected.', 'gls-shipping-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pickup_date_from"><?php esc_html_e('Pickup Date From', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="date" id="pickup_date_from" name="pickup_date_from" required class="regular-text" style="width: 160px;" />
                                <input type="time" id="pickup_time_from" name="pickup_time_from" value="08:00" class="regular-text" style="width: 120px; margin-left: 10px;" />
                                <p class="description"><?php esc_html_e('Earliest date and time for pickup.', 'gls-shipping-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pickup_date_to"><?php esc_html_e('Pickup Date To', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="date" id="pickup_date_to" name="pickup_date_to" required class="regular-text" style="width: 160px;" />
                                <input type="time" id="pickup_time_to" name="pickup_time_to" value="17:00" class="regular-text" style="width: 120px; margin-left: 10px;" />
                                <p class="description"><?php esc_html_e('Latest date and time for pickup.', 'gls-shipping-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2><?php esc_html_e('Pickup Address', 'gls-shipping-for-woocommerce'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="sender_address_select"><?php esc_html_e('Pickup Address', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <select id="sender_address_select" name="sender_address_select" class="regular-text" required>
                                    <?php foreach ($all_addresses as $index => $address): ?>
                                        <option value="<?php echo esc_attr($index); ?>" <?php selected($index, 0); ?>>
                                            <?php echo esc_html($address['name'] . ' - ' . $address['city']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Choose the pickup address from configured sender addresses or store default.', 'gls-shipping-for-woocommerce'); ?></p>
                                
                                <!-- Display selected address details -->
                                <div id="selected-address-details" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
                                    <strong><?php esc_html_e('Selected Address Details:', 'gls-shipping-for-woocommerce'); ?></strong><br>
                                    <span id="address-details-text"></span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(__('Schedule Pickup', 'gls-shipping-for-woocommerce'), 'primary', 'submit-pickup'); ?>
            </form>
            
            <div id="pickup-response" style="margin-top: 20px;"></div>
        </div>
        <?php
    }



    /**
     * Handle pickup request AJAX
     */
    public function handle_pickup_request()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gls-pickup-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'gls-shipping-for-woocommerce')));
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gls-shipping-for-woocommerce')));
        }

        try {
            // Validate required fields
            $required_fields = array('package_count', 'pickup_date_from', 'pickup_date_to', 'contact_name', 'contact_email');
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    wp_send_json_error(array('message' => sprintf(
                        /* translators: %s: field name that is required */
                        __('Field %s is required.', 'gls-shipping-for-woocommerce'), 
                        $field
                    )));
                }
            }

            // Combine date and time fields
            $pickup_date_from = sanitize_text_field($_POST['pickup_date_from']);
            $pickup_time_from = !empty($_POST['pickup_time_from']) ? sanitize_text_field($_POST['pickup_time_from']) : '08:00';
            $pickup_datetime_from = $pickup_date_from . ' ' . $pickup_time_from;

            $pickup_date_to = sanitize_text_field($_POST['pickup_date_to']);
            $pickup_time_to = !empty($_POST['pickup_time_to']) ? sanitize_text_field($_POST['pickup_time_to']) : '17:00';
            $pickup_datetime_to = $pickup_date_to . ' ' . $pickup_time_to;

            // Create pickup request via API
            $pickup_data = array(
                'package_count' => intval($_POST['package_count']),
                'pickup_date_from' => $pickup_datetime_from,
                'pickup_date_to' => $pickup_datetime_to,
                'contact_name' => sanitize_text_field($_POST['contact_name']),
                'contact_phone' => sanitize_text_field($_POST['contact_phone']),
                'contact_email' => sanitize_email($_POST['contact_email']),
                'address_name' => sanitize_text_field($_POST['address_name']),
                'street' => sanitize_text_field($_POST['street']),
                'house_number' => sanitize_text_field($_POST['house_number']),
                'city' => sanitize_text_field($_POST['city']),
                'zip_code' => sanitize_text_field($_POST['zip_code']),
                'country_code' => sanitize_text_field($_POST['country_code'])
            );

            // Call API service
            $api_service = new GLS_Shipping_Pickup_API_Service();
            $result = $api_service->create_pickup_request($pickup_data);

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }

        wp_die();
    }
}

new GLS_Shipping_Pickup();
