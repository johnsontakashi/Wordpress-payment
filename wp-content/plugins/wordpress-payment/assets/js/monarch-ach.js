jQuery(document).ready(function($) {
    'use strict';
    
    // Validate routing number format
    $('#monarch_routing_number').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 9);
        
        if (this.value.length === 9) {
            validateRoutingNumber(this.value);
        }
    });
    
    // Validate account number format
    $('#monarch_account_number, #monarch_confirm_account').on('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
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
    
    // Real-time validation for account number confirmation
    $('#monarch_confirm_account').on('blur', function() {
        const accountNumber = $('#monarch_account_number').val();
        const confirmAccount = $(this).val();
        
        if (accountNumber && confirmAccount && accountNumber !== confirmAccount) {
            showError('Account numbers do not match');
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
            hideError();
        }
    });
    
    // Form validation before submission
    $('body').on('checkout_place_order_monarch_ach', function() {
        return validateMonarchForm();
    });
    
    function validateRoutingNumber(routingNumber) {
        // Basic ABA routing number validation using checksum
        if (routingNumber.length !== 9) {
            return false;
        }
        
        const digits = routingNumber.split('').map(Number);
        const checksum = (
            3 * (digits[0] + digits[3] + digits[6]) +
            7 * (digits[1] + digits[4] + digits[7]) +
            1 * (digits[2] + digits[5] + digits[8])
        ) % 10;
        
        if (checksum !== 0) {
            $('#monarch_routing_number').addClass('error');
            showError('Invalid routing number');
            return false;
        } else {
            $('#monarch_routing_number').removeClass('error');
            hideError();
            return true;
        }
    }
    
    function validateMonarchForm() {
        let isValid = true;
        hideError();
        
        // Clear previous error states
        $('#monarch-ach-form input, #monarch-ach-form select').removeClass('error');
        
        // Check if customer is already registered
        if ($('input[name="monarch_org_id"]').length > 0) {
            return true; // Customer already has bank account connected
        }
        
        // Required field validation
        const requiredFields = [
            {id: 'monarch_bank_name', message: 'Bank name is required'},
            {id: 'monarch_account_type', message: 'Account type is required'},
            {id: 'monarch_routing_number', message: 'Routing number is required'},
            {id: 'monarch_account_number', message: 'Account number is required'},
            {id: 'monarch_confirm_account', message: 'Please confirm account number'},
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
        
        // Routing number validation
        const routingNumber = $('#monarch_routing_number').val();
        if (routingNumber.length !== 9 || !validateRoutingNumber(routingNumber)) {
            showError('Please enter a valid 9-digit routing number');
            return false;
        }
        
        // Account number confirmation
        const accountNumber = $('#monarch_account_number').val();
        const confirmAccount = $('#monarch_confirm_account').val();
        if (accountNumber !== confirmAccount) {
            $('#monarch_confirm_account').addClass('error');
            showError('Account numbers do not match');
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
    
    function showError(message) {
        $('#monarch-ach-errors').html(message).show();
        $('html, body').animate({
            scrollTop: $('#monarch-ach-errors').offset().top - 100
        }, 500);
    }
    
    function hideError() {
        $('#monarch-ach-errors').hide();
    }
    
    // Bank account connection modal functionality
    function openBankConnectionModal(connectionUrl) {
        const modal = $('<div id="bank-connection-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; display: flex; align-items: center; justify-content: center;">' +
            '<div style="background: white; width: 80%; max-width: 600px; height: 80%; border-radius: 8px; position: relative;">' +
            '<button id="close-bank-modal" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>' +
            '<iframe src="' + connectionUrl + '" style="width: 100%; height: 100%; border: none; border-radius: 8px;"></iframe>' +
            '</div>' +
            '</div>');
        
        $('body').append(modal);
        
        $('#close-bank-modal').on('click', function() {
            $('#bank-connection-modal').remove();
        });
        
        // Listen for postMessage from iframe
        window.addEventListener('message', function(event) {
            if (event.data.type === 'BANK_CONNECTION_SUCCESS') {
                $('#bank-connection-modal').remove();
                // Refresh the checkout to show updated bank connection status
                $('body').trigger('update_checkout');
            }
        });
    }
    
    // Handle bank connection button click
    $(document).on('click', '#connect-bank-account', function(e) {
        e.preventDefault();
        
        const connectionUrl = $(this).data('url');
        if (connectionUrl) {
            openBankConnectionModal(connectionUrl);
        }
    });
    
    // Auto-fill form with test data in development mode
    if (typeof monarch_ach_params !== 'undefined' && monarch_ach_params.test_mode) {
        $('#fill-test-data').on('click', function(e) {
            e.preventDefault();
            $('#monarch_bank_name').val('Test Bank');
            $('#monarch_account_type').val('CHECKING');
            $('#monarch_routing_number').val('011000015');
            $('#monarch_account_number').val('123456789');
            $('#monarch_confirm_account').val('123456789');
            $('#monarch_phone').val('(555) 123-4567');
            $('#monarch_company').val('Test Company');
            $('#monarch_dob').val('1990-01-01');
        });
    }
});