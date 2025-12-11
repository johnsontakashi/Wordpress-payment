jQuery(document).ready(function($) {
    'use strict';

    // Phone number formatting
    $(document).on('input', '#monarch_phone', function() {
        let value = this.value.replace(/[^0-9]/g, '');
        if (value.length >= 6) {
            value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
        } else if (value.length >= 3) {
            value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
        }
        this.value = value;
    });

    // Routing number formatting (numbers only, max 9 digits) - both main form and modal
    $(document).on('input', '#monarch_routing_number, #modal_routing_number', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 9);
    });

    // Account number formatting (numbers only) - both main form and modal
    $(document).on('input', '#monarch_account_number, #modal_account_number', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Handle method toggle buttons inside modal
    $(document).on('click', '.monarch-modal-method-btn', function(e) {
        e.preventDefault();
        const method = $(this).data('method');

        // Update button states
        $('.monarch-modal-method-btn').removeClass('active');
        $(this).addClass('active');

        // Update hidden field
        $('#monarch_entry_method').val(method);

        // Show/hide sections inside modal
        if (method === 'manual') {
            $('#modal-auto-section').hide();
            $('#modal-manual-section').show();
        } else {
            $('#modal-manual-section').hide();
            $('#modal-auto-section').show();
        }
    });

    // Handle Connect Bank Account button click
    $(document).on('click', '#monarch-connect-bank', function(e) {
        e.preventDefault();

        // Validate required fields first
        if (!validateCustomerInfo()) {
            return false;
        }

        const $button = $(this);
        const $spinner = $('#monarch-connect-spinner');

        // Disable button and show spinner
        $button.prop('disabled', true).text('Connecting...');
        $spinner.show();

        // Gather customer data from billing fields
        // State field can be select or input depending on country
        let billingState = $('#billing_state').val() || '';
        if (!billingState) {
            // Try select element
            billingState = $('select#billing_state').val() || '';
        }
        if (!billingState) {
            // Try input element
            billingState = $('input#billing_state').val() || '';
        }

        const customerData = {
            action: 'monarch_create_organization',
            nonce: monarch_ach_params.nonce,
            monarch_phone: $('#monarch_phone').val(),
            monarch_dob: $('#monarch_dob').val(),
            billing_first_name: $('#billing_first_name').val() || '',
            billing_last_name: $('#billing_last_name').val() || '',
            billing_email: $('#billing_email').val() || '',
            billing_address_1: $('#billing_address_1').val() || '',
            billing_address_2: $('#billing_address_2').val() || '',
            billing_city: $('#billing_city').val() || '',
            billing_state: billingState,
            billing_postcode: $('#billing_postcode').val() || '',
            billing_country: $('#billing_country').val() || ''
        };

        console.log('Customer data being sent:', customerData);

        // Create organization and get bank linking URL
        $.ajax({
            url: monarch_ach_params.ajax_url,
            method: 'POST',
            data: customerData,
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.bank_linking_url) {
                    // Store organization data
                    $('#monarch_org_id').val(response.data.org_id);

                    // Open bank linking in new window/popup
                    openBankConnectionWindow(response.data.bank_linking_url, response.data.org_id);
                } else {
                    showError(response.data || 'Failed to create organization. Please try again.');
                    $button.prop('disabled', false).text('Connect Bank Account');
                    $spinner.hide();
                }
            },
            error: function(xhr, status, error) {
                showError('Connection error: ' + error);
                $button.prop('disabled', false).text('Connect Bank Account');
                $spinner.hide();
            }
        });
    });

    // Open bank connection in a popup window
    function openBankConnectionWindow(connectionUrl, orgId) {
        // Replace placeholders in the URL
        const checkoutUrl = window.location.href;
        let url = connectionUrl
            .replace('{price}', '')
            .replace('{redirectUrl}', encodeURIComponent(checkoutUrl));

        // Open modal with toggle between automatic and manual
        const modal = $('<div id="bank-connection-modal">' +
            '<div class="monarch-modal-overlay"></div>' +
            '<div class="monarch-modal-content">' +
            '<div class="monarch-modal-header">' +
            '<h3>Connect Your Bank Account</h3>' +
            '<p>Choose how you want to connect your bank account.</p>' +
            '<button type="button" id="close-bank-modal" class="monarch-modal-close">&times;</button>' +
            '</div>' +
            // Toggle buttons
            '<div class="monarch-modal-toggle">' +
            '<button type="button" class="monarch-modal-method-btn active" data-method="auto">Automatic</button>' +
            '<button type="button" class="monarch-modal-method-btn" data-method="manual">Manual Entry</button>' +
            '</div>' +
            // Automatic section (iframe)
            '<div id="modal-auto-section" class="monarch-modal-section">' +
            '<div class="monarch-modal-body">' +
            '<iframe id="bank-linking-iframe" src="' + url + '"></iframe>' +
            '</div>' +
            '<div class="monarch-modal-footer">' +
            '<p>After connecting your bank, click the button below:</p>' +
            '<button type="button" id="monarch-bank-connected-btn" class="button alt">I\'ve Connected My Bank</button>' +
            '</div>' +
            '</div>' +
            // Manual entry section
            '<div id="modal-manual-section" class="monarch-modal-section" style="display:none;">' +
            '<div class="monarch-modal-manual-form">' +
            '<div class="monarch-manual-form-inner">' +
            '<div class="monarch-manual-warning">' +
            '<strong>Note:</strong> Manual bank entry is for testing purposes. For best results, use the Automatic option which securely verifies your bank account.' +
            '</div>' +
            '<p class="form-row">' +
            '<label for="modal_bank_name">Bank Name <span class="required">*</span></label>' +
            '<input id="modal_bank_name" type="text" placeholder="e.g., Chase, Bank of America">' +
            '</p>' +
            '<p class="form-row form-row-half">' +
            '<label for="modal_routing_number">Routing Number <span class="required">*</span></label>' +
            '<input id="modal_routing_number" type="text" maxlength="9" placeholder="9 digits">' +
            '</p>' +
            '<p class="form-row form-row-half">' +
            '<label for="modal_account_number">Account Number <span class="required">*</span></label>' +
            '<input id="modal_account_number" type="text" placeholder="Your account number">' +
            '</p>' +
            '<p class="form-row">' +
            '<label for="modal_account_type">Account Type <span class="required">*</span></label>' +
            '<select id="modal_account_type">' +
            '<option value="CHECKING">Checking</option>' +
            '<option value="SAVINGS">Savings</option>' +
            '</select>' +
            '</p>' +
            '<div class="monarch-modal-manual-footer">' +
            '<button type="button" id="monarch-manual-submit-modal" class="button alt">Submit Bank Details</button>' +
            '<span id="monarch-manual-spinner-modal" class="spinner" style="display:none;"></span>' +
            '</div>' +
            '<p class="monarch-manual-note">Your bank details are securely transmitted and encrypted.</p>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>');

        $('body').append(modal);

        // Close modal handler
        $(document).on('click', '#close-bank-modal', function() {
            $('#bank-connection-modal').remove();
            $('#monarch-connect-bank').prop('disabled', false).text('Connect Bank Account');
            $('#monarch-connect-spinner').hide();
        });

        // Close on overlay click
        $(document).on('click', '.monarch-modal-overlay', function() {
            $('#bank-connection-modal').remove();
            $('#monarch-connect-bank').prop('disabled', false).text('Connect Bank Account');
            $('#monarch-connect-spinner').hide();
        });

        // Handle "I've Connected My Bank" button
        $(document).on('click', '#monarch-bank-connected-btn', function() {
            $(this).prop('disabled', true).text('Verifying...');
            checkBankConnectionStatus(orgId);
        });

        // Listen for postMessage from iframe (if Monarch sends one)
        window.addEventListener('message', handleBankMessage);
    }

    // Handle messages from Monarch iframe
    function handleBankMessage(event) {
        // Check for various message types that Monarch might send
        if (event.data && (event.data.type === 'BANK_CONNECTION_SUCCESS' ||
            event.data.payTokenId || event.data.success)) {

            const payTokenId = event.data.payTokenId || event.data.paytoken_id;

            if (payTokenId) {
                completeBankConnection(payTokenId);
            }
        }
    }

    // Check if bank connection was successful
    function checkBankConnectionStatus(orgId) {
        // For now, we'll ask the user to manually confirm
        // In production, you might poll an API endpoint

        $.ajax({
            url: monarch_ach_params.ajax_url,
            method: 'POST',
            data: {
                action: 'monarch_check_bank_status',
                nonce: monarch_ach_params.nonce,
                org_id: orgId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.paytoken_id) {
                    completeBankConnection(response.data.paytoken_id);
                } else {
                    // Bank not yet connected, show message
                    alert('Please complete the bank connection in the window above, then click this button again.');
                    $('#monarch-bank-connected-btn').prop('disabled', false).text('I\'ve Connected My Bank');
                }
            },
            error: function() {
                // If check fails, try to complete anyway
                $('#bank-connection-modal').remove();
                showBankConnectedUI();
            }
        });
    }

    // Complete bank connection after successful linking
    function completeBankConnection(payTokenId) {
        $.ajax({
            url: monarch_ach_params.ajax_url,
            method: 'POST',
            data: {
                action: 'monarch_bank_connection_complete',
                nonce: monarch_ach_params.nonce,
                paytoken_id: payTokenId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update form with connection data
                    $('#monarch_org_id').val(response.data.org_id);
                    $('#monarch_paytoken_id').val(response.data.paytoken_id);
                    $('#monarch_bank_verified').val('true');

                    // Close modal
                    $('#bank-connection-modal').remove();
                    window.removeEventListener('message', handleBankMessage);

                    // Refresh checkout to show connected status
                    location.reload();
                } else {
                    showError(response.data || 'Failed to complete bank connection');
                    $('#monarch-bank-connected-btn').prop('disabled', false).text('I\'ve Connected My Bank');
                }
            },
            error: function(xhr, status, error) {
                showError('Connection error: ' + error);
                $('#monarch-bank-connected-btn').prop('disabled', false).text('I\'ve Connected My Bank');
            }
        });
    }

    // Show bank connected UI
    function showBankConnectedUI() {
        $('#monarch-ach-form').html(
            '<div class="monarch-bank-connected">' +
            '<p><strong>✓ Bank account connected</strong></p>' +
            '<p>Your bank account has been verified. You can now complete your order.</p>' +
            '</div>'
        );
        $('#monarch-connect-bank').prop('disabled', false).text('Connect Bank Account');
        $('#monarch-connect-spinner').hide();
    }

    // Validate customer information
    function validateCustomerInfo() {
        let isValid = true;
        hideError();

        // Clear previous error states
        $('#monarch-ach-form input').removeClass('error');

        // Check billing fields
        if (!$('#billing_first_name').val() || !$('#billing_last_name').val()) {
            showError('Please fill in your billing name above first.');
            return false;
        }

        if (!$('#billing_email').val()) {
            showError('Please fill in your email address above first.');
            return false;
        }

        // Check billing address fields required by Monarch
        if (!$('#billing_address_1').val()) {
            showError('Please fill in your billing address above first.');
            return false;
        }

        if (!$('#billing_city').val()) {
            showError('Please fill in your billing city above first.');
            return false;
        }

        // State field can be select or input
        let billingState = $('#billing_state').val() || $('select#billing_state').val() || $('input#billing_state').val() || '';
        if (!billingState) {
            showError('Please select your billing state/province above first.');
            return false;
        }

        if (!$('#billing_postcode').val()) {
            showError('Please fill in your billing postcode/ZIP above first.');
            return false;
        }

        // Required field validation
        const requiredFields = [
            {id: 'monarch_phone', message: 'Phone number is required'},
            {id: 'monarch_dob', message: 'Date of birth is required'}
        ];

        requiredFields.forEach(function(field) {
            const $field = $('#' + field.id);
            if (!$field.val() || $field.val().trim() === '') {
                $field.addClass('error');
                if (isValid) {
                    showError(field.message);
                }
                isValid = false;
            }
        });

        if (!isValid) {
            return false;
        }

        // Phone number validation
        const phone = $('#monarch_phone').val().replace(/[^0-9]/g, '');
        if (phone.length < 10) {
            $('#monarch_phone').addClass('error');
            showError('Please enter a valid 10-digit phone number');
            return false;
        }

        // Date of birth validation (must be 18+)
        const dob = new Date($('#monarch_dob').val());
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }

        if (age < 18) {
            $('#monarch_dob').addClass('error');
            showError('You must be at least 18 years old');
            return false;
        }

        return true;
    }

    // Form validation before checkout submission
    $(document).on('checkout_place_order_monarch_ach', function() {
        const $orgId = $('#monarch_org_id, input[name="monarch_org_id"]');
        const $payTokenId = $('#monarch_paytoken_id, input[name="monarch_paytoken_id"]');

        // Check if bank is connected (either from user meta or form)
        if ((!$orgId.val() || !$payTokenId.val()) && !$('.monarch-bank-connected').length) {
            showError('Please connect your bank account before placing your order.');
            return false;
        }

        return true;
    });

    function showError(message) {
        let $errorDiv = $('#monarch-ach-errors');
        if (!$errorDiv.length) {
            $errorDiv = $('<div id="monarch-ach-errors" class="woocommerce-error"></div>');
            // Try to prepend to form, or to bank-connected div, or to payment method
            if ($('#monarch-ach-form').length) {
                $('#monarch-ach-form').prepend($errorDiv);
            } else if ($('.monarch-bank-connected').length) {
                $('.monarch-bank-connected').before($errorDiv);
            } else {
                $('.payment_method_monarch_ach').append($errorDiv);
            }
        }
        $errorDiv.html(message).show();

        if ($errorDiv.length && $errorDiv.offset()) {
            $('html, body').animate({
                scrollTop: $errorDiv.offset().top - 100
            }, 500);
        }
    }

    function hideError() {
        $('#monarch-ach-errors').hide();
    }

    // Handle Manual Bank Entry submit from modal
    $(document).on('click', '#monarch-manual-submit-modal', function(e) {
        e.preventDefault();

        // Validate bank fields from modal
        if (!validateModalBankFields()) {
            return false;
        }

        const $button = $(this);
        const $spinner = $('#monarch-manual-spinner-modal');

        // Disable button and show spinner
        $button.prop('disabled', true).text('Processing...');
        $spinner.show();

        // Gather data from modal fields
        let billingState = $('#billing_state').val() || '';
        if (!billingState) {
            billingState = $('select#billing_state').val() || '';
        }
        if (!billingState) {
            billingState = $('input#billing_state').val() || '';
        }

        const requestData = {
            action: 'monarch_manual_bank_entry',
            nonce: monarch_ach_params.nonce,
            monarch_phone: $('#monarch_phone').val(),
            monarch_dob: $('#monarch_dob').val(),
            bank_name: $('#modal_bank_name').val(),
            routing_number: $('#modal_routing_number').val(),
            account_number: $('#modal_account_number').val(),
            account_type: $('#modal_account_type').val(),
            billing_first_name: $('#billing_first_name').val() || '',
            billing_last_name: $('#billing_last_name').val() || '',
            billing_email: $('#billing_email').val() || '',
            billing_address_1: $('#billing_address_1').val() || '',
            billing_address_2: $('#billing_address_2').val() || '',
            billing_city: $('#billing_city').val() || '',
            billing_state: billingState,
            billing_postcode: $('#billing_postcode').val() || '',
            billing_country: $('#billing_country').val() || ''
        };

        console.log('Manual bank entry data:', requestData);

        $.ajax({
            url: monarch_ach_params.ajax_url,
            method: 'POST',
            data: requestData,
            dataType: 'json',
            success: function(response) {
                console.log('Manual bank entry response:', response);
                if (response.success) {
                    // Update form with connection data
                    $('#monarch_org_id').val(response.data.org_id);
                    $('#monarch_paytoken_id').val(response.data.paytoken_id);
                    $('#monarch_bank_verified').val('true');

                    // Close modal and refresh checkout
                    $('#bank-connection-modal').remove();
                    location.reload();
                } else {
                    showModalError(response.data || 'Failed to connect bank account. Please try again.');
                    $button.prop('disabled', false).text('Submit Bank Details');
                    $spinner.hide();
                }
            },
            error: function(xhr, status, error) {
                console.log('Manual bank entry error:', status, error, xhr.responseText);
                showModalError('Connection error: ' + error);
                $button.prop('disabled', false).text('Submit Bank Details');
                $spinner.hide();
            }
        });
    });

    // Validate bank fields from modal
    function validateModalBankFields() {
        // Clear previous error states
        $('#modal-manual-section input, #modal-manual-section select').removeClass('error');

        const bankName = $('#modal_bank_name').val();
        const routingNumber = $('#modal_routing_number').val();
        const accountNumber = $('#modal_account_number').val();

        if (!bankName || bankName.trim() === '') {
            $('#modal_bank_name').addClass('error');
            showModalError('Bank name is required');
            return false;
        }

        if (!routingNumber || routingNumber.length !== 9) {
            $('#modal_routing_number').addClass('error');
            showModalError('Routing number must be exactly 9 digits');
            return false;
        }

        if (!accountNumber || accountNumber.trim() === '') {
            $('#modal_account_number').addClass('error');
            showModalError('Account number is required');
            return false;
        }

        return true;
    }

    // Show error inside modal
    function showModalError(message) {
        let $errorDiv = $('#monarch-modal-errors');
        if (!$errorDiv.length) {
            $errorDiv = $('<div id="monarch-modal-errors" class="monarch-modal-error"></div>');
            $('.monarch-manual-form-inner').prepend($errorDiv);
        }
        $errorDiv.html(message).show();
    }

    // Handle disconnect bank account click
    $(document).on('click', '#monarch-disconnect-bank', function(e) {
        e.preventDefault();
        console.log('Disconnect bank clicked');

        if (!confirm('Are you sure you want to disconnect your bank account? You will need to connect again.')) {
            return;
        }

        const $link = $(this);
        $link.text('Disconnecting...').css('pointer-events', 'none');

        console.log('Sending disconnect request to:', monarch_ach_params.ajax_url);

        $.ajax({
            url: monarch_ach_params.ajax_url,
            method: 'POST',
            data: {
                action: 'monarch_disconnect_bank',
                nonce: monarch_ach_params.nonce
            },
            dataType: 'json',
            success: function(response) {
                console.log('Disconnect response:', response);
                if (response.success) {
                    location.reload();
                } else {
                    showError(response.data || 'Failed to disconnect bank account');
                    $link.text('Use a different bank account').css('pointer-events', 'auto');
                }
            },
            error: function(xhr, status, error) {
                console.log('Disconnect error:', status, error, xhr.responseText);
                showError('Connection error: ' + error);
                $link.text('Use a different bank account').css('pointer-events', 'auto');
            }
        });
    });

    // Auto-fill form with test data in development mode
    if (typeof monarch_ach_params !== 'undefined' && monarch_ach_params.test_mode === 'yes') {
        $(document).on('click', '#monarch-connect-bank', function(e) {
            // Don't interfere with actual click handler
        });
    }
});
