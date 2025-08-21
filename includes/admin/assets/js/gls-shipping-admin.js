(function () {
	jQuery(document).ready(function ($) {
		// GLS Multiple Accounts Management
		handleAccountModeToggle();
		handleAccountsGrid();
		
		function handleAccountModeToggle() {
			const $accountMode = $('#woocommerce_gls_shipping_method_account_mode');
			const $accountsRow = $('#gls_accounts_row');
			const $singleFields = $('#woocommerce_gls_shipping_method_client_id, #woocommerce_gls_shipping_method_username, #woocommerce_gls_shipping_method_password, #woocommerce_gls_shipping_method_country, #woocommerce_gls_shipping_method_mode').closest('tr');
			
			function toggleFields() {
				const mode = $accountMode.val();
				if (mode === 'multiple') {
					$accountsRow.show();
					$singleFields.hide();
				} else {
					$accountsRow.hide();
					$singleFields.show();
				}
			}
			
			$accountMode.on('change', toggleFields);
			toggleFields(); // Initial state
		}
		
		function handleAccountsGrid() {
			let accountIndex = $('.gls-account-row').length;
			
			// Add new account
			$(document).on('click', '#add-gls-account', function() {
				const newRow = createAccountRow(accountIndex);
				$('#gls-accounts-tbody').append(newRow);
				accountIndex++;
			});
			
			// Delete account
			$(document).on('click', '.delete-account', function() {
				if (confirm('Are you sure you want to delete this account?')) {
					$(this).closest('tr').remove();
					reindexAccounts();
				}
			});
			
			// Edit account (modal functionality)
			$(document).on('click', '.edit-account', function() {
				const $row = $(this).closest('tr');
				const index = $row.data('index');
				openEditModal($row, index);
			});
			
			// Handle active account radio button changes
			$(document).on('change', '.account-active-radio', function() {
				const selectedIndex = $(this).val();
				// Update hidden fields to reflect active status
				$('.account-active-hidden').val('0');
				$(`.gls-account-row[data-index="${selectedIndex}"] .account-active-hidden`).val('1');
			});
		}
		
		function createAccountRow(index) {
			const countries = {
				'AT': 'Austria', 'BE': 'Belgium', 'BG': 'Bulgaria', 'CZ': 'Czech Republic',
				'DE': 'Germany', 'DK': 'Denmark', 'ES': 'Spain', 'FI': 'Finland',
				'FR': 'France', 'GR': 'Greece', 'HR': 'Croatia', 'HU': 'Hungary',
				'IT': 'Italy', 'LU': 'Luxembourg', 'NL': 'Netherlands', 'PL': 'Poland',
				'PT': 'Portugal', 'RO': 'Romania', 'RS': 'Serbia', 'SI': 'Slovenia', 'SK': 'Slovakia'
			};
			
			let countryOptions = '';
			for (const [code, name] of Object.entries(countries)) {
				const selected = code === 'HR' ? 'selected' : '';
				countryOptions += `<option value="${code}" ${selected}>${name}</option>`;
			}
			
			return `
				<tr class="gls-account-row" data-index="${index}">
					<td>
						<input type="radio" name="woocommerce_gls_shipping_method_gls_accounts_grid_active" value="${index}" class="account-active-radio" />
					</td>
					<td>
						<span class="account-clientid-display">New Account</span>
					</td>
					<td>
						<button type="button" class="button button-small edit-account">Edit</button>
						<button type="button" class="button button-small delete-account">Delete</button>
						
						<!-- Hidden fields to store all account data -->
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][name]" value="" class="account-name" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][client_id]" value="" class="account-client-id" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][username]" value="" class="account-username" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][password]" value="" class="account-password" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][country]" value="HR" class="account-country" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][mode]" value="production" class="account-mode" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][active]" value="0" class="account-active-hidden" />
						
						<!-- Sender address fields -->
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][sender_name]" value="" class="account-sender-name" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][sender_street]" value="" class="account-sender-street" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][sender_city]" value="" class="account-sender-city" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][sender_postcode]" value="" class="account-sender-postcode" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][sender_country]" value="" class="account-sender-country" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][sender_phone]" value="" class="account-sender-phone" />
						<input type="hidden" name="woocommerce_gls_shipping_method_gls_accounts_grid[${index}][sender_email]" value="" class="account-sender-email" />
					</td>
				</tr>
			`;
		}
		
		function reindexAccounts() {
			$('.gls-account-row').each(function(newIndex) {
				$(this).attr('data-index', newIndex);
				$(this).find('input, select').each(function() {
					const name = $(this).attr('name');
					if (name) {
						const newName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
						$(this).attr('name', newName);
					}
				});
			});
		}
		
		function openEditModal($row, index) {
			const account = {
				name: $row.find('.account-name').val(),
				client_id: $row.find('.account-client-id').val(),
				username: $row.find('.account-username').val(),
				password: $row.find('.account-password').val(),
				country: $row.find('.account-country').val(),
				mode: $row.find('.account-mode').val(),
				sender_name: $row.find('.account-sender-name').val(),
				sender_street: $row.find('.account-sender-street').val(),
				sender_city: $row.find('.account-sender-city').val(),
				sender_postcode: $row.find('.account-sender-postcode').val(),
				sender_country: $row.find('.account-sender-country').val(),
				sender_phone: $row.find('.account-sender-phone').val(),
				sender_email: $row.find('.account-sender-email').val()
			};
			
			// Add modal styles if not already added
			if ($('#gls-modal-styles').length === 0) {
				$('head').append(`
					<style id="gls-modal-styles">
						#gls-account-modal {
							position: fixed;
							top: 0;
							left: 0;
							width: 100%;
							height: 100%;
							background-color: rgba(0,0,0,0.5);
							z-index: 100000;
						}
						.gls-modal-content {
							position: relative;
							background-color: #fff;
							margin: 5% auto;
							padding: 20px;
							width: 90%;
							max-width: 600px;
							max-height: 80vh;
							overflow-y: auto;
							border-radius: 4px;
							box-shadow: 0 4px 6px rgba(0,0,0,0.1);
						}
						.gls-accounts-table input[type="text"] {
							width: 100%;
							max-width: 150px;
						}
						.gls-accounts-table select {
							width: 100%;
							max-width: 120px;
						}
					</style>
				`);
			}
			
			const countries = {
				'AT': 'Austria', 'BE': 'Belgium', 'BG': 'Bulgaria', 'CZ': 'Czech Republic',
				'DE': 'Germany', 'DK': 'Denmark', 'ES': 'Spain', 'FI': 'Finland',
				'FR': 'France', 'GR': 'Greece', 'HR': 'Croatia', 'HU': 'Hungary',
				'IT': 'Italy', 'LU': 'Luxembourg', 'NL': 'Netherlands', 'PL': 'Poland',
				'PT': 'Portugal', 'RO': 'Romania', 'RS': 'Serbia', 'SI': 'Slovenia', 'SK': 'Slovakia'
			};
			
			let countryOptions = '';
			for (const [code, name] of Object.entries(countries)) {
				const selected = code === account.country ? 'selected' : '';
				countryOptions += `<option value="${code}" ${selected}>${name}</option>`;
			}
			
			let senderCountryOptions = '';
			for (const [code, name] of Object.entries(countries)) {
				const selected = code === account.sender_country ? 'selected' : '';
				senderCountryOptions += `<option value="${code}" ${selected}>${name}</option>`;
			}
			
			const modal = `
				<div id="gls-account-modal" style="display: none;">
					<div class="gls-modal-content">
						<h3>Edit GLS Account</h3>
						
						<h4>Account Details</h4>
						<table class="form-table">
							<tr>
								<th><label>Client ID *</label></th>
								<td><input type="text" id="modal-client-id" value="${account.client_id}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Username *</label></th>
								<td><input type="text" id="modal-username" value="${account.username}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Password *</label></th>
								<td><input type="password" id="modal-password" value="${account.password}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Country</label></th>
								<td>
									<select id="modal-country" style="width: 100%;">
										${countryOptions}
									</select>
								</td>
							</tr>
							<tr>
								<th><label>Mode</label></th>
								<td>
									<select id="modal-mode" style="width: 100%;">
										<option value="production" ${account.mode === 'production' ? 'selected' : ''}>Production</option>
										<option value="sandbox" ${account.mode === 'sandbox' ? 'selected' : ''}>Sandbox</option>
									</select>
								</td>
							</tr>
						</table>
						
						<h4>Sender Address (Optional - fallback to store default)</h4>
						<table class="form-table">
							<tr>
								<th><label>Company/Name</label></th>
								<td><input type="text" id="modal-sender-name" value="${account.sender_name}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Street Address</label></th>
								<td><input type="text" id="modal-sender-street" value="${account.sender_street}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>City</label></th>
								<td><input type="text" id="modal-sender-city" value="${account.sender_city}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Postcode</label></th>
								<td><input type="text" id="modal-sender-postcode" value="${account.sender_postcode}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Country</label></th>
								<td>
									<select id="modal-sender-country" style="width: 100%;">
										<option value="">Use store default</option>
										${senderCountryOptions}
									</select>
								</td>
							</tr>
							<tr>
								<th><label>Phone</label></th>
								<td><input type="text" id="modal-sender-phone" value="${account.sender_phone}" style="width: 100%;" /></td>
							</tr>
							<tr>
								<th><label>Email</label></th>
								<td><input type="email" id="modal-sender-email" value="${account.sender_email}" style="width: 100%;" /></td>
							</tr>
						</table>
						
						<p class="submit">
							<button type="button" class="button-primary" id="save-account">Save Changes</button>
							<button type="button" class="button" id="cancel-edit">Cancel</button>
						</p>
					</div>
				</div>
			`;
			
			if ($('#gls-account-modal').length === 0) {
				$('body').append(modal);
			}
			
			$('#gls-account-modal').show();
			
			$('#save-account').off('click').on('click', function() {
				// Validate required fields
				const clientId = $('#modal-client-id').val().trim();
				const username = $('#modal-username').val().trim();
				const password = $('#modal-password').val().trim();
				
				if (!clientId || !username || !password) {
					alert('Please fill in all required fields (marked with *)');
					return;
				}
				
				// Update hidden fields
				$row.find('.account-name').val(clientId); // Use client_id as name
				$row.find('.account-client-id').val(clientId);
				$row.find('.account-username').val(username);
				$row.find('.account-password').val(password);
				$row.find('.account-country').val($('#modal-country').val());
				$row.find('.account-mode').val($('#modal-mode').val());
				
				// Update sender address fields
				$row.find('.account-sender-name').val($('#modal-sender-name').val());
				$row.find('.account-sender-street').val($('#modal-sender-street').val());
				$row.find('.account-sender-city').val($('#modal-sender-city').val());
				$row.find('.account-sender-postcode').val($('#modal-sender-postcode').val());
				$row.find('.account-sender-country').val($('#modal-sender-country').val());
				$row.find('.account-sender-phone').val($('#modal-sender-phone').val());
				$row.find('.account-sender-email').val($('#modal-sender-email').val());
				
				// Update display values
				$row.find('.account-clientid-display').text(clientId);
				
				$('#gls-account-modal').hide();
			});
			
			$('#cancel-edit').off('click').on('click', function() {
				$('#gls-account-modal').hide();
			});
		}
		
		// Generating label in order details page
		$(".gls-print-label").on("click", function () {
			const orderId = $(this).attr("order-id");
			const $button = $(this);
			const count = $("#gls_label_count").val() || 1;
			
			// Get print position - check both possible IDs for regenerate vs new label
			let printPosition = $("#gls_print_position").val();
			if (!printPosition) {
				printPosition = $("#gls_print_position_new").val();
			}
			
			// Get COD reference - check both possible IDs for regenerate vs new label
			let codReference = $("#gls_cod_reference").val();
			if (!codReference) {
				codReference = $("#gls_cod_reference_new").val();
			}

			// Collect service options
			const services = collectServiceOptions();
			
			$button.prop("disabled", true);
			generateGLSLabel(orderId, $button, count, printPosition, codReference, services);
		});

		// Get parcel status in order details page
		$(".gls-get-status").on("click", function () {
			const orderId = $(this).attr("order-id");
			const parcelNumber = $(this).attr("parcel-number");
			const $button = $(this);
			$button.prop("disabled", true);
			getParcelStatus(orderId, parcelNumber, $button);
		});

		// Toggle service options visibility
		$("#gls-services-toggle, #gls-services-toggle-new").on("click", function (e) {
			e.preventDefault();
			const isNew = $(this).attr("id").includes("new");
			const optionsId = isNew ? "#gls-services-options-new" : "#gls-services-options";
			const arrowId = isNew ? "#gls-services-arrow-new" : "#gls-services-arrow";
			
			$(optionsId).slideToggle(200);
			$(arrowId).text($(optionsId).is(":visible") ? "▲" : "▼");
		});

		// Show/hide SMS text field based on SMS service checkbox
		$(document).on("change", "#gls_sms_service", function () {
			if ($(this).is(":checked")) {
				$("#gls_sms_text_container").slideDown(200);
			} else {
				$("#gls_sms_text_container").slideUp(200);
			}
		});

		// Generate label in order listing page
		$("a.gls-generate-label").on("click", function (e) {
			e.preventDefault();
			const orderId = $(this)
				.closest("tr")
				.find(".check-column input")
				.val();
			const $button = $(this);
			$button.addClass("disabled");
			generateGLSLabel(orderId, $button, 1, null, null, null); // Use defaults from config
		});

		function collectServiceOptions() {
			// Only collect if service options are visible
			if (!$("#gls-services-options").is(":visible") && !$("#gls-services-options-new").is(":visible")) {
				return null;
			}

			return {
				service_24h: $("#gls_service_24h").is(":checked") ? "yes" : "no",
				express_delivery_service: $("#gls_express_delivery_service").val() || "",
				contact_service: $("#gls_contact_service").is(":checked") ? "yes" : "no",
				flexible_delivery_service: $("#gls_flexible_delivery_service").is(":checked") ? "yes" : "no",
				flexible_delivery_sms_service: $("#gls_flexible_delivery_sms_service").is(":checked") ? "yes" : "no",
				sms_service: $("#gls_sms_service").is(":checked") ? "yes" : "no",
				sms_service_text: $("#gls_sms_service_text").val() || "",
				sms_pre_advice_service: $("#gls_sms_pre_advice_service").is(":checked") ? "yes" : "no",
				addressee_only_service: $("#gls_addressee_only_service").is(":checked") ? "yes" : "no",
				insurance_service: $("#gls_insurance_service").is(":checked") ? "yes" : "no"
			};
		}

		function generateGLSLabel(orderId, $button, count, printPosition, codReference, services) {
			const data = {
				action: "gls_generate_label",
				orderId: orderId,
				postNonce: gls_croatia.ajaxNonce,
				count: count,
			};
			
			// Add print position if provided
			if (printPosition) {
				data.printPosition = printPosition;
			}
			
			// Add COD reference if provided
			if (codReference) {
				data.codReference = codReference;
			}

			// Add services if provided
			if (services) {
				data.services = JSON.stringify(services);
			}
			
			$.ajax({
				url: gls_croatia.adminAjaxUrl,
				type: "POST",
				data: data,
				success: function (response) {
					if (response.success) {
						location.reload();
					} else {
						alert(
							"Error generating GLS Label: " + response.data.error
						);
					}
				},
				error: function () {
					alert("An error occurred while generating the GLS Label.");
				},
				complete: function () {
					// Re-enable the button
					if ($button.hasClass("gls-print-label")) {
						$button.prop("disabled", false);
					} else {
						$button.removeClass("disabled");
					}
				},
			});
		}

		function getParcelStatus(orderId, parcelNumber, $button) {
			// Clear previous status
			$("#gls-tracking-status").html('<p>Loading tracking information...</p>');

			$.ajax({
				url: gls_croatia.adminAjaxUrl,
				type: "POST",
				data: {
					action: "gls_get_parcel_status",
					orderId: orderId,
					parcelNumber: parcelNumber,
					postNonce: gls_croatia.ajaxNonce,
				},
				success: function (response) {
					if (response.success) {
						displayTrackingStatus(response.data.tracking_data);
					} else {
						$("#gls-tracking-status").html(
							'<div class="notice notice-error"><p>Error getting tracking status: ' + response.data.error + '</p></div>'
						);
					}
				},
				error: function () {
					$("#gls-tracking-status").html(
						'<div class="notice notice-error"><p>An error occurred while getting tracking status.</p></div>'
					);
				},
				complete: function () {
					$button.prop("disabled", false);
				},
			});
		}

		function displayTrackingStatus(trackingData) {
			let html = '<div class="notice notice-info"><h4>Tracking Information</h4>';
			
			// Basic info
			html += '<p><strong>Parcel Number:</strong> ' + trackingData.ParcelNumber + '</p>';
			html += '<p><strong>Client Reference:</strong> ' + trackingData.ClientReference + '</p>';
			
			// Status list
			if (trackingData.ParcelStatusList && trackingData.ParcelStatusList.length > 0) {
				html += '<h5>Status History:</h5>';
				html += '<div style="margin-top: 10px;">';
				
				trackingData.ParcelStatusList.forEach(function(status, index) {
					// Parse the .NET date format
					const dateMatch = status.StatusDate.match(/Date\((\d+)([+-]\d{4})?\)/);
					let formattedDate = status.StatusDate;
					if (dateMatch) {
						const timestamp = parseInt(dateMatch[1]);
						const date = new Date(timestamp);
						formattedDate = date.toLocaleString();
					}
					
					html += '<div style="border: 1px solid #ddd; margin-bottom: 10px; padding: 15px; border-radius: 4px; background-color: #f9f9f9;">';
					html += '<div style="margin-bottom: 8px;"><strong>Date:</strong> ' + formattedDate + '</div>';
					html += '<div style="margin-bottom: 8px;"><strong>Status:</strong> ' + status.StatusDescription + ' (' + status.StatusCode + ')</div>';
					html += '<div style="margin-bottom: 8px;"><strong>Location:</strong> ' + status.DepotCity + '</div>';
					if (status.StatusInfo) {
						html += '<div><strong>Info:</strong> ' + status.StatusInfo + '</div>';
					}
					html += '</div>';
				});
				
				html += '</div>';
			}
			
			html += '</div>';
			$("#gls-tracking-status").html(html);
		}
	});
})(jQuery);
