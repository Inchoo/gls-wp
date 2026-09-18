<?php

/**
 * Helper for calculating shipment weight from a WooCommerce order.
 *
 * GLS expects the parcel weight in kilograms. This helper sums the product
 * weights of all order line items (respecting quantity) and converts the
 * total from the store's configured weight unit to kilograms.
 *
 * @since 1.5.4
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Weight_Helper
{
    /**
     * Calculate the total order weight in kilograms.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return float Total weight in kg (0 when no product weights are set).
     */
    public static function calculate_order_weight($order)
    {
        if (!$order instanceof WC_Order) {
            return 0.0;
        }

        $weight = 0.0;

        foreach ($order->get_items() as $item) {
            if (!($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $product_weight = $product->get_weight();
            if ($product_weight === '' || $product_weight === null) {
                continue;
            }

            $weight += (float) $product_weight * (float) $item->get_quantity();
        }

        if ($weight <= 0) {
            return 0.0;
        }

        // Convert from the store weight unit to kilograms.
        $weight_in_kg = function_exists('wc_get_weight') ? wc_get_weight($weight, 'kg') : $weight;

        return round((float) $weight_in_kg, 3);
    }

    /**
     * Resolve per-package weights (in kg) for an order.
     *
     * WooCommerce has no per-package weight support (we only know the total
     * order weight and the number of parcels). So the first package is
     * pre-filled with the full calculated weight and the remaining packages are
     * left empty for the merchant to fill in manually. Manually saved values
     * (_gls_weights) always take precedence per package.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @param int $count Number of packages (labels).
     * @return array Zero-indexed array of length $count; each element is a float
     *               weight in kg or '' (empty) when unknown.
     */
    public static function get_package_weights($order, $count)
    {
        $count = max(1, (int) $count);

        if (!$order instanceof WC_Order) {
            return array_fill(0, $count, '');
        }

        $calculated = self::calculate_order_weight($order);

        // Manual per-package overrides.
        $overrides = $order->get_meta('_gls_weights', true);
        if (!is_array($overrides)) {
            $overrides = array();
        }

        // Backward compatibility: migrate a legacy single _gls_weight override
        // into the first package slot when no per-package data exists yet.
        if (empty($overrides)) {
            $legacy = $order->get_meta('_gls_weight', true);
            if ($legacy !== '' && $legacy !== null) {
                $overrides[0] = (float) $legacy;
            }
        }

        $weights = array();
        for ($i = 0; $i < $count; $i++) {
            if (isset($overrides[$i]) && $overrides[$i] !== '' && (float) $overrides[$i] > 0) {
                $weights[$i] = (float) $overrides[$i];
            } elseif ($i === 0 && $calculated > 0) {
                // Pre-fill the first package with the full order weight.
                $weights[$i] = $calculated;
            } else {
                $weights[$i] = '';
            }
        }

        return $weights;
    }
}
