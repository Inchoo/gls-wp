import { registerPlugin } from '@wordpress/plugins';
import { ExperimentalOrderShippingPackages } from '@woocommerce/blocks-checkout';
import { useState, useRef, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { getSetting } from '@woocommerce/settings';

import './index.css';

const settings = getSetting( 'gls-shipping_data', {} );

const GlsPickupComponent = () => {
	const [ pickupData, setPickupData ] = useState( null );
	const mapRef = useRef( null );

	// Get selected shipping rate and shipping address country from WC store
	const { selectedRate, shippingCountry } = useSelect( ( select ) => {
		const store = select( 'wc/store/cart' );
		const rates = store.getShippingRates();
		const selected = rates?.[ 0 ]?.shipping_rates?.find(
			( r ) => r.selected
		);
		const customerData = store.getCustomerData();
		return {
			selectedRate: selected,
			shippingCountry:
				customerData?.shippingAddress?.country || 'HR',
		};
	} );

	const needsPickup =
		selectedRate &&
		settings.mapSelectionMethods?.includes( selectedRate.method_id );

	const isLocker =
		selectedRate?.method_id === 'gls_shipping_method_parcel_locker' ||
		selectedRate?.method_id ===
			'gls_shipping_method_parcel_locker_zones';

	// Send pickup data as extension data with checkout submission
	const { __internalSetExtensionData } = useDispatch(
		'wc/store/checkout'
	);

	useEffect( () => {
		__internalSetExtensionData( 'gls-shipping/pickup-info', {
			pickup_data: pickupData ? JSON.stringify( pickupData ) : '',
		} );
	}, [ pickupData, __internalSetExtensionData ] );

	// Clear pickup data when shipping method changes
	useEffect( () => {
		setPickupData( null );
	}, [ selectedRate?.rate_id ] );

	// Set up dialog element attributes and styles
	useEffect( () => {
		const el = mapRef.current;
		if ( ! el ) return;
		el.setAttribute(
			'filter-type',
			isLocker ? 'parcel-locker' : 'parcel-shop'
		);
		el.style.position = 'relative';
		el.style.zIndex = '9999';
	}, [ needsPickup, isLocker ] );

	// Listen for map dialog change event
	useEffect( () => {
		const el = mapRef.current;
		if ( ! el ) return;
		const handler = ( e ) => setPickupData( e.detail );
		el.addEventListener( 'change', handler );
		return () => el.removeEventListener( 'change', handler );
	}, [ needsPickup ] );

	// Open map dialog with correct country and filter settings
	const openMap = useCallback( () => {
		const el = mapRef.current;
		if ( ! el ) return;

		const countryLower = shippingCountry.toLowerCase();
		el.setAttribute( 'country', countryLower );

		// Apply filter-saturation for Hungary parcel locker
		if (
			countryLower === 'hu' &&
			isLocker &&
			settings.filterSaturation
		) {
			el.setAttribute(
				'filter-saturation',
				settings.filterSaturation
			);
		} else {
			el.removeAttribute( 'filter-saturation' );
		}

		el.showModal();
	}, [ shippingCountry, isLocker ] );

	if ( ! needsPickup ) return null;

	return (
		<div className="gls-blocks-pickup">
			<button
				type="button"
				onClick={ openMap }
				className="wp-element-button gls-blocks-pickup-button"
			>
				{ isLocker
					? settings.i18n?.selectParcelLocker
					: settings.i18n?.selectParcelShop }
			</button>
			{ pickupData && (
				<div className="gls-blocks-pickup-info">
					<strong>
						{ settings.i18n?.pickupLocation }:
					</strong>
					<br />
					{ settings.i18n?.name }: { pickupData.name }
					<br />
					{ settings.i18n?.address }:{ ' ' }
					{ pickupData.contact?.address },{ ' ' }
					{ pickupData.contact?.city },{ ' ' }
					{ pickupData.contact?.postalCode }
					<br />
					{ settings.i18n?.country }:{ ' ' }
					{ pickupData.contact?.countryCode }
				</div>
			) }
			<gls-dpm-dialog ref={ mapRef }></gls-dpm-dialog>
		</div>
	);
};

registerPlugin( 'gls-shipping-blocks', {
	render: () => (
		<ExperimentalOrderShippingPackages>
			<GlsPickupComponent />
		</ExperimentalOrderShippingPackages>
	),
	scope: 'woocommerce-checkout',
} );
