<?php

/**
 * Handles migration of existing labels to secure folder
 *
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Label_Migration
{
    /**
     * Batch size for migration
     */
    const BATCH_SIZE = 20;

    /**
     * Action Scheduler hook name
     */
    const MIGRATION_HOOK = 'gls_migrate_labels_batch';

    /**
     * Option key for tracking migration status
     */
    const MIGRATION_STATUS_OPTION = 'gls_label_migration_status';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Register Action Scheduler hook
        add_action(self::MIGRATION_HOOK, array($this, 'process_migration_batch'));

        // Add admin notice for migration status
        add_action('admin_notices', array($this, 'display_migration_notice'));

        // Handle fallback for old URLs during migration
        add_action('admin_init', array($this, 'handle_old_label_fallback'), 5);

        // Check for migration on plugins loaded (for updates without deactivation)
        add_action('plugins_loaded', array($this, 'check_migration_on_update'), 20);
    }

    /**
     * Plugin activation callback
     */
    public static function on_plugin_activation()
    {
        // Setup labels directory
        if (class_exists('GLS_Shipping_For_Woo')) {
            $instance = GLS_Shipping_For_Woo::get_instance();
            $instance->setup_labels_directory();
        }
        
        // Schedule migration of existing labels
        self::schedule_migration();
    }

    /**
     * Check for migration on plugin update (without deactivation)
     */
    public function check_migration_on_update()
    {
        // Only run once per version
        $current_version = get_option('gls_shipping_version');
        if ($current_version !== GLS_SHIPPING_VERSION) {
            update_option('gls_shipping_version', GLS_SHIPPING_VERSION);
            
            // Setup labels directory
            if (class_exists('GLS_Shipping_For_Woo')) {
                $instance = GLS_Shipping_For_Woo::get_instance();
                $instance->setup_labels_directory();
            }
            
            // Schedule migration if needed
            self::schedule_migration();
        }
    }

    /**
     * Schedule migration on plugin activation/update
     */
    public static function schedule_migration()
    {
        // Check if migration is needed
        if (self::is_migration_needed()) {
            // Initialize migration status
            $status = array(
                'started_at' => current_time('mysql'),
                'total_orders' => self::count_orders_needing_migration(),
                'migrated' => 0,
                'failed' => 0,
                'completed' => false
            );
            update_option(self::MIGRATION_STATUS_OPTION, $status);

            // Schedule recurring action if not already scheduled
            if (function_exists('as_has_scheduled_action') && false === as_has_scheduled_action(self::MIGRATION_HOOK)) {
                as_schedule_recurring_action(time(), 60, self::MIGRATION_HOOK);
            }
        }
    }

    /**
     * Check if migration is needed
     *
     * @return bool
     */
    public static function is_migration_needed()
    {
        $status = get_option(self::MIGRATION_STATUS_OPTION);
        
        // If migration is already completed, no need
        if ($status && isset($status['completed']) && $status['completed']) {
            return false;
        }

        // Check if there are orders with old-style URLs
        return self::count_orders_needing_migration() > 0;
    }

    /**
     * Count orders that need migration
     *
     * @return int
     */
    public static function count_orders_needing_migration()
    {
        global $wpdb;

        // Check both HPOS and legacy meta tables
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
            Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            // HPOS enabled
            $table = $wpdb->prefix . 'wc_orders_meta';
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} 
                WHERE meta_key = '_gls_print_label' 
                AND meta_value LIKE %s 
                AND meta_value NOT LIKE %s",
                '%/wp-content/uploads/%',
                '%gls_download_label%'
            ));
        } else {
            // Legacy post meta
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_gls_print_label' 
                AND meta_value LIKE %s 
                AND meta_value NOT LIKE %s",
                '%/wp-content/uploads/%',
                '%gls_download_label%'
            ));
        }

        return (int) $count;
    }

    /**
     * Process migration batch via Action Scheduler
     */
    public function process_migration_batch()
    {
        // Ensure labels directory exists
        if (class_exists('GLS_Shipping_For_Woo')) {
            GLS_Shipping_For_Woo::get_instance()->setup_labels_directory();
        }

        // Get orders needing migration
        $orders = $this->get_orders_needing_migration(self::BATCH_SIZE);

        if (empty($orders)) {
            // Migration complete
            $this->complete_migration();
            return;
        }

        $status = get_option(self::MIGRATION_STATUS_OPTION, array());
        $migrated = isset($status['migrated']) ? $status['migrated'] : 0;
        $failed = isset($status['failed']) ? $status['failed'] : 0;

        foreach ($orders as $order_id) {
            $result = $this->migrate_single_label($order_id);
            
            if ($result) {
                $migrated++;
            } else {
                $failed++;
            }
        }

        // Update status
        $status['migrated'] = $migrated;
        $status['failed'] = $failed;
        $status['last_run'] = current_time('mysql');
        update_option(self::MIGRATION_STATUS_OPTION, $status);

        // Log progress
        error_log(sprintf(
            'GLS Label Migration: Processed batch. Migrated: %d, Failed: %d, Remaining: ~%d',
            $migrated,
            $failed,
            self::count_orders_needing_migration()
        ));
    }

    /**
     * Get orders that need migration
     *
     * @param int $limit
     * @return array Order IDs
     */
    private function get_orders_needing_migration($limit)
    {
        global $wpdb;

        // Check both HPOS and legacy meta tables
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
            Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            // HPOS enabled
            $table = $wpdb->prefix . 'wc_orders_meta';
            $order_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT order_id FROM {$table} 
                WHERE meta_key = '_gls_print_label' 
                AND meta_value LIKE %s 
                AND meta_value NOT LIKE %s
                LIMIT %d",
                '%/wp-content/uploads/%',
                '%gls_download_label%',
                $limit
            ));
        } else {
            // Legacy post meta
            $order_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_gls_print_label' 
                AND meta_value LIKE %s 
                AND meta_value NOT LIKE %s
                LIMIT %d",
                '%/wp-content/uploads/%',
                '%gls_download_label%',
                $limit
            ));
        }

        return array_map('intval', $order_ids);
    }

    /**
     * Migrate a single label to secure folder
     *
     * @param int $order_id
     * @return bool Success
     */
    public function migrate_single_label($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $old_url = $order->get_meta('_gls_print_label', true);
        if (empty($old_url)) {
            return false;
        }

        // Skip if already migrated (has gls_download_label in URL)
        if (strpos($old_url, 'gls_download_label') !== false) {
            return true;
        }

        // Convert URL to file path
        $upload_dir = wp_upload_dir();
        $old_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $old_url);

        // Check if old file exists
        if (!file_exists($old_path)) {
            // File doesn't exist - just update meta to indicate migration attempted
            // This handles cases where file was already deleted
            error_log(sprintf('GLS Migration: File not found for order %d: %s', $order_id, $old_path));
            
            // Clear the meta since file doesn't exist
            $order->delete_meta_data('_gls_print_label');
            $order->save();
            return true;
        }

        // Generate new filename (keep original name for reference)
        $original_filename = basename($old_path);
        $new_path = GLS_LABELS_DIR . '/' . $original_filename;

        // Handle filename collision
        if (file_exists($new_path)) {
            $pathinfo = pathinfo($original_filename);
            $original_filename = $pathinfo['filename'] . '_' . $order_id . '.' . $pathinfo['extension'];
            $new_path = GLS_LABELS_DIR . '/' . $original_filename;
        }

        // Copy file to new location
        if (!copy($old_path, $new_path)) {
            error_log(sprintf('GLS Migration: Failed to copy file for order %d', $order_id));
            return false;
        }

        // Generate new secure URL
        $new_url = GLS_Shipping_For_Woo::get_label_download_url($original_filename);

        // Update order meta
        $order->update_meta_data('_gls_print_label', $new_url);
        $order->update_meta_data('_gls_print_label_old', $old_url); // Keep reference to old URL
        $order->save();

        // Delete old file
        @unlink($old_path);

        return true;
    }

    /**
     * Mark migration as complete
     */
    private function complete_migration()
    {
        $status = get_option(self::MIGRATION_STATUS_OPTION, array());
        $status['completed'] = true;
        $status['completed_at'] = current_time('mysql');
        update_option(self::MIGRATION_STATUS_OPTION, $status);

        // Unschedule the recurring action
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::MIGRATION_HOOK);
        }

        error_log(sprintf(
            'GLS Label Migration: Completed. Total migrated: %d, Failed: %d',
            isset($status['migrated']) ? $status['migrated'] : 0,
            isset($status['failed']) ? $status['failed'] : 0
        ));
    }

    /**
     * Display admin notice about migration status
     */
    public function display_migration_notice()
    {
        // Only show on WooCommerce pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'woocommerce') === false && $screen->id !== 'shop_order' && $screen->id !== 'edit-shop_order') {
            return;
        }

        $status = get_option(self::MIGRATION_STATUS_OPTION);
        if (!$status) {
            return;
        }

        // Don't show if completed more than 7 days ago
        if (isset($status['completed']) && $status['completed']) {
            if (isset($status['completed_at'])) {
                $completed_time = strtotime($status['completed_at']);
                if (time() - $completed_time > 7 * DAY_IN_SECONDS) {
                    return;
                }
            }

            // Show completion notice
            $message = sprintf(
                __('GLS Label Migration completed. %d labels migrated, %d failed.', 'gls-shipping-for-woocommerce'),
                isset($status['migrated']) ? $status['migrated'] : 0,
                isset($status['failed']) ? $status['failed'] : 0
            );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            return;
        }

        // Show progress notice
        $total = isset($status['total_orders']) ? $status['total_orders'] : 0;
        $migrated = isset($status['migrated']) ? $status['migrated'] : 0;
        $remaining = self::count_orders_needing_migration();

        if ($total > 0) {
            $progress = round(($migrated / $total) * 100);
            $message = sprintf(
                __('GLS Label Migration in progress: %d%% complete (%d/%d labels). This runs automatically in the background.', 'gls-shipping-for-woocommerce'),
                $progress,
                $migrated,
                $total
            );
            echo '<div class="notice notice-info"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Handle fallback for old-style URLs during migration
     * This allows admins to still access labels that haven't been migrated yet
     */
    public function handle_old_label_fallback()
    {
        if (!isset($_GET['gls_old_label']) || !isset($_GET['order_id']) || !isset($_GET['nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'gls_old_label_access')) {
            wp_die(__('Invalid security token.', 'gls-shipping-for-woocommerce'));
        }

        // Check user permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_die(__('You do not have permission to download shipping labels.', 'gls-shipping-for-woocommerce'));
        }

        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_die(__('Order not found.', 'gls-shipping-for-woocommerce'));
        }

        $label_url = $order->get_meta('_gls_print_label', true);
        
        if (empty($label_url)) {
            wp_die(__('Label not found.', 'gls-shipping-for-woocommerce'));
        }

        // Convert URL to path
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $label_url);

        if (!file_exists($file_path)) {
            wp_die(__('PDF label file not found.', 'gls-shipping-for-woocommerce'));
        }

        // Serve the file
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($file_path);
        exit;
    }

    /**
     * Get secure URL for a label (handles both old and new format)
     *
     * @param int $order_id
     * @return string|false
     */
    public static function get_secure_label_url($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $label_url = $order->get_meta('_gls_print_label', true);
        if (empty($label_url)) {
            return false;
        }

        // If already using new format, return as-is
        if (strpos($label_url, 'gls_download_label') !== false) {
            return $label_url;
        }

        // Old format - return fallback URL
        return add_query_arg(array(
            'gls_old_label' => 1,
            'order_id' => $order_id,
            'nonce' => wp_create_nonce('gls_old_label_access'),
        ), admin_url('admin.php'));
    }
}

// Initialize
new GLS_Shipping_Label_Migration();

