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
        
        $translation_array = array(
            'ajaxNonce' => wp_create_nonce('gls-pickup-nonce'),
            'adminAjaxUrl' => admin_url('admin-ajax.php'),
        );
        
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
                    
                    // Prepare form data
                    const formData = {
                        action: "gls_schedule_pickup",
                        nonce: "' . wp_create_nonce('gls-pickup-nonce') . '",
                        package_count: $("#package_count").val(),
                        pickup_date_from: $("#pickup_date_from").val(),
                        pickup_date_to: $("#pickup_date_to").val(),
                        contact_name: $("#contact_name").val(),
                        contact_phone: $("#contact_phone").val(),
                        contact_email: $("#contact_email").val(),
                        address_name: $("#address_name").val(),
                        street: $("#street").val(),
                        house_number: $("#house_number").val(),
                        city: $("#city").val(),
                        zip_code: $("#zip_code").val(),
                        country_code: $("#country_code").val()
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
            });
        ');
    }

    /**
     * Render pickup admin page
     */
    public function pickup_admin_page()
    {
        // Get store address defaults
        $store_address = $this->get_store_address_defaults();
        
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
                                <input type="text" id="pickup_date_from" name="pickup_date_from" required class="regular-text" placeholder="YYYY-MM-DD" />
                                <p class="description"><?php esc_html_e('Earliest date for pickup.', 'gls-shipping-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pickup_date_to"><?php esc_html_e('Pickup Date To', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="pickup_date_to" name="pickup_date_to" required class="regular-text" placeholder="YYYY-MM-DD" />
                                <p class="description"><?php esc_html_e('Latest date for pickup.', 'gls-shipping-for-woocommerce'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2><?php esc_html_e('Pickup Address', 'gls-shipping-for-woocommerce'); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="contact_name"><?php esc_html_e('Contact Name', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="contact_name" name="contact_name" value="<?php echo esc_attr($store_address['contact_name']); ?>" required class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="contact_phone"><?php esc_html_e('Contact Phone', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="tel" id="contact_phone" name="contact_phone" value="<?php echo esc_attr($store_address['contact_phone']); ?>" required class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="contact_email"><?php esc_html_e('Contact Email', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="email" id="contact_email" name="contact_email" value="<?php echo esc_attr($store_address['contact_email']); ?>" required class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="address_name"><?php esc_html_e('Company/Address Name', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="address_name" name="address_name" value="<?php echo esc_attr($store_address['address_name']); ?>" required class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="street"><?php esc_html_e('Street', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="street" name="street" value="<?php echo esc_attr($store_address['street']); ?>" required class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="house_number"><?php esc_html_e('House Number', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="house_number" name="house_number" value="<?php echo esc_attr($store_address['house_number']); ?>" required class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="city"><?php esc_html_e('City', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="city" name="city" value="<?php echo esc_attr($store_address['city']); ?>" required class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="zip_code"><?php esc_html_e('ZIP Code', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="zip_code" name="zip_code" value="<?php echo esc_attr($store_address['zip_code']); ?>" required class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="country_code"><?php esc_html_e('Country Code', 'gls-shipping-for-woocommerce'); ?> *</label>
                            </th>
                            <td>
                                <select id="country_code" name="country_code" required class="regular-text">
                                    <option value="HR" <?php selected($store_address['country_code'], 'HR'); ?>>Croatia (HR)</option>
                                    <option value="SI" <?php selected($store_address['country_code'], 'SI'); ?>>Slovenia (SI)</option>
                                    <option value="RS" <?php selected($store_address['country_code'], 'RS'); ?>>Serbia (RS)</option>
                                    <option value="HU" <?php selected($store_address['country_code'], 'HU'); ?>>Hungary (HU)</option>
                                    <option value="RO" <?php selected($store_address['country_code'], 'RO'); ?>>Romania (RO)</option>
                                    <option value="SK" <?php selected($store_address['country_code'], 'SK'); ?>>Slovakia (SK)</option>
                                    <option value="CZ" <?php selected($store_address['country_code'], 'CZ'); ?>>Czech Republic (CZ)</option>
                                </select>
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
     * Get store address defaults from WooCommerce settings
     */
    private function get_store_address_defaults()
    {
        $country = get_option('woocommerce_default_country', 'HR');
        $country_code = strpos($country, ':') !== false ? substr($country, 0, strpos($country, ':')) : $country;
        
        return array(
            'contact_name' => get_option('woocommerce_store_address', ''),
            'contact_phone' => get_option('woocommerce_store_phone', ''),
            'contact_email' => get_option('admin_email', ''),
            'address_name' => get_bloginfo('name'),
            'street' => get_option('woocommerce_store_address', ''),
            'house_number' => get_option('woocommerce_store_address_2', ''),
            'city' => get_option('woocommerce_store_city', ''),
            'zip_code' => get_option('woocommerce_store_postcode', ''),
            'country_code' => $country_code
        );
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
            $required_fields = array('package_count', 'pickup_date_from', 'pickup_date_to', 'contact_name', 'contact_phone', 'contact_email');
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    wp_send_json_error(array('message' => sprintf(
                        /* translators: %s: field name that is required */
                        __('Field %s is required.', 'gls-shipping-for-woocommerce'), 
                        $field
                    )));
                }
            }

            // Create pickup request via API
            $pickup_data = array(
                'package_count' => intval($_POST['package_count']),
                'pickup_date_from' => sanitize_text_field($_POST['pickup_date_from']),
                'pickup_date_to' => sanitize_text_field($_POST['pickup_date_to']),
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
