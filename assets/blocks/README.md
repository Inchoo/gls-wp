# GLS Shipping WooCommerce Blocks Integration

## How the React Component Works

### 1. WordPress Registration

```php
// includes/public/class-gls-shipping-checkout-blocks.php - line 45
add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_integration'));
```

WooCommerce Blocks calls this action when all blocks are loaded.

### 2. Integration Class

```php
// includes/public/class-gls-shipping-blocks-integration.php
class GLS_Shipping_Blocks_Integration implements IntegrationInterface
```

This class implements the WooCommerce Blocks IntegrationInterface and:
- Registers JavaScript files
- Passes data about GLS shipping methods
- Defines dependencies (React, WP packages)

### 3. React Component Called Through Filter

In `assets/blocks/src/gls-shipping-blocks-frontend.js`:

```javascript
// This line registers a filter that "intercepts" shipping method labels
registerCheckoutFilters('gls-shipping', {
    shippingMethodLabel: (defaultValue, extensions, args) => {
        const { shippingMethod } = args;
        
        return (
            <div>
                {defaultValue}  {/* Original label */}
                <GlsMapButton shippingMethod={shippingMethod} />  {/* Our button */}
            </div>
        );
    },
});
```

### 4. Execution Flow

1. **WooCommerce Blocks checkout** renders shipping methods
2. **`registerCheckoutFilters`** intercepts each shipping method label
3. **Filter callback** checks if it's a GLS method
4. **If yes**, adds `<GlsMapButton>` component
5. **React component** renders with button
6. **User clicks** → map opens
7. **User selects** location → saved in checkout store
8. **Checkout submission** → data sent to server

### 5. Data Flow

```
Frontend (React) → WooCommerce Blocks Store → Store API → PHP Backend

Using Additional Checkout Fields API:
woocommerce_register_additional_checkout_field({
    id: 'gls-shipping/pickup-info',
    location: 'contact',
    type: 'text'
});
```

Data is automatically sent through WooCommerce Additional Checkout Fields API to:
`/wp-json/wc/store/v1/checkout`

### 6. Backend Processing

```php
// includes/public/class-gls-shipping-checkout-blocks.php
public function save_gls_pickup_on_order_creation($order, $data)
{
    if (isset($data['gls-shipping/pickup-info']) && !empty($data['gls-shipping/pickup-info'])) {
        $pickup_info = sanitize_text_field($data['gls-shipping/pickup-info']);
        $order->update_meta_data('_gls_pickup_info', $pickup_info);
    }
}
```

## File Structure

```
assets/blocks/
├── src/
│   ├── gls-shipping-blocks-frontend.js     # React component for frontend
│   └── gls-shipping-blocks-editor.js       # React component for editor
├── build/
│   ├── gls-shipping-blocks-frontend.js     # Compiled frontend bundle
│   ├── gls-shipping-blocks-frontend.asset.php  # Frontend dependencies
│   ├── gls-shipping-blocks-editor.js       # Compiled editor bundle
│   └── gls-shipping-blocks-editor.asset.php    # Editor dependencies
├── package.json                            # NPM dependencies
├── webpack.config.js                       # Build configuration
└── README.md                              # This documentation
```

## Class Structure

```
includes/public/
├── class-gls-shipping-checkout.php          # Classic checkout handling
├── class-gls-shipping-checkout-blocks.php   # WooCommerce Blocks checkout handling
└── class-gls-shipping-blocks-integration.php # Blocks integration registration
```

## Difference Between Classic and Blocks Checkout

| Classic Checkout | Blocks Checkout |
|------------------|-----------------|
| PHP filter `woocommerce_cart_shipping_method_full_label` | JavaScript filter `registerCheckoutFilters` |
| Vanilla JavaScript | React components |
| POST data in `$_POST['gls_pickup_info']` | Additional Checkout Fields API |
| jQuery event listeners | React hooks (`useState`, `useEffect`) |
| `woocommerce_checkout_update_order_meta` action | `woocommerce_checkout_create_order` action |

## Additional Checkout Fields API

WooCommerce 9.6.1+ introduced the Additional Checkout Fields API which we use for Blocks:

```php
woocommerce_register_additional_checkout_field([
    'id'            => 'gls-shipping/pickup-info',
    'label'         => 'GLS Pickup Information',
    'location'      => 'contact',
    'type'          => 'text',
    'required'      => false,
]);
```

This field is:
- Hidden from UI using CSS
- Populated by JavaScript when user selects pickup location
- Validated on server side
- Automatically saved to order meta

## Validation

### Frontend Validation
```javascript
// In React component - validates pickup selection before checkout
if (!pickupInfo && isGlsMethod) {
    // Show error message
    return false;
}
```

### Backend Validation
```php
// Validates JSON format and required fields
public function validate_gls_pickup_json($errors, $field_key, $field_value)
{
    // JSON format validation
    // Required fields validation (id, name, contact)
}
```

## Debugging

### Frontend
```javascript
// In browser console
console.log(window.glsShipping); // Data passed from PHP
console.log(wp.data.select('wc/store/checkout')); // WooCommerce store data
```

### Backend
```php
// In validation/save functions
error_log('Pickup data: ' . print_r($data, true));
```

## Compatibility

Plugin automatically detects checkout type:

```php
// includes/public/class-gls-shipping-assets.php
if (has_block('woocommerce/checkout') && is_checkout()) {
    // Blocks checkout - uses React components
} else {
    // Classic checkout - uses existing JS
}
```

## Building Assets

```bash
cd assets/blocks
npm install
npm run build
```

## Requirements

- WooCommerce 8.0+
- WordPress 6.0+
- WooCommerce Blocks 11.0+
- For Additional Checkout Fields: WooCommerce 9.6.1+

## Integration Points

1. **Shipping Method Filter**: Adds map button to GLS shipping methods
2. **Checkout Fields**: Hidden field for storing pickup location data
3. **Order Processing**: Saves pickup data to order meta
4. **Validation**: Ensures pickup location is selected for GLS methods
5. **Blocks Integration**: Registers with WooCommerce Blocks system