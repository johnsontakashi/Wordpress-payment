/**
 * Monarch ACH Payment Method for WooCommerce Blocks Checkout
 */
(function() {
    'use strict';

    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    const { getSetting } = wc.wcSettings;
    const { createElement, useState, useEffect } = wp.element;
    const { decodeEntities } = wp.htmlEntities;

    // Get settings from PHP
    const settings = getSetting('monarch_ach_data', {});
    const defaultLabel = 'ACH Bank Transfer';
    const label = decodeEntities(settings.title) || defaultLabel;

    /**
     * Content component - displays the payment form
     */
    const Content = (props) => {
        const { eventRegistration, emitResponse } = props;
        const { onPaymentSetup } = eventRegistration;

        const [phone, setPhone] = useState('');
        const [dob, setDob] = useState('');
        const [connectionMethod, setConnectionMethod] = useState('auto');
        const [bankConnected, setBankConnected] = useState(false);
        const [orgId, setOrgId] = useState('');
        const [paytokenId, setPaytokenId] = useState('');
        const [isProcessing, setIsProcessing] = useState(false);
        const [errorMessage, setErrorMessage] = useState('');

        // Manual entry fields
        const [routingNumber, setRoutingNumber] = useState('');
        const [accountNumber, setAccountNumber] = useState('');
        const [accountType, setAccountType] = useState('checking');

        // Check if already connected
        useEffect(() => {
            if (settings.has_saved_bank) {
                setBankConnected(true);
                setOrgId(settings.saved_org_id || '');
                setPaytokenId(settings.saved_paytoken_id || '');
            }
        }, []);

        // Handle payment setup
        useEffect(() => {
            const unsubscribe = onPaymentSetup(() => {
                if (bankConnected && orgId && paytokenId) {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                monarch_org_id: orgId,
                                monarch_paytoken_id: paytokenId,
                                monarch_phone: phone,
                                monarch_dob: dob
                            }
                        }
                    };
                }

                // For manual entry
                if (connectionMethod === 'manual' && routingNumber && accountNumber) {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                monarch_phone: phone,
                                monarch_dob: dob,
                                monarch_connection_method: 'manual',
                                monarch_routing_number: routingNumber,
                                monarch_account_number: accountNumber,
                                monarch_account_type: accountType
                            }
                        }
                    };
                }

                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: 'Please connect your bank account or enter bank details manually.'
                };
            });

            return unsubscribe;
        }, [onPaymentSetup, bankConnected, orgId, paytokenId, phone, dob, connectionMethod, routingNumber, accountNumber, accountType]);

        // Create organization and open bank linking
        const handleConnectBank = () => {
            if (!phone || !dob) {
                setErrorMessage('Please enter phone number and date of birth.');
                return;
            }

            setIsProcessing(true);
            setErrorMessage('');

            // Get billing data from checkout
            const billingData = wp.data.select('wc/store/cart').getCustomerData().billingAddress;

            jQuery.ajax({
                url: settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'monarch_create_organization',
                    nonce: settings.nonce,
                    first_name: billingData.first_name,
                    last_name: billingData.last_name,
                    email: billingData.email,
                    phone: phone,
                    dob: dob,
                    address: billingData.address_1,
                    city: billingData.city,
                    state: billingData.state,
                    zip: billingData.postcode
                },
                success: function(response) {
                    setIsProcessing(false);
                    if (response.success && response.data.bank_link_url) {
                        setOrgId(response.data.org_id);
                        openBankLinkingModal(response.data.bank_link_url, response.data.org_id, response.data.purchaser_api_key, response.data.purchaser_app_id);
                    } else {
                        setErrorMessage(response.data?.message || 'Failed to create organization.');
                    }
                },
                error: function() {
                    setIsProcessing(false);
                    setErrorMessage('Connection error. Please try again.');
                }
            });
        };

        // Open bank linking modal
        const openBankLinkingModal = (url, organizationId, purchaserApiKey, purchaserAppId) => {
            // Create modal
            const modal = document.createElement('div');
            modal.id = 'monarch-bank-modal-blocks';
            modal.innerHTML = `
                <div style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:999999; display:flex; align-items:center; justify-content:center;">
                    <div style="background:white; width:90%; max-width:500px; max-height:90vh; border-radius:8px; overflow:hidden; position:relative;">
                        <div style="padding:15px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
                            <strong>Connect Your Bank</strong>
                            <button id="monarch-close-modal" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
                        </div>
                        <div style="height:500px;">
                            <iframe src="${url}" style="width:100%; height:100%; border:none;"></iframe>
                        </div>
                        <div style="padding:15px; border-top:1px solid #ddd; text-align:center;">
                            <button id="monarch-verify-connection" style="background:#0073aa; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer;">I've Connected My Bank</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Close button
            document.getElementById('monarch-close-modal').addEventListener('click', () => {
                modal.remove();
            });

            // Verify connection button
            document.getElementById('monarch-verify-connection').addEventListener('click', () => {
                verifyBankConnection(organizationId, purchaserApiKey, purchaserAppId, modal);
            });

            // Listen for postMessage from iframe
            window.addEventListener('message', function messageHandler(event) {
                try {
                    const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                    if (data.fnToCall === 'onSuccess' || (data.status === 'SUCCESS' && data.providerAccountId)) {
                        verifyBankConnection(organizationId, purchaserApiKey, purchaserAppId, modal);
                        window.removeEventListener('message', messageHandler);
                    }
                } catch (e) {}
            });
        };

        // Verify bank connection
        const verifyBankConnection = (organizationId, purchaserApiKey, purchaserAppId, modal) => {
            jQuery.ajax({
                url: settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'monarch_get_latest_paytoken',
                    nonce: settings.nonce,
                    org_id: organizationId,
                    purchaser_api_key: purchaserApiKey,
                    purchaser_app_id: purchaserAppId
                },
                success: function(response) {
                    if (response.success && response.data.paytoken_id) {
                        setPaytokenId(response.data.paytoken_id);
                        setBankConnected(true);
                        modal.remove();
                    } else {
                        alert('Bank connection not verified yet. Please complete the process in the iframe or try again.');
                    }
                },
                error: function() {
                    alert('Error verifying connection. Please try again.');
                }
            });
        };

        // Handle manual entry submission
        const handleManualEntry = () => {
            if (!phone || !dob) {
                setErrorMessage('Please enter phone number and date of birth.');
                return;
            }
            if (!routingNumber || !accountNumber) {
                setErrorMessage('Please enter routing and account numbers.');
                return;
            }

            setIsProcessing(true);
            setErrorMessage('');

            const billingData = wp.data.select('wc/store/cart').getCustomerData().billingAddress;

            jQuery.ajax({
                url: settings.ajax_url,
                type: 'POST',
                data: {
                    action: 'monarch_manual_bank_entry',
                    nonce: settings.nonce,
                    first_name: billingData.first_name,
                    last_name: billingData.last_name,
                    email: billingData.email,
                    phone: phone,
                    dob: dob,
                    address: billingData.address_1,
                    city: billingData.city,
                    state: billingData.state,
                    zip: billingData.postcode,
                    routing_number: routingNumber,
                    account_number: accountNumber,
                    account_type: accountType
                },
                success: function(response) {
                    setIsProcessing(false);
                    if (response.success) {
                        setOrgId(response.data.org_id);
                        setPaytokenId(response.data.paytoken_id);
                        setBankConnected(true);
                    } else {
                        setErrorMessage(response.data?.message || 'Failed to add bank account.');
                    }
                },
                error: function() {
                    setIsProcessing(false);
                    setErrorMessage('Connection error. Please try again.');
                }
            });
        };

        // Disconnect bank
        const handleDisconnect = () => {
            setBankConnected(false);
            setOrgId('');
            setPaytokenId('');
        };

        // Render connected state
        if (bankConnected) {
            return createElement('div', { className: 'monarch-ach-block-form' },
                createElement('div', { style: { padding: '15px', background: '#d4edda', borderRadius: '4px', marginBottom: '10px' } },
                    createElement('strong', null, '✓ Bank account connected'),
                    createElement('br'),
                    createElement('a', {
                        href: '#',
                        onClick: (e) => { e.preventDefault(); handleDisconnect(); },
                        style: { fontSize: '12px' }
                    }, 'Use a different bank account')
                )
            );
        }

        // Render form
        return createElement('div', { className: 'monarch-ach-block-form' },
            // Error message
            errorMessage && createElement('div', {
                style: { padding: '10px', background: '#f8d7da', color: '#721c24', borderRadius: '4px', marginBottom: '10px' }
            }, errorMessage),

            // Description
            settings.description && createElement('p', null, decodeEntities(settings.description)),

            // Phone field
            createElement('p', { className: 'form-row' },
                createElement('label', { htmlFor: 'monarch_phone_block' }, 'Phone Number ', createElement('span', { className: 'required' }, '*')),
                createElement('input', {
                    type: 'tel',
                    id: 'monarch_phone_block',
                    value: phone,
                    onChange: (e) => setPhone(e.target.value),
                    style: { width: '100%', padding: '8px' }
                })
            ),

            // DOB field
            createElement('p', { className: 'form-row' },
                createElement('label', { htmlFor: 'monarch_dob_block' }, 'Date of Birth ', createElement('span', { className: 'required' }, '*')),
                createElement('input', {
                    type: 'date',
                    id: 'monarch_dob_block',
                    value: dob,
                    onChange: (e) => setDob(e.target.value),
                    style: { width: '100%', padding: '8px' }
                })
            ),

            // Connection method tabs
            createElement('div', { style: { marginTop: '15px' } },
                createElement('div', { style: { display: 'flex', gap: '10px', marginBottom: '15px' } },
                    createElement('button', {
                        type: 'button',
                        onClick: () => setConnectionMethod('auto'),
                        style: {
                            flex: 1,
                            padding: '10px',
                            background: connectionMethod === 'auto' ? '#0073aa' : '#f0f0f0',
                            color: connectionMethod === 'auto' ? 'white' : 'black',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: 'pointer'
                        }
                    }, 'Automatic'),
                    createElement('button', {
                        type: 'button',
                        onClick: () => setConnectionMethod('manual'),
                        style: {
                            flex: 1,
                            padding: '10px',
                            background: connectionMethod === 'manual' ? '#0073aa' : '#f0f0f0',
                            color: connectionMethod === 'manual' ? 'white' : 'black',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: 'pointer'
                        }
                    }, 'Manual Entry')
                ),

                // Automatic method
                connectionMethod === 'auto' && createElement('div', null,
                    createElement('p', { style: { fontSize: '13px', color: '#666' } },
                        'Securely connect your bank account using your online banking credentials.'),
                    createElement('button', {
                        type: 'button',
                        onClick: handleConnectBank,
                        disabled: isProcessing,
                        style: {
                            width: '100%',
                            padding: '12px',
                            background: '#0073aa',
                            color: 'white',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: isProcessing ? 'not-allowed' : 'pointer'
                        }
                    }, isProcessing ? 'Processing...' : 'Connect Bank Account')
                ),

                // Manual method
                connectionMethod === 'manual' && createElement('div', null,
                    createElement('p', { className: 'form-row' },
                        createElement('label', { htmlFor: 'monarch_routing_block' }, 'Routing Number ', createElement('span', { className: 'required' }, '*')),
                        createElement('input', {
                            type: 'text',
                            id: 'monarch_routing_block',
                            value: routingNumber,
                            onChange: (e) => setRoutingNumber(e.target.value),
                            maxLength: 9,
                            style: { width: '100%', padding: '8px' }
                        })
                    ),
                    createElement('p', { className: 'form-row' },
                        createElement('label', { htmlFor: 'monarch_account_block' }, 'Account Number ', createElement('span', { className: 'required' }, '*')),
                        createElement('input', {
                            type: 'text',
                            id: 'monarch_account_block',
                            value: accountNumber,
                            onChange: (e) => setAccountNumber(e.target.value),
                            style: { width: '100%', padding: '8px' }
                        })
                    ),
                    createElement('p', { className: 'form-row' },
                        createElement('label', { htmlFor: 'monarch_account_type_block' }, 'Account Type'),
                        createElement('select', {
                            id: 'monarch_account_type_block',
                            value: accountType,
                            onChange: (e) => setAccountType(e.target.value),
                            style: { width: '100%', padding: '8px' }
                        },
                            createElement('option', { value: 'checking' }, 'Checking'),
                            createElement('option', { value: 'savings' }, 'Savings')
                        )
                    ),
                    createElement('button', {
                        type: 'button',
                        onClick: handleManualEntry,
                        disabled: isProcessing,
                        style: {
                            width: '100%',
                            padding: '12px',
                            background: '#0073aa',
                            color: 'white',
                            border: 'none',
                            borderRadius: '4px',
                            cursor: isProcessing ? 'not-allowed' : 'pointer',
                            marginTop: '10px'
                        }
                    }, isProcessing ? 'Processing...' : 'Verify Bank Account')
                )
            )
        );
    };

    /**
     * Label component
     */
    const Label = (props) => {
        const { PaymentMethodLabel } = props.components;
        return createElement(PaymentMethodLabel, { text: label });
    };

    /**
     * Register the payment method
     */
    registerPaymentMethod({
        name: 'monarch_ach',
        label: createElement(Label, null),
        content: createElement(Content, null),
        edit: createElement(Content, null),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports || ['products']
        }
    });
})();
