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
     * Resolve the effective parcel weight for an order in kilograms.
     *
     * Priority: a manually entered/saved order weight (_gls_weight),
     * otherwise the calculated weight from product data.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return float Weight in kg (0 when none available).
     */
    public static function get_order_weight($order)
    {
        if (!$order instanceof WC_Order) {
            return 0.0;
        }

        $saved_weight = $order->get_meta('_gls_weight', true);
        if ($saved_weight !== '' && $saved_weight !== null) {
            return (float) $saved_weight;
        }

        return self::calculate_order_weight($order);
    }
}
