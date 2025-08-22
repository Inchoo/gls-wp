<?php

/**
 * Handles showing of order Information
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Order
{

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_gls_shipping_info_meta_box'));
        add_action('wp_ajax_gls_generate_label', array($this, 'generate_label_and_tracking_number'));
        add_action('wp_ajax_gls_get_parcel_status', array($this, 'get_parcel_status'));
    }

    public function add_gls_shipping_info_meta_box()
    {
        $screen = 'shop_order';

        if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            $screen = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
        }

        add_meta_box(
            'gls_shipping_info_meta_box',
            esc_html__('GLS Shipping Info', 'gls-shipping-for-woocommerce'),
            array($this, 'gls_shipping_info_meta_box_content'),
            $screen,
            'side',
            'default'
        );
    }

    private function display_gls_pickup_info($order_id)
    {
        $order = wc_get_order($order_id);
    
        $gls_pickup_info = $order->get_meta('_gls_pickup_info', true);
        $tracking_codes  = $order->get_meta('_gls_tracking_codes', true);
        
        // Legacy support, should be removed later on.
        $tracking_code  = $order->get_meta('_gls_tracking_code', true);
    
        if (!empty($gls_pickup_info)) {
            $pickup_info = json_decode($gls_pickup_info);
    
            echo '<strong>' . esc_html__('GLS Pickup Location:', 'gls-shipping-for-woocommerce') . '</strong><br/>';
            echo '<strong>' . esc_html__('ID:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->id) . '<br>';
            echo '<strong>' . esc_html__('Name:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->name) . '<br>';
            echo '<strong>' . esc_html__('Address:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->contact->address) . ', ' . esc_html($pickup_info->contact->city) . ', ' . esc_html($pickup_info->contact->postalCode) . '<br>';
            echo '<strong>' . esc_html__('Country:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->contact->countryCode) . '<br>';
        }
    
        if (!empty($tracking_codes) && is_array($tracking_codes)) {
            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
            echo '<br/><strong>' . esc_html__('GLS Tracking Numbers:', 'gls-shipping-for-woocommerce') . '</strong><br>';
            foreach ($tracking_codes as $tracking_code) {
                $tracking_url = "https://gls-group.eu/" . $gls_shipping_method_settings['country'] . "/en/parcel-tracking/?match=" . $tracking_code;
                echo '<a href="' . esc_url($tracking_url) . '" target="_blank">' . esc_html($tracking_code) . '</a><br>';
            }
        } else if (!empty($tracking_code)) {
            // Legacy support, should be removed later on.
            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
            $tracking_url = "https://gls-group.eu/" . $gls_shipping_method_settings['country'] . "/en/parcel-tracking/?match=" . $tracking_code;
            echo '<br/><strong>' . esc_html__('GLS Tracking Number: ', 'gls-shipping-for-woocommerce') . '<a href="' . esc_url($tracking_url) . '" target="_blank">' . esc_html($tracking_code) . '</a></strong><br>';
        }
    }
    

    public function gls_shipping_info_meta_box_content($order_or_post_id)
    {

        $order = ($order_or_post_id instanceof WP_Post)
            ? wc_get_order($order_or_post_id->ID)
            : $order_or_post_id;

        $gls_print_label = $order->get_meta('_gls_print_label', true);
        
        // Get tracking number for status button
        $tracking_codes = $order->get_meta('_gls_tracking_codes', true);
        $gls_tracking_number = '';
        if (!empty($tracking_codes) && is_array($tracking_codes)) {
            $gls_tracking_number = $tracking_codes[0]; // Use first tracking code
        } else {
            // Legacy support - check for single tracking code
            $legacy_tracking_code = $order->get_meta('_gls_tracking_code', true);
            if (!empty($legacy_tracking_code)) {
                $gls_tracking_number = $legacy_tracking_code;
            }
        }

        $this->display_gls_pickup_info($order->get_id(), false);
?>
        <h4 style="margin-bottom:0px;">
            <div style="margin-top:10px;">
                <?php if ($gls_print_label) { ?>
                    <a class="button primary" href="<?php echo esc_url($gls_print_label); ?>" target="_blank" style="width: 100%; text-align: center; display: block; box-sizing: border-box;"><?php esc_html_e("Print Label", "gls-shipping-for-woocommerce"); ?></a>
                    <div style="margin-top:10px;display: flex; flex-direction: column;">
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span><?php esc_html_e("Number of Packages:", "gls-shipping-for-woocommerce"); ?></span>
                            <input type="number" id="gls_label_count" name="gls_label_count" min="1" value="1" style="width: 60px;">
                        </div>
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <?php 
                            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
                            $default_print_position = isset($gls_shipping_method_settings['print_position']) ? $gls_shipping_method_settings['print_position'] : '1';
                            $saved_print_position = $order->get_meta('_gls_print_position', true) ?: $default_print_position;
                            ?>
                            <span><?php esc_html_e("Print Position:", "gls-shipping-for-woocommerce"); ?></span>
                            <select id="gls_print_position" name="gls_print_position" style="width: 60px;">
                                <option value="1" <?php selected($saved_print_position, '1'); ?>>1</option>
                                <option value="2" <?php selected($saved_print_position, '2'); ?>>2</option>
                                <option value="3" <?php selected($saved_print_position, '3'); ?>>3</option>
                                <option value="4" <?php selected($saved_print_position, '4'); ?>>4</option>
                            </select>
                        </div>
                        <?php if ($order->get_payment_method() === 'cod') { ?>
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <?php 
                            $saved_cod_reference = $order->get_meta('_gls_cod_reference', true) ?: $order->get_id();
                            ?>
                            <span><?php esc_html_e("COD Reference:", "gls-shipping-for-woocommerce"); ?></span>
                            <input type="text" id="gls_cod_reference" name="gls_cod_reference" value="<?php echo esc_attr($saved_cod_reference); ?>" style="width: 120px;">
                        </div>
                        <?php } ?>
                        
                        <!-- Service Options Toggle -->
                        <div style="margin-bottom: 10px;">
                            <a href="#" id="gls-services-toggle" style="text-decoration: none; color: #0073aa;">
                                <?php esc_html_e("⚙️ Advanced Services", "gls-shipping-for-woocommerce"); ?> <span id="gls-services-arrow">▼</span>
                            </a>
                        </div>
                        
                        <!-- Service Options (Hidden by default) -->
                        <div id="gls-services-options" style="display: none; margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
                            <?php echo $this->render_service_options($order); ?>
                        </div>
                        
                        <button type="button" class="button gls-print-label" order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <?php esc_html_e("Regenerate Shipping Label", "gls-shipping-for-woocommerce"); ?>
                        </button>
                        <?php if (!empty($gls_tracking_number)) { ?>
                        <button type="button" class="button gls-get-status" order-id="<?php echo esc_attr($order->get_id()); ?>" parcel-number="<?php echo esc_attr($gls_tracking_number); ?>" style="margin-top: 10px;">
                            <?php esc_html_e("Get Order Status", "gls-shipping-for-woocommerce"); ?>
                        </button>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div style="margin-top:10px;display: flex; flex-direction: column;">
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span><?php esc_html_e("Number of Packages:", "gls-shipping-for-woocommerce"); ?></span>
                            <input type="number" id="gls_label_count" name="gls_label_count" min="1" value="1" style="width: 60px;">
                        </div>
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <?php 
                            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
                            $default_print_position = isset($gls_shipping_method_settings['print_position']) ? $gls_shipping_method_settings['print_position'] : '1';
                            $saved_print_position = $order->get_meta('_gls_print_position', true) ?: $default_print_position;
                            ?>
                            <span><?php esc_html_e("Print Position:", "gls-shipping-for-woocommerce"); ?></span>
                            <select id="gls_print_position_new" name="gls_print_position_new" style="width: 60px;">
                                <option value="1" <?php selected($saved_print_position, '1'); ?>>1</option>
                                <option value="2" <?php selected($saved_print_position, '2'); ?>>2</option>
                                <option value="3" <?php selected($saved_print_position, '3'); ?>>3</option>
                                <option value="4" <?php selected($saved_print_position, '4'); ?>>4</option>
                            </select>
                        </div>
                        <?php if ($order->get_payment_method() === 'cod') { ?>
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <?php 
                            $saved_cod_reference = $order->get_meta('_gls_cod_reference', true) ?: $order->get_id();
                            ?>
                            <span><?php esc_html_e("COD Reference:", "gls-shipping-for-woocommerce"); ?></span>
                            <input type="text" id="gls_cod_reference_new" name="gls_cod_reference_new" value="<?php echo esc_attr($saved_cod_reference); ?>" style="width: 120px;">
                        </div>
                        <?php } ?>
                        
                        <!-- Service Options Toggle -->
                        <div style="margin-bottom: 10px;">
                            <a href="#" id="gls-services-toggle-new" style="text-decoration: none; color: #0073aa;">
                                <?php esc_html_e("⚙️ Advanced Services", "gls-shipping-for-woocommerce"); ?> <span id="gls-services-arrow-new">▼</span>
                            </a>
                        </div>
                        
                        <!-- Service Options (Hidden by default) -->
                        <div id="gls-services-options-new" style="display: none; margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9;">
                            <?php echo $this->render_service_options($order); ?>
                        </div>
                        
                        <button type="button" class="button gls-print-label" order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <?php esc_html_e("Generate Shipping Label", "gls-shipping-for-woocommerce"); ?>
                        </button>
                    </div>
                <?php } ?>
            </div>
            <div id="gls-info"></div>
            <div id="gls-tracking-status" style="margin-top: 15px;"></div>
        </h4>
<?php
    }

    public function generate_label_and_tracking_number()
    {
        if (!wp_verify_nonce(sanitize_text_field($_POST['postNonce']), 'import-nonce')) {
            die('Busted!');
        }

        $order_id = sanitize_text_field($_POST['orderId']);

        $count = intval($_POST['count']) ?: 1;
        $print_position = isset($_POST['printPosition']) ? intval($_POST['printPosition']) : null;
        $cod_reference = isset($_POST['codReference']) ? sanitize_text_field($_POST['codReference']) : null;
        $services = isset($_POST['services']) ? json_decode(stripslashes($_POST['services']), true) : null;
        
        try {
            $order = wc_get_order($order_id);
            
            // Save print position to order meta if provided
            if ($print_position !== null) {
                $order->update_meta_data('_gls_print_position', $print_position);
            }
            
            // Save COD reference to order meta if provided
            if ($cod_reference !== null) {
                $order->update_meta_data('_gls_cod_reference', $cod_reference);
            }
            
            // Save services to order meta if provided
            if ($services !== null) {
                $order->update_meta_data('_gls_services', $services);
            }
            
            if ($print_position !== null || $cod_reference !== null || $services !== null) {
                $order->save();
            }

            $prepare_data = new GLS_Shipping_API_Data($order_id);
            $data = $prepare_data->generate_post_fields($count, $print_position, $cod_reference, $services);
    
            $api = new GLS_Shipping_API_Service();
            $result = $api->send_order($data);
            $this->save_label_and_tracking_info($result['body'], $order_id);
    
            wp_send_json_success(array('success' => true));
        } catch (Exception $e) {
            error_log($e->getMessage());
            wp_send_json_error(array('success' => false, 'error' => $e->getMessage()));
            return;
        }
    }

    public function save_label_and_tracking_info($body, $order_id)
    {
        $order = wc_get_order($order_id);
        if (!empty($body['Labels'])) {
            $this->save_print_labels($body['Labels'], $order_id, $order);
        }

        if (!empty($body['PrintLabelsInfoList'])) {
            $this->save_tracking_info($body['PrintLabelsInfoList'], $order_id, $order);
        }
    }

    public function save_print_labels($labels, $order_id, $order)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    
        WP_Filesystem();
        global $wp_filesystem;
    
        $label_print = implode(array_map('chr', $labels));
        $upload_dir = wp_upload_dir();
        
        $timestamp = current_time('YmdHis');
        $file_name = 'shipping_label_' . $order_id . '_' . $timestamp . '.pdf';
        
        $file_url = $upload_dir['url'] . '/' . $file_name;
        $file_path = $upload_dir['path'] . '/' . $file_name;

        if ($wp_filesystem->put_contents($file_path, $label_print)) {
            $order->update_meta_data('_gls_print_label', $file_url);
            $order->save();
        }
    }
    

    public function save_tracking_info($printLabelsInfoList, $order_id, $order)
    {
        $tracking_codes = array();
        $parcel_ids = array();
    
        foreach ($printLabelsInfoList as $labelInfo) {
            if (isset($labelInfo['ParcelNumber'])) {
                $tracking_codes[] = $labelInfo['ParcelNumber'];
            }
            if (isset($labelInfo['ParcelId'])) {
                $parcel_ids[] = $labelInfo['ParcelId'];
            }
        }
    
        if (!empty($tracking_codes)) {
            $order->update_meta_data('_gls_tracking_codes', $tracking_codes);
        }
    
        if (!empty($parcel_ids)) {
            $order->update_meta_data('_gls_parcel_ids', $parcel_ids);
        }
    
        $order->save();
    }

    public function get_parcel_status()
    {
        if (!wp_verify_nonce(sanitize_text_field($_POST['postNonce']), 'import-nonce')) {
            wp_send_json_error(array('error' => 'Invalid security token'));
            wp_die();
        }

        $order_id = intval($_POST['orderId']);
        $parcel_number = sanitize_text_field($_POST['parcelNumber']);

        if (empty($order_id) || empty($parcel_number)) {
            wp_send_json_error(array('error' => 'Missing order ID or parcel number'));
            wp_die();
        }

        try {
            $api_service = new GLS_Shipping_API_Service();
            $tracking_data = $api_service->get_parcel_status($parcel_number);
            
            wp_send_json_success(array('tracking_data' => $tracking_data));
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }

        wp_die();
    }

    /**
     * Render service options for order screen
     */
    private function render_service_options($order)
    {
        $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
        $shipping_country = $order->get_shipping_country();
        
        ob_start();
        ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 13px;">
            
            <!-- 24H Service -->
            <?php if ($shipping_country !== 'RS') { ?>
            <div>
                <label>
                    <input type="checkbox" id="gls_service_24h" name="gls_service_24h" 
                           <?php checked($gls_shipping_method_settings['service_24h'] ?? 'no', 'yes'); ?>>
                    <?php esc_html_e('24H Service', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            <?php } ?>
            
            <!-- Express Delivery Service -->
            <div>
                <label><?php esc_html_e('Express Service:', 'gls-shipping-for-woocommerce'); ?></label>
                <select id="gls_express_delivery_service" name="gls_express_delivery_service" style="width: 100%; margin-top: 2px;">
                    <option value="" <?php selected($gls_shipping_method_settings['express_delivery_service'] ?? '', ''); ?>><?php esc_html_e('Disabled', 'gls-shipping-for-woocommerce'); ?></option>
                    <option value="T09" <?php selected($gls_shipping_method_settings['express_delivery_service'] ?? '', 'T09'); ?>>T09 (09:00)</option>
                    <option value="T10" <?php selected($gls_shipping_method_settings['express_delivery_service'] ?? '', 'T10'); ?>>T10 (10:00)</option>
                    <option value="T12" <?php selected($gls_shipping_method_settings['express_delivery_service'] ?? '', 'T12'); ?>>T12 (12:00)</option>
                </select>
            </div>
            
            <!-- Contact Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_contact_service" name="gls_contact_service"
                           <?php checked($gls_shipping_method_settings['contact_service'] ?? 'no', 'yes'); ?>>
                    <?php esc_html_e('Contact Service (CS1)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- Flexible Delivery Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_flexible_delivery_service" name="gls_flexible_delivery_service"
                           <?php checked($gls_shipping_method_settings['flexible_delivery_service'] ?? 'no', 'yes'); ?>>
                    <?php esc_html_e('Flexible Delivery (FDS)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- Flexible Delivery SMS Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_flexible_delivery_sms_service" name="gls_flexible_delivery_sms_service"
                           <?php checked($gls_shipping_method_settings['flexible_delivery_sms_service'] ?? 'no', 'yes'); ?>>
                    <?php esc_html_e('Flexible Delivery SMS (FSS)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- SMS Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_sms_service" name="gls_sms_service"
                           <?php checked($gls_shipping_method_settings['sms_service'] ?? 'no', 'yes'); ?>>
                    <?php esc_html_e('SMS Service (SM1)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- SMS Pre-advice Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_sms_pre_advice_service" name="gls_sms_pre_advice_service"
                           <?php checked($gls_shipping_method_settings['sms_pre_advice_service'] ?? 'no', 'yes'); ?>>
                    <?php esc_html_e('SMS Pre-advice (SM2)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- Addressee Only Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_addressee_only_service" name="gls_addressee_only_service"
                           <?php checked($gls_shipping_method_settings['addressee_only_service'] ?? 'no', 'yes'); ?>>
                    <?php esc_html_e('Addressee Only (AOS)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
            <!-- Insurance Service -->
            <div>
                <label>
                    <input type="checkbox" id="gls_insurance_service" name="gls_insurance_service"
                           <?php checked($gls_shipping_method_settings['insurance_service'] ?? 'no', 'yes'); ?>>
                    <?php esc_html_e('Insurance (INS)', 'gls-shipping-for-woocommerce'); ?>
                </label>
            </div>
            
        </div>
        
        <!-- SMS Service Text -->
        <div id="gls_sms_text_container" style="margin-top: 10px; display: <?php echo ($gls_shipping_method_settings['sms_service'] ?? 'no') === 'yes' ? 'block' : 'none'; ?>;">
            <label><?php esc_html_e('SMS Text:', 'gls-shipping-for-woocommerce'); ?></label>
            <input type="text" id="gls_sms_service_text" name="gls_sms_service_text" 
                   value="<?php echo esc_attr($gls_shipping_method_settings['sms_service_text'] ?? ''); ?>" 
                   style="width: 100%; margin-top: 2px;" 
                   placeholder="Max 130 characters">
            <small style="color: #666;"><?php esc_html_e('Variables: #ParcelNr#, #COD#, #PickupDate#, #From_Name#, #ClientRef#', 'gls-shipping-for-woocommerce'); ?></small>
        </div>
        
        <?php
        return ob_get_clean();
    }
}

new GLS_Shipping_Order();
