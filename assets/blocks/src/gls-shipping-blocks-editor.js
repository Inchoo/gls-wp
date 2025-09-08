// Use stable registerCheckoutFilters API (available since WooCommerce 8.6.0)
const registerCheckoutFilters = window.wc?.blocksCheckout?.registerCheckoutFilters;
import { __ } from '@wordpress/i18n';

// Editor preview - show placeholder button
const GlsMapButtonPreview = ({ shippingMethod }) => {
    const glsMethods = window.glsShipping?.gls_methods || [];
    const translations = window.glsShipping?.translations || {};
    
    if (!glsMethods.includes(shippingMethod.method_id)) {
        return null;
    }

    const buttonText = shippingMethod.method_id.includes('locker') 
        ? translations.select_parcel_locker || __('Select Parcel Locker', 'gls-shipping-for-woocommerce')
        : translations.select_parcel_shop || __('Select Parcel Shop', 'gls-shipping-for-woocommerce');

    return (
        <div className="gls-map-button-container" style={{ marginTop: '10px' }}>
            <button 
                type="button" 
                className="wp-element-button gls-map-button"
                disabled
                style={{
                    backgroundColor: '#0073aa',
                    color: 'white',
                    border: 'none',
                    padding: '8px 16px',
                    borderRadius: '3px',
                    opacity: '0.7'
                }}
            >
                {buttonText} (Preview)
            </button>
        </div>
    );
};

// Register filter for editor
if (registerCheckoutFilters) {
    registerCheckoutFilters('gls-shipping', {
        shippingMethodLabel: (defaultValue, extensions, args) => {
            const { shippingMethod } = args;
            
            return (
                <div>
                    {defaultValue}
                    <GlsMapButtonPreview shippingMethod={shippingMethod} />
                </div>
            );
        },
    });
}
