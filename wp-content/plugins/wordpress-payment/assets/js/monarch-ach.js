jQuery(document).ready(function($) {
    'use strict';
    
    // Phone number formatting
    $('#monarch_phone').on('input', function() {
        let value = this.value.replace(/[^0-9]/g, '');
        if (value.length >= 6) {
            value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
        } else if (value.length >= 3) {
            value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
        }
        this.value = value;
    });
    
    // Handle bank connection button click
    $(document).on('click', '#connect-bank-account', function(e) {
        e.preventDefault();
        
        // Validate required fields first
        if (!validateCustomerInfo()) {
            return false;
        }
        
        const $button = $(this);
        const $spinner = $('#bank-connect-spinner');
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.show();
        
        // Gather customer data
        const customerData = {
            action: 'monarch_create_organization',
            nonce: monarch_ach_params.nonce,
            monarch_phone: $('#monarch_phone').val(),
            monarch_company: $('#monarch_company').val(),
            monarch_dob: $('#monarch_dob').val(),
            billing_first_name: $('#billing_first_name').val() || '',
            billing_last_name: $('#billing_last_name').val() || '',
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
                    
                    // Open bank linking modal
                    openBankConnectionModal(response.data.bank_linking_url);
                } else {
                    showError(response.data || 'Failed to create organization');
                }
            },
            error: function(xhr, status, error) {
                showError('Connection error: ' + error);
            },
            complete: function() {
                // Re-enable button and hide spinner
                $button.prop('disabled', false);
                $spinner.hide();
            }
        });
    });
    
    // Bank account connection modal functionality
    function openBankConnectionModal(connectionUrl) {
        const modal = $('<div id="bank-connection-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; display: flex; align-items: center; justify-content: center;">' +
            '<div class="modal-content" style="background: white; width: 90%; max-width: 800px; height: 90%; max-height: 600px; border-radius: 12px; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">' +
            '<div style="padding: 20px; border-bottom: 1px solid #eee; background: #f8f9fa; border-radius: 12px 12px 0 0;">' +
            '<h3 style="margin: 0; color: #333;">Connect Your Bank Account</h3>' +
            '<p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">Complete your bank account connection to proceed with payment.</p>' +
            '</div>' +
            '<button id="close-bank-modal" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 28px; cursor: pointer; color: #999; padding: 5px; line-height: 1;">&times;</button>' +
            '<iframe id="bank-linking-iframe" src="' + connectionUrl + '" style="width: 100%; height: calc(100% - 80px); border: none; border-radius: 0 0 12px 12px;"></iframe>' +
            '</div>' +
            '</div>');
        
        $('body').append(modal);
        
        // Close modal handler
        $('#close-bank-modal').on('click', function() {
            $('#bank-connection-modal').remove();
        });
        
        // Close on background click
        $('#bank-connection-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).remove();
            }
        });
        
        // Listen for postMessage from iframe
        window.addEventListener('message', function(event) {
            if (event.data.type === 'BANK_CONNECTION_SUCCESS') {
                const payTokenId = event.data.payTokenId;
                
                if (payTokenId) {
                    // Complete the bank connection process
                    completeBankConnection(payTokenId);
                }
            }
        });
    }
    
    // Complete bank connection after successful linking
    function completeBankConnection(payTokenId) {
        const connectionData = {
            action: 'monarch_bank_connection_complete',
            nonce: monarch_ach_params.nonce,
            paytoken_id: payTokenId
        };
        
        $.ajax({
            url: monarch_ach_params.ajax_url,
            method: 'POST',
            data: connectionData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update form with connection data
                    $('#monarch_org_id').val(response.data.org_id);
                    $('#monarch_paytoken_id').val(response.data.paytoken_id);
                    $('#bank_connected').val('true');
                    
                    // Hide customer info form and show success message
                    $('#monarch-customer-info').hide();
                    $('#bank-connection-status').show();
                    
                    // Close modal
                    $('#bank-connection-modal').remove();
                    
                    // Update checkout
                    $('body').trigger('update_checkout');
                } else {
                    showError(response.data || 'Failed to complete bank connection');
                }
            },
            error: function(xhr, status, error) {
                showError('Connection error: ' + error);
            }
        });
    }
    
    // Validate customer information
    function validateCustomerInfo() {
        let isValid = true;
        hideError();
        
        // Clear previous error states
        $('#monarch-ach-form input').removeClass('error');
        
        // Required field validation
        const requiredFields = [
            {id: 'monarch_phone', message: 'Phone number is required'},
            {id: 'monarch_dob', message: 'Date of birth is required'}
        ];
        
        requiredFields.forEach(function(field) {
            const $field = $('#' + field.id);
            if (!$field.val() || $field.val().trim() === '') {
                $field.addClass('error');
                if (isValid) { // Show only the first error
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
        const age = today.getFullYear() - dob.getFullYear();
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
    $('body').on('checkout_place_order_monarch_ach', function() {
        // Check if bank is connected
        if ($('#bank_connected').val() !== 'true') {
            showError('Please connect your bank account before proceeding with payment.');
            return false;
        }
        
        return true;
    });
    
    function showError(message) {
        $('#monarch-ach-errors').html(message).show();
        $('html, body').animate({
            scrollTop: $('#monarch-ach-errors').offset().top - 100
        }, 500);
    }
    
    function hideError() {
        $('#monarch-ach-errors').hide();
    }

    // Handle disconnect bank account click
    $(document).on('click', '#monarch-disconnect-bank', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to disconnect your bank account? You will need to enter your bank details again.')) {
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
                    // Refresh checkout to show bank entry form
                    $('body').trigger('update_checkout');
                    // Reload page to ensure form is fully reset
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
    if (typeof monarch_ach_params !== 'undefined' && monarch_ach_params.test_mode) {
        // Add test data button if in test mode
        if ($('#connect-bank-account').length && !$('#fill-test-data').length) {
            $('#connect-bank-account').after(
                '<button type="button" id="fill-test-data" class="button" style="margin-left: 10px;">Fill Test Data</button>'
            );
        }
        
        $(document).on('click', '#fill-test-data', function(e) {
            e.preventDefault();
            $('#monarch_phone').val('(555) 123-4567');
            $('#monarch_company').val('Test Company');
            $('#monarch_dob').val('1990-01-01');
        });
    }
});