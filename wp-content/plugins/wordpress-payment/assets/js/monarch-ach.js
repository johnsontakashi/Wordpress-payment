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

    // Lock field and show display mode with Edit button
    function lockField(fieldType) {
        const wrapper = $('#monarch-' + fieldType + '-wrapper');
        const input = wrapper.find('input');
        const inputContainer = wrapper.find('.monarch-field-input');
        const displayContainer = wrapper.find('.monarch-field-display');
        const displayValue = wrapper.find('.monarch-field-value');

        let displayText = input.val();

        // Format the display text
        if (fieldType === 'dob' && displayText) {
            // Format date as MM/DD/YYYY
            const date = new Date(displayText);
            displayText = (date.getMonth() + 1).toString().padStart(2, '0') + '/' +
                          date.getDate().toString().padStart(2, '0') + '/' +
                          date.getFullYear();
        }

        displayValue.text(displayText);
        inputContainer.hide();
        displayContainer.show();
        wrapper.addClass('confirmed');
    }

    // Unlock field for editing
    function unlockField(fieldType) {
        const wrapper = $('#monarch-' + fieldType + '-wrapper');
        const inputContainer = wrapper.find('.monarch-field-input');
        const displayContainer = wrapper.find('.monarch-field-display');

        displayContainer.hide();
        inputContainer.show();
        wrapper.removeClass('confirmed');
        wrapper.find('input').focus();
    }

    // Handle Edit button click
    $(document).on('click', '.monarch-edit-btn', function(e) {
        e.preventDefault();
        const fieldType = $(this).data('field');
        unlockField(fieldType);
    });

    // Lock fields when user leaves the field (blur) if valid
    $(document).on('blur', '#monarch_phone', function() {
        const phone = $(this).val().replace(/[^0-9]/g, '');
        if (phone.length >= 10) {
            lockField('phone');
        }
    });

    $(document).on('blur', '#monarch_dob', function() {
        const dob = $(this).val();
        if (dob) {
            // Validate age (must be 18+)
            const dobDate = new Date(dob);
            const today = new Date();
            let age = today.getFullYear() - dobDate.getFullYear();
            const monthDiff = today.getMonth() - dobDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
                age--;
            }
            if (age >= 18) {
                lockField('dob');
            }
        }
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
            error: function(jqXHR, textStatus, errorThrown) {
                showError('Connection error: ' + errorThrown);
                $button.prop('disabled', false).text('Connect Bank Account');
                $spinner.hide();
            }
        });
    });

    // Open bank connection in a popup window
    function openBankConnectionWindow(connectionUrl, orgId) {
        let url = connectionUrl;

        console.log('Original bank linking URL from Monarch:', connectionUrl);

        // Get the current page URL for callbacks
        const currentUrl = window.location.href;
        let locationUrl = currentUrl.split('?')[0]; // Clean URL without query params

        // Build callback URL - use a simple query param that's guaranteed to be detected
        // The ?monarch_bank_callback=1 parameter will be caught by our PHP handler
        let callbackUrl = locationUrl + '?monarch_bank_callback=1&org_id=' + orgId;

        // Clean up URL formatting and replace placeholders properly
        if (url.includes('{redirectUrl}') || url.includes('{price}')) {
            // Replace placeholders with actual values
            url = url.replace(/\{price\}/g, '100'); // Default price or get from order
            // Use the callback URL with parameters for better redirect detection
            url = url.replace(/\{redirectUrl\}/g, encodeURIComponent(callbackUrl));

            console.log('Replaced URL placeholders - redirectUrl:', callbackUrl, 'price: 100');
        }

        // Add locationURL parameter for Yodlee FastLink postMessage support
        // This is required for postMessage callbacks to work (especially on localhost)
        // See: https://developer.yodlee.com/docs/fastlink/4.0/advanced
        if (!url.includes('locationURL=') && !url.includes('locationUrl=')) {
            const separator = url.includes('?') ? '&' : (url.includes('#') ? '&' : '?');
            // For hash-based URLs, we need to add it before the hash or within the hash params
            if (url.includes('#')) {
                // Add locationURL to the hash fragment parameters
                url = url + '&locationURL=' + encodeURIComponent(locationUrl);
            } else {
                url = url + separator + 'locationURL=' + encodeURIComponent(locationUrl);
            }
            console.log('Added locationURL parameter for postMessage support:', locationUrl);
        }

        // Fix malformed URLs with multiple hash fragments
        if (url.includes('#') && url.split('#').length > 2) {
            const parts = url.split('#');
            const baseUrl = parts[0];
            // Join all hash parts with & instead of multiple #
            let hashFragment = parts.slice(1).join('&');
            
            // Clean up any remaining invalid patterns
            hashFragment = hashFragment.replace(/&+/g, '&').replace(/^&|&$/g, '');
            
            url = baseUrl + '#' + hashFragment;
        }

        console.log('Final iframe URL:', url);
        console.log('URL Analysis:', {
            'hasPlaceholders': url.includes('{'),
            'isLocalhost': url.includes('localhost'),
            'isHTTPS': url.startsWith('https'),
            'hasDoubleEncoding': url.includes('http%3A')
        });

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
            '<iframe id="bank-linking-iframe" src="' + url + '" sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-top-navigation allow-top-navigation-by-user-activation" onload="handleIframeLoad()" onerror="handleIframeError()"></iframe>' +
            '</div>' +
            '<div class="monarch-modal-footer">' +
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
            window.removeEventListener('message', handleBankMessage);
            $('#bank-connection-modal').remove();
            $('#monarch-connect-bank').prop('disabled', false).text('Connect Bank Account');
            $('#monarch-connect-spinner').hide();
        });

        // Close on overlay click
        $(document).on('click', '.monarch-modal-overlay', function() {
            window.removeEventListener('message', handleBankMessage);
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

        // Add global iframe error handlers
        window.handleIframeLoad = function() {
            console.log('Bank linking iframe loaded successfully');
            // Add visual feedback that iframe loaded
            $('#bank-linking-iframe').css('border', '2px solid green');

            // Check if iframe redirected to our site (indicates bank linking complete)
            // This handles the 404 error case where Yodlee redirects to our checkout URL
            try {
                const iframe = document.getElementById('bank-linking-iframe');
                if (iframe && iframe.contentWindow) {
                    // Try to access iframe location - will throw error if cross-origin
                    const iframeSrc = iframe.contentWindow.location.href;

                    // If we can access it and it's our domain, bank linking completed
                    if (iframeSrc && (iframeSrc.includes(window.location.hostname) || iframeSrc.includes('localhost'))) {
                        console.log('Iframe redirected to our domain - bank linking likely complete');
                        // Auto-trigger verification after a brief delay
                        setTimeout(function() {
                            if ($('#monarch-bank-connected-btn').length && !$('#monarch-bank-connected-btn').prop('disabled')) {
                                console.log('Auto-triggering bank verification after redirect');
                                $('#monarch-bank-connected-btn').text('Redirect Detected! Verifying...');
                                $('#monarch-bank-connected-btn').click();
                            }
                        }, 500);
                    }
                }
            } catch (e) {
                // Cross-origin error - iframe still on Yodlee domain, which is expected
                console.log('Iframe is on external domain (expected)');
            }
        };

        window.handleIframeError = function() {
            console.log('Bank linking iframe failed to load');
            showError('Failed to load bank connection. Please try the manual entry option or refresh the page.');
        };

        // Monitor iframe for navigation changes (backup for redirect detection)
        let iframeLoadCount = 0;
        const iframeMonitor = setInterval(function() {
            const iframe = document.getElementById('bank-linking-iframe');
            if (!iframe) {
                clearInterval(iframeMonitor);
                return;
            }

            // Count load events - multiple loads may indicate navigation
            iframe.onload = function() {
                iframeLoadCount++;
                console.log('Iframe load event #' + iframeLoadCount);

                // If iframe loads multiple times, user may have completed flow
                if (iframeLoadCount >= 2) {
                    console.log('Multiple iframe loads detected - may indicate completion');
                }

                // Call the main load handler
                window.handleIframeLoad();
            };
        }, 1000);

        // Add informational message for localhost development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            var localhostInfo = $('<div class="monarch-localhost-info" style="background:#d1ecf1; border:1px solid #bee5eb; padding:10px; margin:10px; border-radius:4px; font-size:12px;">' +
                '<strong>Development Mode:</strong> Complete the bank linking process in the iframe, then click "I\'ve Connected My Bank" to verify. ' +
                'If you experience issues, the <strong>Manual Entry</strong> option is also available.' +
                '</div>');
            $('#modal-auto-section .monarch-modal-body').prepend(localhostInfo);
        }
    }

    // Handle messages from Monarch iframe
    // Supports Yodlee FastLink 4 callbacks: onSuccess, onClose, onError, onEvent
    // See: https://developer.yodlee.com/docs/fastlink/4.0/advanced
    function handleBankMessage(event) {
        try {
            // Log all messages for debugging
            console.log('Received postMessage from:', event.origin, 'Data:', event.data);

            // Only accept messages from trusted Monarch/Yodlee domains
            const allowedOrigins = [
                'https://devapi.monarch.is',
                'https://api.monarch.is',
                'https://appsandbox.monarch.is',
                'https://app.monarch.is',
                'https://dag2.yodlee.com',
                'https://fl4.prod.yodlee.com',
                'https://node.yodlee.com',
                'https://fastlink.yodlee.com',
                'https://finapp.yodlee.com',
                'https://aggregation.yodlee.com'
            ];

            const isAllowed = allowedOrigins.some(origin =>
                event.origin.startsWith(origin) ||
                event.origin.includes('yodlee') ||
                event.origin.includes('monarch') ||
                event.origin.includes('envestnet')
            );

            if (!isAllowed) {
                console.log('Message from non-allowed origin, ignoring:', event.origin);
                return;
            }

            console.log('Allowed origin - processing message');

            // Skip focus-related messages
            if (event.data && event.data.action === 'focus') {
                return;
            }

            // Parse message data
            let messageData = event.data;
            if (typeof event.data === 'string') {
                try {
                    messageData = JSON.parse(event.data);
                } catch (e) {
                    // Not JSON, check for specific strings
                    if (event.data.includes('SUCCESS') || event.data.includes('COMPLETE')) {
                        console.log('Bank linking success detected from string message');
                        triggerBankVerification();
                        return;
                    }
                    return;
                }
            }

            if (!messageData || typeof messageData !== 'object') {
                return;
            }

            // =====================================================
            // Handle Yodlee FastLink 4 callback events
            // =====================================================

            // onSuccess callback - account successfully added
            // Contains: providerAccountId, requestId, status='SUCCESS', additionalStatus
            if (messageData.fnToCall === 'onSuccess' ||
                (messageData.status === 'SUCCESS' && messageData.providerAccountId)) {
                console.log('FastLink onSuccess callback received:', messageData);
                console.log('Provider Account ID:', messageData.providerAccountId);
                triggerBankVerification();
                return;
            }

            // onClose callback - user closed or completed flow
            // Contains: action, sites array, status
            if (messageData.fnToCall === 'onClose' || messageData.fnToCall === 'close') {
                console.log('FastLink onClose callback received:', messageData);
                // Check if there are successfully linked sites
                if (messageData.sites && messageData.sites.length > 0) {
                    const successSites = messageData.sites.filter(s => s.status === 'SUCCESS');
                    if (successSites.length > 0) {
                        console.log('Successfully linked sites found:', successSites);
                        triggerBankVerification();
                        return;
                    }
                }
                // User closed without completing - still check in case linking completed
                if (messageData.action !== 'exit' || messageData.status === 'SUCCESS') {
                    triggerBankVerification();
                }
                return;
            }

            // onError callback - error occurred
            if (messageData.fnToCall === 'onError') {
                console.log('FastLink onError callback received:', messageData);
                console.error('FastLink error:', messageData.code, messageData.message);
                // Don't automatically fail - let user retry or use manual
                return;
            }

            // onEvent callback - intermediate status updates
            if (messageData.fnToCall === 'onEvent') {
                console.log('FastLink onEvent callback received:', messageData);
                // Could show progress to user here
                return;
            }

            // =====================================================
            // Handle other message formats (legacy/Monarch-specific)
            // =====================================================

            // Yodlee accountStatus function call
            if (messageData.fnToCall === 'accountStatus') {
                console.log('accountStatus callback received');
                triggerBankVerification();
                return;
            }

            // POST_MESSAGE type from Yodlee
            if (messageData.type === 'POST_MESSAGE') {
                console.log('POST_MESSAGE received, checking for success indicators');
                if (messageData.providerAccountId || messageData.status === 'SUCCESS') {
                    triggerBankVerification();
                }
                return;
            }

            // Sites array present (Yodlee success indicator)
            if (messageData.sites && Array.isArray(messageData.sites)) {
                console.log('Sites array received:', messageData.sites);
                triggerBankVerification();
                return;
            }

            // Provider account linked
            if (messageData.providerAccountId && messageData.providerId) {
                console.log('Provider account linked:', messageData.providerAccountId);
                triggerBankVerification();
                return;
            }

            // Direct PayToken from Monarch
            const payTokenId = messageData.payTokenId || messageData.paytoken_id || messageData.paytokenId || messageData._id;
            if (payTokenId) {
                console.log('Received PayToken ID directly:', payTokenId);
                completeBankConnection(payTokenId);
                return;
            }

            // Exit action
            if (messageData.action === 'exit') {
                console.log('Exit action received, checking bank status...');
                triggerBankVerification();
                return;
            }

            // Handle our own callback page message (from redirect)
            if (messageData.type === 'MONARCH_BANK_CALLBACK' && messageData.status === 'SUCCESS') {
                console.log('Monarch bank callback received - bank linking complete');
                triggerBankVerification();
                return;
            }

            // Generic success indicators
            if (messageData.type === 'BANK_CONNECTION_SUCCESS' ||
                messageData.success === true ||
                messageData.action === 'bankConnected' ||
                messageData.status === 'SUCCESS') {
                console.log('Bank connection success indicator received');
                triggerBankVerification();
                return;
            }

            console.log('Unhandled message format:', messageData);

        } catch (error) {
            console.error('Error handling postMessage:', error);
        }
    }

    // Trigger bank verification with a small delay
    function triggerBankVerification() {
        $('#monarch-bank-connected-btn').text('Connection Successful! Verifying...').addClass('success-pulse');
        setTimeout(function() {
            if ($('#monarch-bank-connected-btn').length && !$('#monarch-bank-connected-btn').prop('disabled')) {
                $('#monarch-bank-connected-btn').click();
            }
        }, 1500);
    }

    // Check if bank connection was successful
    // This calls the /v1/getlatestpaytoken/[organizationID] endpoint
    // Per Monarch embedded bank linking documentation
    // Includes retry logic since paytoken may take a moment to be available after bank linking
    function checkBankConnectionStatus(orgId, retryCount) {
        retryCount = retryCount || 0;
        const maxRetries = 5; // Increased retries
        const retryDelay = 3000; // 3 seconds between retries (bank linking can take time)

        console.log('Checking bank connection status for org:', orgId, '(attempt', retryCount + 1, 'of', maxRetries + 1, ')');

        $('#monarch-bank-connected-btn').text('Verifying... ' + (retryCount > 0 ? '(Attempt ' + (retryCount + 1) + ')' : ''));

        $.ajax({
            url: monarch_ach_params.ajax_url,
            method: 'POST',
            data: {
                action: 'monarch_get_latest_paytoken',
                nonce: monarch_ach_params.nonce,
                org_id: orgId
            },
            dataType: 'json',
            success: function(response) {
                console.log('getLatestPayToken response:', response);

                if (response.success && response.data.paytoken_id) {
                    // Successfully retrieved paytoken - bank linking is complete
                    console.log('Bank linked successfully, paytoken:', response.data.paytoken_id);
                    completeBankConnection(response.data.paytoken_id);
                } else {
                    // PayToken not found - might need to retry
                    var errorMsg = response.data || 'PayToken not found';
                    console.log('PayToken not found:', errorMsg);
                    console.log('Full response object:', JSON.stringify(response, null, 2));

                    if (retryCount < maxRetries) {
                        // Retry after delay - paytoken may not be immediately available
                        console.log('Retrying in', retryDelay, 'ms...');
                        $('#monarch-bank-connected-btn').text('Verifying... Please wait (' + (maxRetries - retryCount) + ' attempts remaining)');
                        setTimeout(function() {
                            checkBankConnectionStatus(orgId, retryCount + 1);
                        }, retryDelay);
                    } else {
                        // Max retries reached - show detailed error
                        console.error('Max retries reached. Error details:', errorMsg);
                        var errorDetails = 'Bank connection not detected after ' + (maxRetries + 1) + ' attempts.\n\n';
                        errorDetails += 'Possible reasons:\n';
                        errorDetails += '1. Bank linking was not completed in the iframe\n';
                        errorDetails += '2. The PayToken is still being processed\n';
                        errorDetails += '3. There may be a credentials mismatch\n\n';
                        errorDetails += 'Technical details: ' + errorMsg + '\n\n';
                        errorDetails += 'Please try:\n';
                        errorDetails += '- Using the "Manual Entry" option instead\n';
                        errorDetails += '- Refreshing the page and trying again';
                        alert(errorDetails);
                        $('#monarch-bank-connected-btn').prop('disabled', false).text('I\'ve Connected My Bank');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error retrieving paytoken:', error);
                console.error('XHR response:', xhr.responseText);

                if (retryCount < maxRetries) {
                    // Retry on error
                    console.log('Request error, retrying in', retryDelay, 'ms...');
                    setTimeout(function() {
                        checkBankConnectionStatus(orgId, retryCount + 1);
                    }, retryDelay);
                } else {
                    alert('Failed to verify bank connection after multiple attempts.\n\nError: ' + error + '\n\nPlease try using the "Manual Entry" option instead.');
                    $('#monarch-bank-connected-btn').prop('disabled', false).text('I\'ve Connected My Bank');
                }
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
            error: function(jqXHR, textStatus, errorThrown) {
                showError('Connection error: ' + errorThrown);
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
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('Manual bank entry error:', textStatus, errorThrown, jqXHR.responseText);
                showModalError('Connection error: ' + errorThrown);
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
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('Disconnect error:', textStatus, errorThrown, jqXHR.responseText);
                showError('Connection error: ' + errorThrown);
                $link.text('Use a different bank account').css('pointer-events', 'auto');
            }
        });
    });

    // Auto-fill form with test data in development mode
    if (typeof monarch_ach_params !== 'undefined' && monarch_ach_params.test_mode === 'yes') {
        // Test mode is active - could add debug helpers here
    }
});
