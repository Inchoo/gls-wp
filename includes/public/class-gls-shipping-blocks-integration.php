<?php

/**
 * GLS Shipping Blocks Integration
 * 
 * Handles WooCommerce Blocks checkout integration for GLS shipping methods.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class GLS_Shipping_Blocks_Integration implements IntegrationInterface
{
    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name()
    {
        return 'gls-shipping';
    }

    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize()
    {
        error_log('GLS: Blocks integration initialize() called');
        $this->register_block_frontend_scripts();
        $this->register_block_editor_scripts();
        error_log('GLS: Scripts registered');
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles()
    {
        return array('gls-shipping-blocks-frontend');
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles()
    {
        return array('gls-shipping-blocks-editor');
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data()
    {
        return array(
            'gls_methods' => array(
                GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID,
                GLS_SHIPPING_METHOD_PARCEL_SHOP_ID,
                GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID,
                GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID
            ),
            'translations' => array(
                'select_parcel_locker' => __('Select Parcel Locker', 'gls-shipping-for-woocommerce'),
                'select_parcel_shop' => __('Select Parcel Shop', 'gls-shipping-for-woocommerce'),
                'pickup_location' => __('Pickup Location:', 'gls-shipping-for-woocommerce'),
                'name' => __('Name', 'gls-shipping-for-woocommerce'),
                'address' => __('Address', 'gls-shipping-for-woocommerce'),
                'country' => __('Country', 'gls-shipping-for-woocommerce'),
            ),
        );
    }

    /**
     * Register scripts for frontend.
     */
    public function register_block_frontend_scripts()
    {
        $script_path = '/assets/blocks/build/gls-shipping-blocks-frontend.js';
        $script_url = GLS_SHIPPING_URL . $script_path;
        $script_asset_path = GLS_SHIPPING_ABSPATH . '/assets/blocks/build/gls-shipping-blocks-frontend.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array('wp-element', 'wp-i18n', 'wc-blocks-checkout'),
                'version' => GLS_SHIPPING_VERSION,
            );

        wp_register_script(
            'gls-shipping-blocks-frontend',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
        
        error_log('GLS: Frontend script registered: ' . $script_url);

        wp_set_script_translations(
            'gls-shipping-blocks-frontend',
            'gls-shipping-for-woocommerce',
            GLS_SHIPPING_ABSPATH . 'languages'
        );
    }

    /**
     * Register scripts for editor.
     */
    public function register_block_editor_scripts()
    {
        $script_path = '/assets/blocks/build/gls-shipping-blocks-editor.js';
        $script_url = GLS_SHIPPING_URL . $script_path;
        $script_asset_path = GLS_SHIPPING_ABSPATH . '/assets/blocks/build/gls-shipping-blocks-editor.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array('wp-element', 'wp-i18n', 'wc-blocks-checkout'),
                'version' => GLS_SHIPPING_VERSION,
            );

        wp_register_script(
            'gls-shipping-blocks-editor',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }
}
