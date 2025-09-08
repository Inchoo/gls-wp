<?php

defined('ABSPATH') || exit;

/**
 * Class GLS_Shipping_Assets
 *
 * Handles the loading of scripts and additional HTML content required by the GLS Shipping functionality.
 */
class GLS_Shipping_Assets
{

    /**
     * Initializes the class by setting up hooks.
     *
     * Hooks into WordPress to enqueue scripts and add HTML content to the footer.
     */
    public static function init()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'load_scripts'));
        add_action('wp_footer', array(__CLASS__, 'footer_map'));
        add_filter('script_loader_tag', array(__CLASS__, 'add_module_type_attribute'), 10, 3);

        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'), 10, 1);
    }

    /**
     * Echoes GLS map dialog HTML in the footer.
     *
     * Outputs a GLS map dialog element, used for displaying the GLS map, to the footer of cart and checkout pages only.
     */
    public static function footer_map()
    {
        // Only output map dialogs on cart and checkout pages
        if (!is_cart() && !is_checkout()) {
            return;
        }

        echo '<gls-dpm-dialog country="hr" style="position: relative; z-index: 9999;" class="inchoo-gls-map gls-map-locker" filter-type="parcel-locker"></gls-dpm-dialog>';
        echo '<gls-dpm-dialog country="hr" style="position: relative; z-index: 9999;" class="inchoo-gls-map gls-map-shop" filter-type="parcel-shop"></gls-dpm-dialog>';
    }

    /**
     * Enqueues necessary JavaScript for the frontend.
     *
     * Loads the JavaScript required for the GLS Shipping functionality on cart and checkout pages only.
     */
    public static function load_scripts()
    {
        // Only load scripts on cart and checkout pages
        if (!is_cart() && !is_checkout()) {
            return;
        }

        $translation_array = array(
            'pickup_location' => __('Pickup Location', 'gls-shipping-for-woocommerce'),
            'name' => __('Name', 'gls-shipping-for-woocommerce'),
            'address' => __('Address', 'gls-shipping-for-woocommerce'),
            'country' => __('Country', 'gls-shipping-for-woocommerce'),
        );
        
        // Load GLS map widget
        wp_enqueue_script('gls-shipping-dpm', 'https://map.gls-croatia.com/widget/gls-dpm.js', array(), GLS_SHIPPING_VERSION, false);
        
        // Check if we're using WooCommerce Blocks checkout
        $is_checkout = is_checkout();
        $has_checkout_block = has_block('woocommerce/checkout');
        
        if ($has_checkout_block && $is_checkout) {
            // Blocks checkout - enqueue React components directly here
            if (file_exists(GLS_SHIPPING_ABSPATH . '/assets/blocks/build/gls-shipping-blocks-frontend.js')) {
                wp_enqueue_script(
                    'gls-shipping-blocks-frontend',
                    GLS_SHIPPING_URL . '/assets/blocks/build/gls-shipping-blocks-frontend.js',
                    array('wp-element', 'wp-i18n', 'wp-data', 'wc-blocks-checkout'),
                    GLS_SHIPPING_VERSION,
                    true
                );
                
                wp_set_script_translations(
                    'gls-shipping-blocks-frontend',
                    'gls-shipping-for-woocommerce',
                    GLS_SHIPPING_ABSPATH . 'languages'
                );
            }
            
            // Data will be passed via localize_script
            wp_localize_script(
                'gls-shipping-dpm',
                'glsShipping',
                array(
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
                )
            );
        } else {
            // Classic checkout
            wp_enqueue_script('gls-shipping-public', GLS_SHIPPING_URL . 'assets/js/gls-shipping-public.js', array('jquery', 'gls-shipping-dpm'), GLS_SHIPPING_VERSION, false);
            wp_localize_script(
                'gls-shipping-public',
                'gls_croatia',
                $translation_array
            );
        }
    }


    /**
     * Make sure script is loaded as module type
     */
    public static function add_module_type_attribute($tag, $handle, $src)
    {
        if ('gls-shipping-dpm' === $handle) {
            $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        return $tag;
    }


    /**
     * Register/queue backend scripts.
     */
    public static function admin_enqueue_scripts()
    {
        $currentScreen = get_current_screen();
        $screenID = $currentScreen->id;
        
        // Check if we're on order pages OR shipping settings page
        $isOrderPage = ($screenID === "shop_order" || $screenID === "woocommerce_page_wc-orders" || $screenID === "edit-shop_order");
        $isShippingSettingsPage = ($screenID === "woocommerce_page_wc-settings" && isset($_GET['tab']) && $_GET['tab'] === 'shipping');
        
        if ($isOrderPage || $isShippingSettingsPage) {
            $translation_array = array(
                'ajaxNonce' => wp_create_nonce('import-nonce'),
                'adminAjaxUrl' => admin_url('admin-ajax.php'),
                'pickup_location' => __('Pickup Location', 'gls-shipping-for-woocommerce'),
                'name' => __('Name', 'gls-shipping-for-woocommerce'),
                'address' => __('Address', 'gls-shipping-for-woocommerce'),
                'country' => __('Country', 'gls-shipping-for-woocommerce'),
            );
            
            // GLS map scripts will be loaded dynamically when needed
            
            wp_enqueue_script('gls-shipping-backend', GLS_SHIPPING_URL . 'includes/admin/assets/js/gls-shipping-admin.js', array('jquery'), time(), true);
            wp_localize_script(
                'gls-shipping-backend',
                'gls_croatia',
                $translation_array
            );
        }
    }


}

GLS_Shipping_Assets::init();
