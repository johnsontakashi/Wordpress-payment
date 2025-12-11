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
            billing_state: $('#billing_state').val() || '',
            billing_postcode: $('#billing_postcode').val() || '',
            billing_country: $('#billing_country').val() || ''
        };

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

        // Open modal with iframe
        const modal = $('<div id="bank-connection-modal">' +
            '<div class="monarch-modal-overlay"></div>' +
            '<div class="monarch-modal-content">' +
            '<div class="monarch-modal-header">' +
            '<h3>Connect Your Bank Account</h3>' +
            '<p>Complete your bank account verification to proceed with payment.</p>' +
            '<button type="button" id="close-bank-modal" class="monarch-modal-close">&times;</button>' +
            '</div>' +
            '<div class="monarch-modal-body">' +
            '<iframe id="bank-linking-iframe" src="' + url + '"></iframe>' +
            '</div>' +
            '<div class="monarch-modal-footer">' +
            '<p>After connecting your bank, click the button below:</p>' +
            '<button type="button" id="monarch-bank-connected-btn" class="button alt">I\'ve Connected My Bank</button>' +
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
            $('#monarch-ach-form').prepend($errorDiv);
        }
        $errorDiv.html(message).show();

        $('html, body').animate({
            scrollTop: $errorDiv.offset().top - 100
        }, 500);
    }

    function hideError() {
        $('#monarch-ach-errors').hide();
    }

    // Handle disconnect bank account click
    $(document).on('click', '#monarch-disconnect-bank', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to disconnect your bank account? You will need to connect again.')) {
            return;
        }

        const $link = $(this);
        $link.text('Disconnecting...').css('pointer-events', 'none');

        $.ajax({
            url: monarch_ach_params.ajax_url,
            method: 'POST',
            data: {
                action: 'monarch_disconnect_bank',
                nonce: monarch_ach_params.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    showError(response.data || 'Failed to disconnect bank account');
                    $link.text('Use a different bank account').css('pointer-events', 'auto');
                }
            },
            error: function(xhr, status, error) {
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
