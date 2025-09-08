import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';


// Use stable registerCheckoutFilters API (available since WooCommerce 8.6.0)
const registerCheckoutFilters = window.wc?.blocksCheckout?.registerCheckoutFilters;


const GlsMapButton = ({ shippingMethod, extensions }) => {
    const [selectedPickup, setSelectedPickup] = useState(null);
    
    const glsMethods = window.glsShipping?.gls_methods || [];
    const translations = window.glsShipping?.translations || {};
    
    if (!glsMethods.includes(shippingMethod.method_id)) {
        return null;
    }
    
    const handleMapClick = () => {
        // Get country from shipping or billing fields, fallback to HR
        const shippingCountryField = document.getElementById('shipping-country') || 
                                     document.querySelector('select[name*="shipping_country"]') ||
                                     document.querySelector('input[name*="shipping_country"]');
        const billingCountryField = document.getElementById('billing_country') || 
                                    document.querySelector('select[name*="billing_country"]') ||
                                    document.querySelector('input[name*="billing_country"]');
        const selectedCountry = (shippingCountryField?.value || billingCountryField?.value || 'hr').toLowerCase();
        
        let mapClass;
        if (shippingMethod.method_id.includes('locker')) {
            mapClass = 'gls-map-locker';
        } else if (shippingMethod.method_id.includes('shop')) {
            mapClass = 'gls-map-shop';
        }
        
        if (mapClass) {
            const mapElement = document.querySelector(`.${mapClass}`);
            if (mapElement) {
                mapElement.setAttribute('country', selectedCountry.toLowerCase());
                mapElement.showModal();
            }
        }
    };

    // Listen for map selection
    useEffect(() => {
        const mapElements = document.getElementsByClassName('inchoo-gls-map');
        
        const handleMapChange = (e) => {
            const pickupInfo = e.detail;
            setSelectedPickup(pickupInfo);
            
            // Save to WooCommerce additional checkout field (contact location)
            const fieldElement = document.getElementById('contact-gls-shipping-pickup-info');
            
            if (fieldElement) {
                const jsonData = JSON.stringify(pickupInfo);
                
                // Set the value using React-compatible approach
                const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;
                nativeInputValueSetter.call(fieldElement, jsonData);
                
                // Trigger React synthetic events
                const reactEvent = new Event('input', { bubbles: true });
                fieldElement.dispatchEvent(reactEvent);
            }
        };

        if (mapElements.length > 0) {
            for (let i = 0; i < mapElements.length; i++) {
                mapElements[i].addEventListener('change', handleMapChange);
            }
        }

        return () => {
            if (mapElements.length > 0) {
                for (let i = 0; i < mapElements.length; i++) {
                    mapElements[i].removeEventListener('change', handleMapChange);
                }
            }
        };
    }, [__internalSetExtensionData]);

    const buttonText = shippingMethod.method_id.includes('locker') 
        ? translations.select_parcel_locker || __('Select Parcel Locker', 'gls-shipping-for-woocommerce')
        : translations.select_parcel_shop || __('Select Parcel Shop', 'gls-shipping-for-woocommerce');

    return (
        <div className="gls-map-button-container" style={{ marginTop: '10px' }}>
            <button 
                type="button" 
                className="wp-element-button gls-map-button"
                onClick={handleMapClick}
                style={{
                    backgroundColor: '#0073aa',
                    color: 'white',
                    border: 'none',
                    padding: '8px 16px',
                    borderRadius: '3px',
                    cursor: 'pointer'
                }}
            >
                {buttonText}
            </button>
            {selectedPickup && (
                <div className="gls-pickup-info" style={{
                    border: '1px solid #ddd',
                    padding: '10px',
                    marginTop: '10px',
                    backgroundColor: '#f9f9f9',
                    borderRadius: '3px'
                }}>
                    <strong>{translations.pickup_location || __('Pickup Location:', 'gls-shipping-for-woocommerce')}</strong><br/>
                    <strong>{translations.name || __('Name', 'gls-shipping-for-woocommerce')}:</strong> {selectedPickup.name}<br/>
                    <strong>{translations.address || __('Address', 'gls-shipping-for-woocommerce')}:</strong> {selectedPickup.contact.address}, {selectedPickup.contact.city}, {selectedPickup.contact.postalCode}<br/>
                    <strong>{translations.country || __('Country', 'gls-shipping-for-woocommerce')}:</strong> {selectedPickup.contact.countryCode}
                </div>
            )}
        </div>
    );
};

