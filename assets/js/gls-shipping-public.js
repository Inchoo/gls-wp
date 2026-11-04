/* eslint-disable */
(function () {
	"use strict";

	function domReady(callback) {
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", callback);
		} else {
			callback();
		}
	}

	domReady(function () {
		function onPickupSelected(e) {
			var pickupInfo = e.detail;
			var pickupInfoDiv =
				document.getElementById("gls-pickup-info");
			if (pickupInfoDiv) {
				pickupInfoDiv.innerHTML =
					"<strong>" +
					gls_croatia.pickup_location +
					":</strong><br>" +
					gls_croatia.name +
					": " +
					pickupInfo.name +
					"<br>" +
					gls_croatia.address +
					": " +
					pickupInfo.contact.address +
					", " +
					pickupInfo.contact.city +
					", " +
					pickupInfo.contact.postalCode +
					"<br>" +
					gls_croatia.country +
					": " +
					pickupInfo.contact.countryCode;
				pickupInfoDiv.style.display = "block";
			}

			var hiddenInput = document.getElementById(
				"gls-pickup-info-data"
			);
			if (!hiddenInput) {
				hiddenInput = document.createElement("input");
				hiddenInput.type = "hidden";
				hiddenInput.id = "gls-pickup-info-data";
				hiddenInput.name = "gls_pickup_info";
				document.forms["checkout"].appendChild(hiddenInput);
			}
			hiddenInput.value = JSON.stringify(pickupInfo);
		}

		var mapElements = document.getElementsByClassName("inchoo-gls-map");
		for (var i = 0; i < mapElements.length; i++) {
			mapElements[i].addEventListener("change", onPickupSelected);
		}

		// The dialogs are normally printed in wp_footer, but some setups never
		// output them, so create the missing one on demand.
		function getMapElement(mapClass) {
			var mapElement = document.querySelector("." + mapClass);
			if (mapElement) {
				return mapElement;
			}

			mapElement = document.createElement("gls-dpm-dialog");
			mapElement.setAttribute("country", "hr");
			mapElement.setAttribute("style", "position: relative; z-index: 9999;");
			mapElement.setAttribute("class", "inchoo-gls-map " + mapClass);
			mapElement.setAttribute(
				"filter-type",
				mapClass === "gls-map-locker" ? "parcel-locker" : "parcel-shop"
			);
			mapElement.addEventListener("change", onPickupSelected);
			document.body.appendChild(mapElement);

			return mapElement;
		}

		function showMapModal(mapClass) {
			// Use shipping country when "Ship to a different address" is checked,
			// so the map matches the country used by PHP calculate_shipping() (destination).
			var shipToDifferent = document.getElementById(
				"ship-to-different-address-checkbox"
			);
			var countryFieldId =
				shipToDifferent && shipToDifferent.checked
					? "shipping_country"
					: "billing_country";
			var countryField = document.getElementById(countryFieldId);
			var selectedCountry = countryField ? countryField.value : "";

			var mapElement = getMapElement(mapClass);
			var countryLower = selectedCountry.toLowerCase();
			mapElement.setAttribute("country", countryLower);

			// Apply filter-saturation for Hungary parcel locker only
			if (
				countryLower === "hu" &&
				mapClass === "gls-map-locker" &&
				gls_croatia.filter_saturation
			) {
				mapElement.setAttribute(
					"filter-saturation",
					gls_croatia.filter_saturation
				);
			} else {
				mapElement.removeAttribute("filter-saturation");
			}

			mapElement.showModal();
		}

		document.body.addEventListener("click", function (event) {
			if (
				event.target.matches(".dugme-gls_shipping_method_parcel_locker")
			) {
				showMapModal("gls-map-locker");
			} else if (
				event.target.matches(".dugme-gls_shipping_method_parcel_shop")
			) {
				showMapModal("gls-map-shop");
			}
		});

		function clearGLSPickupInfo() {
			var glsPickupInfo = document.getElementById("gls-pickup-info");
			var glsPickupInfoData = document.getElementById(
				"gls-pickup-info-data"
			);

			if (glsPickupInfo) {
				glsPickupInfo.innerHTML = "";
				glsPickupInfo.style.display = "none";
			}
			if (glsPickupInfoData) {
				glsPickupInfoData.value = "";
			}
		}

		// Clear the selected pickup location when the shipping method changes.
		// filter-type is fixed per dialog, so nothing else needs updating.
		function updateCheckout() {
			clearGLSPickupInfo();
		}

		// Event listener for shipping method change
		document.body.addEventListener("change", function (event) {
			if (event.target.name === "shipping_method[0]") {
				updateCheckout();
			}
		});

		document.body.addEventListener("updated_checkout", updateCheckout);
	});
})();