// Try multiple filter approaches
if (registerCheckoutFilters) {

    // Try different filter names
    const filterConfigs = [
        'shippingMethodLabel',
        'shippingMethodName', 
        'shippingRateLabel',
        'shippingOptionLabel'
    ];

    filterConfigs.forEach(filterName => {
        registerCheckoutFilters(`gls-${filterName}`, {
            [filterName]: (defaultValue, extensions, args) => {
                
                const { shippingMethod } = args || {};
                
                if (shippingMethod && window.glsShipping?.gls_methods?.includes(shippingMethod.method_id)) {
                    return (
                        <div>
                            {defaultValue}
                            <GlsMapButton shippingMethod={shippingMethod} extensions={extensions} />
                        </div>
                    );
                }
                
                return defaultValue;
            },
        });
    });

}

// Global map selection handler for blocks checkout
function handleGLSMapSelection() {
    const mapElements = document.getElementsByClassName("inchoo-gls-map");

    if (mapElements.length > 0) {
        for (var i = 0; i < mapElements.length; i++) {
            // Remove existing listeners to prevent duplicates
            mapElements[i].removeEventListener("change", handleMapChange);
            mapElements[i].addEventListener("change", handleMapChange);
        }
    }
}

function handleMapChange(e) {
    const pickupInfo = e.detail;
    
    // Find or create pickup info display area
    let pickupInfoDiv = document.getElementById("gls-pickup-info-blocks");
    
    if (!pickupInfoDiv) {
        // Create new pickup info div - position it after shipping options
        pickupInfoDiv = document.createElement("div");
        pickupInfoDiv.id = "gls-pickup-info-blocks";
        pickupInfoDiv.style.cssText = `
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            background-color: #f9f9f9;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.4;
        `;
        
        // Insert after shipping rates control
        const shippingControl = document.querySelector('.wc-block-components-shipping-rates-control');
        if (shippingControl) {
            shippingControl.parentNode.insertBefore(pickupInfoDiv, shippingControl.nextSibling);
        }
    }
    
    // Get translations
    const translations = window.glsShipping?.translations || {};
    
    // Update pickup info content
    pickupInfoDiv.innerHTML = `
        <strong>${translations.pickup_location || 'Pickup Location:'}:</strong><br>
        <strong>${translations.name || 'Name'}:</strong> ${pickupInfo.name}<br>
        <strong>${translations.address || 'Address'}:</strong> ${pickupInfo.contact.address}, ${pickupInfo.contact.city}, ${pickupInfo.contact.postalCode}<br>
        <strong>${translations.country || 'Country'}:</strong> ${pickupInfo.contact.countryCode}
    `;
    
    pickupInfoDiv.style.display = "block";
    
                // Save pickup info to additional checkout field (contact location)
                const fieldElement = document.getElementById('contact-gls-shipping-pickup-info');
    
    if (fieldElement) {
        // Set JSON data directly to the field instead of simple string
        const jsonData = JSON.stringify(pickupInfo);
        
        
        // Set the value directly using React-compatible approach
        const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;
        nativeInputValueSetter.call(fieldElement, jsonData);
        
                // Trigger React synthetic events
                const reactEvent = new Event('input', { bubbles: true });
                fieldElement.dispatchEvent(reactEvent);
    }
}

// Function to clear pickup info when shipping method changes
function clearGLSPickupInfo() {
    const glsPickupInfo = document.getElementById("gls-pickup-info-blocks");

    if (glsPickupInfo) {
        glsPickupInfo.innerHTML = "";
        glsPickupInfo.style.display = "none";
    }
    
    // Clear the additional checkout field (contact location) using React-compatible method
    try {
        const fieldElement = document.getElementById('contact-gls-shipping-pickup-info');
        if (fieldElement) {
            // Use React-compatible value setting
            const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value").set;
            nativeInputValueSetter.call(fieldElement, '');
            
            // Trigger React synthetic events
            const inputEvent = new Event('input', { bubbles: true });
            fieldElement.dispatchEvent(inputEvent);
            
            const changeEvent = new Event('change', { bubbles: true });
            fieldElement.dispatchEvent(changeEvent);
            
        }
    } catch (error) {
        // Silent error handling
    }
}

// Listen for shipping method changes in blocks checkout
function setupShippingMethodListener() {
    // Listen for changes to radio buttons
    document.addEventListener('change', (e) => {
        if (e.target.matches('input[name*="radio-control"]') && e.target.type === 'radio') {
            
            // Check if new method is NOT a pickup method
            const isPickupMethod = getPickupMethods().some(method => e.target.value.includes(method));
            
            if (!isPickupMethod) {
                clearGLSPickupInfo();
            }
        }
    });
}

// Helper function to create GLS map button
function createGLSMapButton(selectedValue) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'gls-map-button';
    button.style.cssText = 'display: block; margin-top: 8px; background: #0073aa; color: white; border: none; padding: 8px 16px; border-radius: 3px; cursor: pointer; font-size: 14px; width: auto;';
    
    const translations = window.glsShipping?.translations || {};
    if (selectedValue.includes('locker')) {
        button.textContent = translations.select_parcel_locker || 'Select Parcel Locker';
    } else {
        button.textContent = translations.select_parcel_shop || 'Select Parcel Shop';
    }
    
    button.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Get country from shipping or billing fields, fallback to HR
        const shippingCountryField = document.getElementById('shipping-country') || 
                                     document.querySelector('select[name*="shipping_country"]') ||
                                     document.querySelector('input[name*="shipping_country"]');
        const billingCountryField = document.getElementById('billing_country') || 
                                    document.querySelector('select[name*="billing_country"]') ||
                                    document.querySelector('input[name*="billing_country"]');
        const selectedCountry = (shippingCountryField?.value || billingCountryField?.value || 'hr').toLowerCase();
        let mapClass;
        
        if (selectedValue.includes('locker')) {
            mapClass = 'gls-map-locker';
        } else if (selectedValue.includes('shop')) {
            mapClass = 'gls-map-shop';
        }
        
        if (mapClass) {
            const mapElement = document.querySelector(`.${mapClass}`);
            if (mapElement) {
                mapElement.setAttribute('country', selectedCountry.toLowerCase());
                mapElement.showModal();
            }
        }
    });
    
    return button;
}

// Helper function to get pickup methods array
function getPickupMethods() {
    return [
        'gls_shipping_method_parcel_locker',
        'gls_shipping_method_parcel_shop', 
        'gls_shipping_method_parcel_locker_zones',
        'gls_shipping_method_parcel_shop_zones'
    ];
}

// Function to check initial shipping method selection and add buttons if needed
function checkInitialShippingMethod() {
    const selectedShippingInput = document.querySelector('input[name*="radio-control"]:checked');
    if (selectedShippingInput) {
        const selectedValue = selectedShippingInput.value;
        const isPickupMethod = getPickupMethods().some(method => selectedValue.includes(method));
        
        if (isPickupMethod) {
            const selectedLabel = selectedShippingInput.closest('.wc-block-components-radio-control__option');
            if (selectedLabel && !selectedLabel.querySelector('.gls-map-button')) {
                const optionLayout = selectedLabel.querySelector('.wc-block-components-radio-control__option-layout');
                if (optionLayout) {
                    const button = createGLSMapButton(selectedValue);
                    optionLayout.insertAdjacentElement('afterend', button);
                }
            }
        }
    }
}

// Initialize map selection handler when DOM is ready
setTimeout(() => {
    handleGLSMapSelection();
    setupShippingMethodListener();
    
    // Add buttons when GLS method is selected
    addGLSButtonsOnMethodChange();
    
    // Check if GLS method is already selected on page load
    checkInitialShippingMethod();
}, 1500);

// Function to add GLS buttons when shipping method changes
function addGLSButtonsOnMethodChange() {
    document.addEventListener('change', (e) => {
        if (e.target.matches('input[name*="radio-control"]') && e.target.type === 'radio') {
            const selectedValue = e.target.value;
            const isPickupMethod = getPickupMethods().some(method => selectedValue.includes(method));
            
            // Remove all existing GLS buttons first
            document.querySelectorAll('.gls-map-button').forEach(btn => btn.remove());
            
            if (isPickupMethod) {
                // Add button for the selected GLS method
                setTimeout(() => {
                    const selectedLabel = document.querySelector(`input[value="${selectedValue}"]`)?.closest('.wc-block-components-radio-control__option');
                    if (selectedLabel && !selectedLabel.querySelector('.gls-map-button')) {
                        const optionLayout = selectedLabel.querySelector('.wc-block-components-radio-control__option-layout');
                        if (optionLayout) {
                            const button = createGLSMapButton(selectedValue);
                            optionLayout.insertAdjacentElement('afterend', button);
                        }
                    }
                }, 100);
            }
        }
    });
}
