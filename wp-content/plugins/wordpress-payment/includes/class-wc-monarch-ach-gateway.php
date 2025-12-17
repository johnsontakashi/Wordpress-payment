<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Monarch_ACH_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'monarch_ach';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = 'Monarch ACH';
        $this->method_description = 'Secure ACH bank transfers via Monarch payment gateway';
        $this->supports = array('products');
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        
        $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');
        $this->app_id = $this->testmode ? $this->get_option('test_app_id') : $this->get_option('live_app_id');
        $this->merchant_org_id = $this->testmode ? $this->get_option('test_merchant_org_id') : $this->get_option('live_merchant_org_id');
        $this->partner_name = $this->get_option('partner_name');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Show transaction details to customers on order view page (My Account)
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_transaction_details_for_customer'), 10, 1);
        
        // AJAX hooks
        add_action('wp_ajax_monarch_create_customer', array($this, 'ajax_create_customer'));
        add_action('wp_ajax_nopriv_monarch_create_customer', array($this, 'ajax_create_customer'));
        add_action('wp_ajax_monarch_add_bank_account', array($this, 'ajax_add_bank_account'));
        add_action('wp_ajax_nopriv_monarch_add_bank_account', array($this, 'ajax_add_bank_account'));
        add_action('wp_ajax_monarch_create_organization', array($this, 'ajax_create_organization'));
        add_action('wp_ajax_nopriv_monarch_create_organization', array($this, 'ajax_create_organization'));
        add_action('wp_ajax_monarch_bank_connection_complete', array($this, 'ajax_bank_connection_complete'));
        add_action('wp_ajax_nopriv_monarch_bank_connection_complete', array($this, 'ajax_bank_connection_complete'));
        add_action('wp_ajax_monarch_check_bank_status', array($this, 'ajax_check_bank_status'));
        add_action('wp_ajax_nopriv_monarch_check_bank_status', array($this, 'ajax_check_bank_status'));
        add_action('wp_ajax_monarch_get_latest_paytoken', array($this, 'ajax_get_latest_paytoken'));
        add_action('wp_ajax_nopriv_monarch_get_latest_paytoken', array($this, 'ajax_get_latest_paytoken'));
        add_action('wp_ajax_monarch_disconnect_bank', array($this, 'ajax_disconnect_bank'));
        add_action('wp_ajax_monarch_manual_bank_entry', array($this, 'ajax_manual_bank_entry'));
        add_action('wp_ajax_nopriv_monarch_manual_bank_entry', array($this, 'ajax_manual_bank_entry'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable Monarch ACH Payment',
                'default' => 'no'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title displayed during checkout.',
                'default' => 'ACH Bank Transfer',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description displayed during checkout.',
                'default' => 'Pay securely using your bank account via ACH transfer.',
            ),
            'testmode' => array(
                'title' => 'Test mode',
                'label' => 'Enable Test Mode',
                'type' => 'checkbox',
                'description' => 'Place the payment gateway in test mode using sandbox API credentials.',
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'api_credentials_heading' => array(
                'title' => 'API Credentials',
                'type' => 'title',
                'description' => 'Enter your Monarch API credentials below. You can get these from your Monarch dashboard.',
            ),
            'partner_name' => array(
                'title' => 'Partner Name',
                'type' => 'text',
                'description' => 'Your partner name as registered with Monarch (e.g., "yourcompany").',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter your partner name',
            ),
            'test_credentials_heading' => array(
                'title' => 'Sandbox/Test Credentials',
                'type' => 'title',
                'description' => 'These credentials are used when Test Mode is enabled.',
            ),
            'test_api_key' => array(
                'title' => 'Sandbox API Key',
                'type' => 'password',
                'description' => 'Your sandbox API key from Monarch (e.g., "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx").',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter sandbox API key',
            ),
            'test_app_id' => array(
                'title' => 'Sandbox App ID',
                'type' => 'text',
                'description' => 'Your sandbox App ID from Monarch (e.g., "a1b2c3d4").',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter sandbox App ID',
            ),
            'test_merchant_org_id' => array(
                'title' => 'Sandbox Merchant Org ID',
                'type' => 'text',
                'description' => 'Your sandbox merchant organization ID from Monarch (e.g., "1234567890").',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter sandbox Merchant Org ID',
            ),
            'live_credentials_heading' => array(
                'title' => 'Production/Live Credentials',
                'type' => 'title',
                'description' => 'These credentials are used when Test Mode is disabled.',
            ),
            'live_api_key' => array(
                'title' => 'Live API Key',
                'type' => 'password',
                'description' => 'Your production API key from Monarch.',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter live API key',
            ),
            'live_app_id' => array(
                'title' => 'Live App ID',
                'type' => 'text',
                'description' => 'Your production App ID from Monarch.',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter live App ID',
            ),
            'live_merchant_org_id' => array(
                'title' => 'Live Merchant Org ID',
                'type' => 'text',
                'description' => 'Your production merchant organization ID from Monarch.',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter live Merchant Org ID',
            ),
        );
    }
    
    public function payment_scripts() {
        if (!is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }
        
        if ('no' === $this->enabled) {
            return;
        }
        
        if (empty($this->api_key) || empty($this->app_id)) {
            return;
        }
        
        wp_enqueue_script(
            'wc-monarch-ach',
            WC_MONARCH_ACH_PLUGIN_URL . 'assets/js/monarch-ach.js',
            array('jquery', 'wc-checkout'),
            WC_MONARCH_ACH_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wc-monarch-ach',
            WC_MONARCH_ACH_PLUGIN_URL . 'assets/css/monarch-ach.css',
            array(),
            WC_MONARCH_ACH_VERSION
        );
        
        wp_localize_script('wc-monarch-ach', 'monarch_ach_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('monarch_ach_nonce'),
            'test_mode' => $this->testmode ? 'yes' : 'no'
        ));
    }
    
    public function is_available() {
        // Check if gateway is enabled
        if ('yes' !== $this->enabled) {
            return false;
        }
        
        // For Store API, don't block on missing credentials
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return parent::is_available();
        }
        
        // Check if required API credentials are configured
        if (empty($this->api_key) || empty($this->app_id)) {
            return false;
        }
        
        // Check if merchant org ID is configured
        if (empty($this->merchant_org_id)) {
            return false;
        }
        
        // Check if partner name is configured
        if (empty($this->partner_name)) {
            return false;
        }
        
        return parent::is_available();
    }
    
    public function payment_fields() {
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }
        
        // Check if customer is already registered with Monarch
        $customer_id = get_current_user_id();
        $monarch_org_id = get_user_meta($customer_id, '_monarch_org_id', true);
        $paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);
        
        if ($monarch_org_id && $paytoken_id) {
            echo '<div class="monarch-bank-connected">';
            echo '<p><strong>✓ Bank account connected</strong></p>';
            echo '<p><a href="#" id="monarch-disconnect-bank" class="monarch-disconnect-link">Use a different bank account</a></p>';
            echo '<input type="hidden" name="monarch_org_id" value="' . esc_attr($monarch_org_id) . '">';
            echo '<input type="hidden" name="monarch_paytoken_id" value="' . esc_attr($paytoken_id) . '">';
            echo '</div>';
            return;
        }
        
        ?>
        <div id="monarch-ach-form">
            <div id="monarch-ach-errors" class="woocommerce-error" style="display:none;"></div>

            <!-- Phone Number Field with Edit Mode -->
            <div class="form-row form-row-wide monarch-editable-field" id="monarch-phone-wrapper">
                <label for="monarch_phone">Phone Number <span class="required">*</span></label>
                <div class="monarch-field-input">
                    <input id="monarch_phone" name="monarch_phone" type="tel" required>
                </div>
                <div class="monarch-field-display" style="display:none;">
                    <span class="monarch-field-value" id="monarch_phone_display"></span>
                    <button type="button" class="monarch-edit-btn" data-field="phone">Edit</button>
                </div>
            </div>

            <!-- Date of Birth Field with Edit Mode -->
            <div class="form-row form-row-wide monarch-editable-field" id="monarch-dob-wrapper">
                <label for="monarch_dob">Date of Birth <span class="required">*</span></label>
                <div class="monarch-field-input">
                    <input id="monarch_dob" name="monarch_dob" type="date" required>
                </div>
                <div class="monarch-field-display" style="display:none;">
                    <span class="monarch-field-value" id="monarch_dob_display"></span>
                    <button type="button" class="monarch-edit-btn" data-field="dob">Edit</button>
                </div>
            </div>

            <p class="form-row form-row-wide">
                <button type="button" id="monarch-connect-bank" class="button alt">Connect Bank Account</button>
                <span id="monarch-connect-spinner" class="spinner" style="display:none; float:none; margin-left:10px;"></span>
            </p>

            <p class="monarch-security-notice">
                Your bank details are securely transmitted and encrypted.
            </p>

            <input type="hidden" id="monarch_org_id" name="monarch_org_id" value="">
            <input type="hidden" id="monarch_paytoken_id" name="monarch_paytoken_id" value="">
            <input type="hidden" id="monarch_bank_verified" name="monarch_bank_verified" value="">
            <input type="hidden" id="monarch_entry_method" name="monarch_entry_method" value="auto">
            <input type="hidden" id="monarch_info_confirmed" name="monarch_info_confirmed" value="">
        </div>
        <?php
    }
    
    public function validate_fields() {
        $customer_id = get_current_user_id();
        $monarch_org_id = get_user_meta($customer_id, '_monarch_org_id', true);
        $paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);

        // Bank account must be connected through Monarch's verification flow
        if (!$monarch_org_id || !$paytoken_id) {
            wc_add_notice('Please connect your bank account before placing an order.', 'error');
            return false;
        }

        return true;
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $customer_id = $order->get_user_id();

        try {
            // Get verified org_id and paytoken_id from user meta
            $org_id = get_user_meta($customer_id, '_monarch_org_id', true);
            $paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);

            // Bank account must be connected through Monarch's verification flow
            if (!$org_id || !$paytoken_id) {
                throw new Exception('Please connect your bank account before placing an order.');
            }

            // Get the purchaser org's API credentials (required for sale transactions)
            $org_api_key = get_user_meta($customer_id, '_monarch_org_api_key', true);
            $org_app_id = get_user_meta($customer_id, '_monarch_org_app_id', true);

            // Use purchaser org's credentials if available, otherwise fall back to merchant credentials
            $api_key_for_sale = $org_api_key ?: $this->api_key;
            $app_id_for_sale = $org_app_id ?: $this->app_id;

            $monarch_api = new Monarch_API(
                $api_key_for_sale,
                $app_id_for_sale,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );

            $order->add_order_note('Using verified bank account - orgId: ' . $org_id . ', payTokenId: ' . $paytoken_id);
            if ($org_api_key) {
                $order->add_order_note('Using purchaser org credentials for transaction');
            }

            // Ensure PayToken is assigned to the organization before transaction
            // This is a safety check for existing users whose PayToken may not have been assigned
            $logger = WC_Monarch_Logger::instance();
            $assign_result = $monarch_api->assign_paytoken($paytoken_id, $org_id);
            if ($assign_result['success']) {
                $logger->debug('PayToken re-assigned before transaction', array(
                    'org_id' => $org_id,
                    'paytoken_id' => $paytoken_id
                ));
            } else {
                // Log but continue - PayToken is likely already assigned
                $logger->debug('PayToken assignment check before transaction', array(
                    'org_id' => $org_id,
                    'paytoken_id' => $paytoken_id,
                    'result' => $assign_result['error'] ?? 'Already assigned'
                ));
            }

            // Create Sale Transaction
            $transaction_result = $monarch_api->create_sale_transaction(array(
                'amount' => $order->get_total(),
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id,
                'comment' => 'Order #' . $order_id . ' - ' . get_bloginfo('name')
            ));

            if (!$transaction_result['success']) {
                throw new Exception('Transaction failed: ' . $transaction_result['error']);
            }

            $transaction_id = $transaction_result['data']['id'] ?? $transaction_result['data']['_id'] ?? '';
            $order->add_order_note('Transaction created - ID: ' . ($transaction_id ?: 'N/A'));

            // Save transaction ID to order meta (visible in admin) - HPOS compatible
            if ($transaction_id) {
                $order->set_transaction_id($transaction_id);
                $order->update_meta_data('_monarch_transaction_id', $transaction_id);
                $order->update_meta_data('_monarch_org_id', $org_id);
                $order->update_meta_data('_monarch_paytoken_id', $paytoken_id);
                $order->save();
            }

            // Save transaction data
            $this->save_transaction_data($order_id, $transaction_result['data'], $org_id, $paytoken_id);

            $order->payment_complete($transaction_id);
            $order->add_order_note('ACH payment processed. Transaction ID: ' . ($transaction_id ?: 'N/A'));

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );

        } catch (Exception $e) {
            wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
            return array('result' => 'fail', 'redirect' => '');
        }
    }
    
    private function setup_customer_and_bank_account($order, $monarch_api) {
        $customer_data = $this->prepare_customer_data($order);
        
        // Create organization
        $org_result = $monarch_api->create_organization($customer_data);
        if (!$org_result['success']) {
            return array('success' => false, 'error' => $org_result['error']);
        }
        
        $user_id = $org_result['data']['_id'];
        $org_id = $org_result['data']['orgId'];
        
        // Create PayToken
        $bank_data = $this->prepare_bank_data();
        $paytoken_result = $monarch_api->create_paytoken($user_id, $bank_data);
        if (!$paytoken_result['success']) {
            return array('success' => false, 'error' => $paytoken_result['error']);
        }
        
        $paytoken_id = $paytoken_result['data']['_id'];
        
        // Assign PayToken
        $assign_result = $monarch_api->assign_paytoken($paytoken_id, $org_id);
        if (!$assign_result['success']) {
            return array('success' => false, 'error' => $assign_result['error']);
        }
        
        // Save to user meta
        $customer_id = $order->get_user_id();
        if ($customer_id) {
            update_user_meta($customer_id, '_monarch_org_id', $org_id);
            update_user_meta($customer_id, '_monarch_user_id', $user_id);
            update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);
            update_user_meta($customer_id, '_monarch_connected_date', current_time('mysql'));

            // Log customer creation
            $logger = WC_Monarch_Logger::instance();
            $logger->log_customer_event('customer_created', $customer_id, array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id
            ));
        }
        
        return array(
            'success' => true,
            'org_id' => $org_id,
            'paytoken_id' => $paytoken_id
        );
    }
    
    private function prepare_customer_data($order) {
        return array(
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'password' => wp_generate_password(),
            'phone' => sanitize_text_field($_POST['monarch_phone']),
            'company_name' => sanitize_text_field($_POST['monarch_company']) ?: $order->get_billing_company(),
            'dob' => sanitize_text_field($_POST['monarch_dob']),
            'address_1' => $order->get_billing_address_1(),
            'address_2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'zip' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country()
        );
    }
    
    private function prepare_bank_data() {
        return array(
            'bank_name' => sanitize_text_field($_POST['monarch_bank_name']),
            'account_number' => sanitize_text_field($_POST['monarch_account_number']),
            'routing_number' => sanitize_text_field($_POST['monarch_routing_number']),
            'account_type' => sanitize_text_field($_POST['monarch_account_type'])
        );
    }
    
    private function save_transaction_data($order_id, $transaction_data, $org_id, $paytoken_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'monarch_ach_transactions';
        
        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'transaction_id' => $transaction_data['id'] ?? uniqid(),
                'monarch_org_id' => $org_id,
                'paytoken_id' => $paytoken_id,
                'amount' => $transaction_data['amount'] ?? 0,
                'currency' => 'USD',
                'status' => $transaction_data['status'] ?? 'pending',
                'api_response' => json_encode($transaction_data)
            ),
            array('%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
    }
    
    public function thankyou_page() {
        echo '<p>Thank you for your payment. Your ACH transaction is being processed and you will receive confirmation once complete.</p>';
    }

    /**
     * Display transaction details to customers on order view page (My Account → Orders → View)
     */
    public function display_transaction_details_for_customer($order) {
        // Only show for Monarch ACH payments
        if ($order->get_payment_method() !== 'monarch_ach') {
            return;
        }

        // Get transaction data from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'monarch_ach_transactions';
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d LIMIT 1",
            $order->get_id()
        ));

        if (!$transaction) {
            return;
        }

        // Map status to user-friendly text
        $status_labels = array(
            'pending' => 'Processing',
            'processing' => 'Processing',
            'submitted' => 'Submitted',
            'completed' => 'Completed',
            'success' => 'Completed',
            'settled' => 'Completed',
            'approved' => 'Approved',
            'failed' => 'Failed',
            'declined' => 'Declined',
            'rejected' => 'Rejected',
            'returned' => 'Returned',
            'refunded' => 'Refunded',
            'voided' => 'Cancelled',
            'cancelled' => 'Cancelled'
        );

        $status_text = $status_labels[strtolower($transaction->status)] ?? ucfirst($transaction->status);

        // Status colors
        $status_colors = array(
            'pending' => '#0366d6',
            'processing' => '#0366d6',
            'submitted' => '#0366d6',
            'completed' => '#22863a',
            'success' => '#22863a',
            'settled' => '#22863a',
            'approved' => '#22863a',
            'failed' => '#cb2431',
            'declined' => '#cb2431',
            'rejected' => '#cb2431',
            'returned' => '#cb2431',
            'refunded' => '#6f42c1',
            'voided' => '#6a737d',
            'cancelled' => '#6a737d'
        );

        $status_color = $status_colors[strtolower($transaction->status)] ?? '#6a737d';

        ?>
        <section class="woocommerce-monarch-transaction-details">
            <h2>ACH Payment Details</h2>
            <table class="woocommerce-table shop_table monarch-transaction-table">
                <tbody>
                    <tr>
                        <th>Payment Method</th>
                        <td>ACH Bank Transfer</td>
                    </tr>
                    <tr>
                        <th>Transaction ID</th>
                        <td><code style="font-size: 12px;"><?php echo esc_html($transaction->transaction_id); ?></code></td>
                    </tr>
                    <tr>
                        <th>Payment Status</th>
                        <td>
                            <span style="background: <?php echo esc_attr($status_color); ?>15; color: <?php echo esc_attr($status_color); ?>; padding: 4px 10px; border-radius: 4px; font-weight: 500; font-size: 13px;">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td><?php echo wc_price($transaction->amount); ?></td>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at))); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if (in_array(strtolower($transaction->status), array('pending', 'processing', 'submitted'))): ?>
            <p class="monarch-processing-notice" style="margin-top: 15px; padding: 12px; background: #f0f6fc; border-left: 4px solid #0366d6; font-size: 14px;">
                <strong>Note:</strong> ACH bank transfers typically take 2-5 business days to complete.
                You will receive an email notification once your payment has been processed.
            </p>
            <?php endif; ?>
        </section>
        <?php
    }
    
    /**
     * AJAX handler for creating organization and getting bank linking URL
     */
    public function ajax_create_organization() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
        }

        // Log credentials being used for debugging
        $logger = WC_Monarch_Logger::instance();
        $logger->debug('ajax_create_organization called', array(
            'api_key_last_4' => substr($this->api_key, -4),
            'app_id_last_4' => substr($this->app_id, -4),
            'merchant_org_id' => $this->merchant_org_id,
            'parent_org_id' => $this->merchant_org_id,
            'testmode' => $this->testmode ? 'yes' : 'no',
            'base_url' => $this->testmode ? 'https://devapi.monarch.is/v1' : 'https://api.monarch.is/v1'
        ));

        try {
            $monarch_api = new Monarch_API(
                $this->api_key,
                $this->app_id,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );
            
            // Get current user data
            $current_user = wp_get_current_user();
            
            // Prepare customer data
            $phone = preg_replace('/[^0-9]/', '', sanitize_text_field($_POST['monarch_phone']));
            $phone = substr($phone, -10); // Last 10 digits

            $dob_raw = sanitize_text_field($_POST['monarch_dob']);
            $dob = date('m/d/Y', strtotime($dob_raw)); // Convert to mm/dd/yyyy

            // Generate unique email for Monarch to avoid "email already in use" errors
            // Use WordPress user ID + timestamp to ensure uniqueness
            $user_email = $current_user->user_email;
            $email_parts = explode('@', $user_email);
            $unique_email = $email_parts[0] . '+wp' . $current_user->ID . '_' . time() . '@' . ($email_parts[1] ?? 'example.com');

            $customer_data = array(
                'first_name' => $current_user->user_firstname ?: sanitize_text_field($_POST['billing_first_name']),
                'last_name' => $current_user->user_lastname ?: sanitize_text_field($_POST['billing_last_name']),
                'email' => $unique_email,
                'password' => wp_generate_password(12, false),
                'phone' => $phone,
                'company_name' => sanitize_text_field($_POST['monarch_company']),
                'dob' => $dob,
                'address_1' => sanitize_text_field($_POST['billing_address_1']),
                'address_2' => sanitize_text_field($_POST['billing_address_2']),
                'city' => sanitize_text_field($_POST['billing_city']),
                'state' => sanitize_text_field($_POST['billing_state']),
                'zip' => sanitize_text_field($_POST['billing_postcode']),
                'country' => sanitize_text_field($_POST['billing_country'])
            );

            // Create organization
            $org_result = $monarch_api->create_organization($customer_data);

            if (!$org_result['success']) {
                wp_send_json_error($org_result['error']);
            }

            // Log full response structure for debugging credential extraction
            $logger->debug('Create Organization full response', array(
                'response_keys' => array_keys($org_result['data'] ?? []),
                'has_api_key' => isset($org_result['data']['api']),
                'api_structure' => isset($org_result['data']['api']) ? array_keys($org_result['data']['api']) : 'not present',
                'full_response' => $org_result['data']
            ));

            $user_id = $org_result['data']['_id'];
            $org_id = $org_result['data']['orgId'];
            $bank_linking_url = $org_result['data']['partner_embedded_url'] ?? $org_result['data']['bankLinkingUrl'] ?? $org_result['data']['connectionUrl'] ?? '';
            
            // Clean up the URL format before sending to frontend
            if (!empty($bank_linking_url)) {
                // Remove any double-encoding or malformed URL structure
                $bank_linking_url = urldecode($bank_linking_url);
                
                // Fix double-encoded URLs (common issue with callback URLs)
                if (strpos($bank_linking_url, 'http%3A') !== false) {
                    // This indicates a double-encoded URL
                    $bank_linking_url = urldecode($bank_linking_url);
                }
                
                // Ensure proper URL format - remove invalid hash fragments
                if (strpos($bank_linking_url, '#') !== false && substr_count($bank_linking_url, '#') > 1) {
                    $parts = explode('#', $bank_linking_url);
                    $base_url = $parts[0];
                    $hash_parts = array_slice($parts, 1);
                    
                    // Combine hash parts with & instead of multiple #
                    $clean_hash = implode('&', $hash_parts);
                    $bank_linking_url = $base_url . '#' . $clean_hash;
                }
                
                // Validate the final URL
                if (!filter_var($bank_linking_url, FILTER_VALIDATE_URL)) {
                    error_log('Monarch ACH: Invalid bank linking URL after cleanup: ' . $bank_linking_url);
                }
            }
            
            // Save organization data temporarily (will be permanent after bank connection)
            $customer_id = get_current_user_id();
            update_user_meta($customer_id, '_monarch_temp_org_id', $org_id);
            update_user_meta($customer_id, '_monarch_temp_user_id', $user_id);

            // Store the purchaser org's API credentials for transactions
            // The Monarch API returns credentials in response.data.api.sandbox or response.data.api.prod
            $org_api = $org_result['data']['api'] ?? null;
            $purchaser_api_key = null;
            $purchaser_app_id = null;

            if ($org_api) {
                $credentials_key = $this->testmode ? 'sandbox' : 'prod';
                $org_credentials = $org_api[$credentials_key] ?? null;
                if ($org_credentials) {
                    $purchaser_api_key = $org_credentials['api_key'] ?? $org_credentials['apiKey'] ?? null;
                    $purchaser_app_id = $org_credentials['app_id'] ?? $org_credentials['appId'] ?? null;
                }
            }

            // If credentials not found in expected location, try alternative paths
            if (!$purchaser_api_key) {
                $purchaser_api_key = $org_result['data']['apiKey'] ?? $org_result['data']['api_key'] ?? null;
            }
            if (!$purchaser_app_id) {
                $purchaser_app_id = $org_result['data']['appId'] ?? $org_result['data']['app_id'] ?? null;
            }

            // Save purchaser credentials if found
            if ($purchaser_api_key && $purchaser_app_id) {
                update_user_meta($customer_id, '_monarch_temp_org_api_key', $purchaser_api_key);
                update_user_meta($customer_id, '_monarch_temp_org_app_id', $purchaser_app_id);
                $logger->debug('Purchaser credentials saved', array(
                    'api_key_last_4' => substr($purchaser_api_key, -4),
                    'app_id' => $purchaser_app_id
                ));
            } else {
                // Log warning - credentials not found, will fall back to merchant credentials
                $logger->debug('Purchaser credentials NOT found in response - will use merchant credentials', array(
                    'org_result_keys' => array_keys($org_result['data'] ?? []),
                    'api_structure' => $org_api ? array_keys($org_api) : 'null'
                ));
            }

            // Log organization creation
            $logger->log_customer_event('organization_created', $customer_id, array(
                'org_id' => $org_id,
                'user_id' => $user_id,
                'has_purchaser_credentials' => !empty($purchaser_api_key)
            ));

            // Log the bank linking URL for debugging
            $logger->debug('Bank linking URL details', array(
                'original_url' => $org_result['data']['partner_embedded_url'] ?? 'not set',
                'cleaned_url' => $bank_linking_url,
                'has_placeholders' => strpos($bank_linking_url, '{') !== false
            ));

            wp_send_json_success(array(
                'org_id' => $org_id,
                'user_id' => $user_id,
                'bank_linking_url' => $bank_linking_url
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Organization creation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for bank connection completion
     *
     * For EMBEDDED bank linking flow:
     * - PayToken is created by Monarch when user completes bank linking via Yodlee
     * - We retrieve the PayToken via getLatestPayToken
     * - We then explicitly ASSIGN the PayToken to the organization to ensure it's properly linked
     * - This assignment step is critical - without it, the PayToken may be "Invalid" during transactions
     */
    public function ajax_bank_connection_complete() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
        }

        $customer_id = get_current_user_id();
        $org_id = get_user_meta($customer_id, '_monarch_temp_org_id', true);
        $user_id = get_user_meta($customer_id, '_monarch_temp_user_id', true);
        $paytoken_id = sanitize_text_field($_POST['paytoken_id']);

        if (!$org_id || !$user_id || !$paytoken_id) {
            wp_send_json_error('Missing organization or paytoken data');
        }

        try {
            $logger = WC_Monarch_Logger::instance();

            $logger->debug('Bank connection complete - starting assignment', array(
                'org_id' => $org_id,
                'user_id' => $user_id,
                'paytoken_id' => $paytoken_id,
                'flow' => 'embedded_bank_linking'
            ));

            // Get purchaser's API credentials
            $purchaser_api_key = get_user_meta($customer_id, '_monarch_temp_org_api_key', true);
            $purchaser_app_id = get_user_meta($customer_id, '_monarch_temp_org_app_id', true);

            // Use purchaser credentials if available, otherwise fall back to merchant credentials
            $api_key_to_use = $purchaser_api_key ?: $this->api_key;
            $app_id_to_use = $purchaser_app_id ?: $this->app_id;

            // Initialize Monarch API
            $monarch_api = new Monarch_API(
                $api_key_to_use,
                $app_id_to_use,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );

            // CRITICAL: Explicitly assign PayToken to the organization
            // Even though Yodlee creates the PayToken, it may not be properly assigned to the org
            // This ensures the PayToken is valid for transactions
            $assign_result = $monarch_api->assign_paytoken($paytoken_id, $org_id);

            if (!$assign_result['success']) {
                // Log the error but continue - the PayToken might already be assigned
                $logger->warning('PayToken assignment returned error (may already be assigned)', array(
                    'org_id' => $org_id,
                    'paytoken_id' => $paytoken_id,
                    'error' => $assign_result['error'] ?? 'Unknown error'
                ));
            } else {
                $logger->info('PayToken successfully assigned to organization', array(
                    'org_id' => $org_id,
                    'paytoken_id' => $paytoken_id
                ));
            }

            // Save permanent user data
            update_user_meta($customer_id, '_monarch_org_id', $org_id);
            update_user_meta($customer_id, '_monarch_user_id', $user_id);
            update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);
            update_user_meta($customer_id, '_monarch_connected_date', current_time('mysql'));

            // Copy temp API credentials to permanent
            $temp_api_key = get_user_meta($customer_id, '_monarch_temp_org_api_key', true);
            $temp_app_id = get_user_meta($customer_id, '_monarch_temp_org_app_id', true);
            if ($temp_api_key && $temp_app_id) {
                update_user_meta($customer_id, '_monarch_org_api_key', $temp_api_key);
                update_user_meta($customer_id, '_monarch_org_app_id', $temp_app_id);
            }

            // Clean up temporary data
            delete_user_meta($customer_id, '_monarch_temp_org_id');
            delete_user_meta($customer_id, '_monarch_temp_user_id');
            delete_user_meta($customer_id, '_monarch_temp_org_api_key');
            delete_user_meta($customer_id, '_monarch_temp_org_app_id');

            // Log bank connection
            $logger->log_customer_event('bank_connected', $customer_id, array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id
            ));

            wp_send_json_success(array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id
            ));

        } catch (Exception $e) {
            wp_send_json_error('Bank connection completion failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for checking bank connection status
     *
     * IMPORTANT: Must use the PURCHASER's API credentials
     * The orgId must be associated with the security headers being used.
     */
    public function ajax_check_bank_status() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
        }

        $org_id = sanitize_text_field($_POST['org_id']);

        if (!$org_id) {
            wp_send_json_error('Organization ID is required');
        }

        try {
            $customer_id = get_current_user_id();

            // IMPORTANT: Use the PURCHASER's API credentials
            // The orgId must be associated with the security headers being used
            $purchaser_api_key = get_user_meta($customer_id, '_monarch_temp_org_api_key', true);
            $purchaser_app_id = get_user_meta($customer_id, '_monarch_temp_org_app_id', true);

            // Use purchaser credentials if available, otherwise fall back to merchant credentials
            $api_key_to_use = $purchaser_api_key ?: $this->api_key;
            $app_id_to_use = $purchaser_app_id ?: $this->app_id;

            // Query Monarch API to get organization details including paytokens
            $api_url = $this->testmode
                ? 'https://devapi.monarch.is/v1'
                : 'https://api.monarch.is/v1';

            $response = wp_remote_get($api_url . '/organization/' . $org_id, array(
                'headers' => array(
                    'accept' => 'application/json',
                    'X-API-KEY' => $api_key_to_use,
                    'X-APP-ID' => $app_id_to_use
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                wp_send_json_error('Failed to check bank status: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code >= 200 && $status_code < 300) {
                // Check if organization has paytokens
                $paytokens = $body['payTokens'] ?? $body['paytokens'] ?? array();

                if (!empty($paytokens) && is_array($paytokens)) {
                    // Get the first paytoken (or the most recent one)
                    $paytoken = is_array($paytokens[0]) ? $paytokens[0] : array('_id' => $paytokens[0]);
                    $paytoken_id = $paytoken['_id'] ?? $paytoken['payToken'] ?? $paytokens[0];

                    wp_send_json_success(array(
                        'connected' => true,
                        'paytoken_id' => $paytoken_id,
                        'org_id' => $org_id
                    ));
                } else {
                    wp_send_json_success(array(
                        'connected' => false,
                        'message' => 'No bank account connected yet'
                    ));
                }
            } else {
                wp_send_json_error('Failed to retrieve organization status');
            }

        } catch (Exception $e) {
            wp_send_json_error('Error checking bank status: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for getting latest paytoken after embedded bank linking
     * This is the correct flow per Monarch documentation:
     * After user links bank in iframe, call /v1/getlatestpaytoken/[organizationID]
     *
     * IMPORTANT: Must use the PURCHASER's API credentials (returned when organization was created)
     * NOT the merchant's credentials. The orgId must be associated with the security headers.
     */
    public function ajax_get_latest_paytoken() {
        $logger = WC_Monarch_Logger::instance();

        // Verify nonce
        if (!check_ajax_referer('monarch_ach_nonce', 'nonce', false)) {
            $logger->error('Nonce verification failed');
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check if user is logged in (allow guest checkout)
        if (!is_user_logged_in() && !isset($_POST['org_id'])) {
            wp_send_json_error('User must be logged in or provide organization ID');
            return;
        }

        $org_id = isset($_POST['org_id']) ? sanitize_text_field($_POST['org_id']) : '';

        if (empty($org_id)) {
            wp_send_json_error('Organization ID is required');
            return;
        }

        try {
            $customer_id = get_current_user_id();

            // IMPORTANT: Use the PURCHASER's API credentials for getLatestPayToken
            // The orgId must be associated with the security headers being used
            $purchaser_api_key = get_user_meta($customer_id, '_monarch_temp_org_api_key', true);
            $purchaser_app_id = get_user_meta($customer_id, '_monarch_temp_org_app_id', true);

            // Log credentials being used
            $logger->debug('ajax_get_latest_paytoken called', array(
                'org_id' => $org_id,
                'using_purchaser_credentials' => !empty($purchaser_api_key),
                'purchaser_api_key_last_4' => $purchaser_api_key ? substr($purchaser_api_key, -4) : 'N/A',
                'purchaser_app_id' => $purchaser_app_id ?: 'N/A',
                'merchant_api_key_last_4' => substr($this->api_key, -4),
                'merchant_app_id' => $this->app_id,
                'testmode' => $this->testmode ? 'yes' : 'no'
            ));

            // Use purchaser credentials if available, otherwise fall back to merchant credentials
            $api_key_to_use = $purchaser_api_key ?: $this->api_key;
            $app_id_to_use = $purchaser_app_id ?: $this->app_id;

            // Initialize Monarch API with the correct credentials
            $monarch_api = new Monarch_API(
                $api_key_to_use,
                $app_id_to_use,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );

            // Call getLatestPayToken API endpoint
            $result = $monarch_api->get_latest_paytoken($org_id);

            if ($result['success']) {
                $data = $result['data'];

                // Extract paytoken ID from response
                // The API may return it in different formats, so check multiple possible fields
                $paytoken_id = $data['_id'] ?? $data['payTokenId'] ?? $data['paytoken_id'] ?? $data['payToken'] ?? null;

                if ($paytoken_id) {
                    // CRITICAL: Explicitly assign PayToken to the organization
                    // This ensures the PayToken is valid for transactions
                    $assign_result = $monarch_api->assign_paytoken($paytoken_id, $org_id);

                    if (!$assign_result['success']) {
                        // Log the warning but continue - PayToken might already be assigned
                        $logger->warning('PayToken assignment returned error during retrieval (may already be assigned)', array(
                            'org_id' => $org_id,
                            'paytoken_id' => $paytoken_id,
                            'error' => $assign_result['error'] ?? 'Unknown error'
                        ));
                    } else {
                        $logger->info('PayToken assigned to organization during retrieval', array(
                            'org_id' => $org_id,
                            'paytoken_id' => $paytoken_id
                        ));
                    }

                    // Store the paytoken for the current user
                    $customer_id = get_current_user_id();
                    update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);

                    // Also store org_id permanently now that bank is linked
                    $temp_org_id = get_user_meta($customer_id, '_monarch_temp_org_id', true);
                    if ($temp_org_id) {
                        update_user_meta($customer_id, '_monarch_org_id', $temp_org_id);
                    }

                    // Log success
                    $logger->log_customer_event('paytoken_retrieved', $customer_id, array(
                        'org_id' => $org_id,
                        'paytoken_id' => $paytoken_id,
                        'assigned' => $assign_result['success'] ?? false
                    ));

                    wp_send_json_success(array(
                        'connected' => true,
                        'paytoken_id' => $paytoken_id,
                        'org_id' => $org_id,
                        'message' => 'Bank account connected successfully'
                    ));
                } else {
                    // No paytoken found - bank linking may not be complete yet
                    $logger->debug('PayToken not found in response', array(
                        'org_id' => $org_id,
                        'response_data' => $data
                    ));
                    wp_send_json_error('PayToken not found. Please complete bank linking first.');
                }
            } else {
                // API call failed - could be 404 (no paytoken) or other error
                $error_message = $result['error'] ?? 'Failed to retrieve paytoken';
                $status_code = $result['status_code'] ?? 0;

                $logger->debug('getLatestPayToken API failed', array(
                    'org_id' => $org_id,
                    'error' => $error_message,
                    'status_code' => $status_code,
                    'using_purchaser_credentials' => !empty($purchaser_api_key),
                    'full_response' => $result
                ));

                // 404 typically means no paytoken exists yet
                if ($status_code == 404 || strpos(strtolower($error_message), 'not found') !== false) {
                    $creds_info = !empty($purchaser_api_key) ? '(using purchaser credentials)' : '(using merchant credentials - purchaser credentials not found)';
                    wp_send_json_error('PayToken not found ' . $creds_info . '. Bank linking may not be complete yet.');
                } else {
                    $creds_info = !empty($purchaser_api_key) ? '(purchaser creds)' : '(merchant creds)';
                    wp_send_json_error($error_message . ' ' . $creds_info);
                }
            }

        } catch (Exception $e) {
            wp_send_json_error('Error retrieving paytoken: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for disconnecting bank account
     */
    public function ajax_disconnect_bank() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
        }

        $customer_id = get_current_user_id();

        // Delete all Monarch-related user meta
        delete_user_meta($customer_id, '_monarch_org_id');
        delete_user_meta($customer_id, '_monarch_user_id');
        delete_user_meta($customer_id, '_monarch_paytoken_id');
        delete_user_meta($customer_id, '_monarch_org_api_key');
        delete_user_meta($customer_id, '_monarch_org_app_id');
        delete_user_meta($customer_id, '_monarch_temp_org_id');
        delete_user_meta($customer_id, '_monarch_temp_user_id');
        delete_user_meta($customer_id, '_monarch_temp_org_api_key');
        delete_user_meta($customer_id, '_monarch_temp_org_app_id');

        // Log the disconnection
        $logger = WC_Monarch_Logger::instance();
        $logger->log_customer_event('bank_disconnected', $customer_id, array());

        wp_send_json_success(array('message' => 'Bank account disconnected successfully'));
    }

    /**
     * AJAX handler for manual bank entry
     * Creates organization + paytoken + assigns in one flow
     */
    public function ajax_manual_bank_entry() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
        }

        try {
            $monarch_api = new Monarch_API(
                $this->api_key,
                $this->app_id,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );

            // Get current user data
            $current_user = wp_get_current_user();
            $customer_id = get_current_user_id();

            // Validate required fields
            $bank_name = sanitize_text_field($_POST['bank_name']);
            $routing_number = sanitize_text_field($_POST['routing_number']);
            $account_number = sanitize_text_field($_POST['account_number']);
            $account_type = sanitize_text_field($_POST['account_type']);

            if (empty($bank_name) || empty($routing_number) || empty($account_number)) {
                wp_send_json_error('Please fill in all bank details');
            }

            // Validate routing number (9 digits)
            if (!preg_match('/^\d{9}$/', $routing_number)) {
                wp_send_json_error('Routing number must be exactly 9 digits');
            }

            // Prepare customer data
            $phone = preg_replace('/[^0-9]/', '', sanitize_text_field($_POST['monarch_phone']));
            $phone = substr($phone, -10);

            $dob_raw = sanitize_text_field($_POST['monarch_dob']);
            $dob = date('m/d/Y', strtotime($dob_raw));

            // Generate unique email for Monarch
            $user_email = $current_user->user_email;
            $email_parts = explode('@', $user_email);
            $unique_email = $email_parts[0] . '+wp' . $customer_id . '_' . time() . '@' . ($email_parts[1] ?? 'example.com');

            $customer_data = array(
                'first_name' => $current_user->user_firstname ?: sanitize_text_field($_POST['billing_first_name']),
                'last_name' => $current_user->user_lastname ?: sanitize_text_field($_POST['billing_last_name']),
                'email' => $unique_email,
                'password' => wp_generate_password(12, false),
                'phone' => $phone,
                'company_name' => sanitize_text_field($_POST['monarch_company'] ?? ''),
                'dob' => $dob,
                'address_1' => sanitize_text_field($_POST['billing_address_1']),
                'address_2' => sanitize_text_field($_POST['billing_address_2'] ?? ''),
                'city' => sanitize_text_field($_POST['billing_city']),
                'state' => sanitize_text_field($_POST['billing_state']),
                'zip' => sanitize_text_field($_POST['billing_postcode']),
                'country' => sanitize_text_field($_POST['billing_country'])
            );

            // Step 1: Create organization
            $org_result = $monarch_api->create_organization($customer_data);

            if (!$org_result['success']) {
                wp_send_json_error('Organization creation failed: ' . $org_result['error']);
            }

            $user_id = $org_result['data']['_id'];
            $org_id = $org_result['data']['orgId'];

            // Log organization creation
            $logger = WC_Monarch_Logger::instance();
            $logger->log_customer_event('organization_created_manual', $customer_id, array(
                'org_id' => $org_id,
                'user_id' => $user_id
            ));

            // Step 2: Create PayToken with bank details
            $bank_data = array(
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'routing_number' => $routing_number,
                'account_type' => $account_type
            );

            $paytoken_result = $monarch_api->create_paytoken($user_id, $bank_data);

            if (!$paytoken_result['success']) {
                wp_send_json_error('Bank account setup failed: ' . $paytoken_result['error']);
            }

            $paytoken_id = $paytoken_result['data']['payToken'] ?? $paytoken_result['data']['_id'];

            // Step 3: Assign PayToken to organization
            $assign_result = $monarch_api->assign_paytoken($paytoken_id, $org_id);

            if (!$assign_result['success']) {
                wp_send_json_error('Failed to link bank account: ' . $assign_result['error']);
            }

            // Save permanent user data
            update_user_meta($customer_id, '_monarch_org_id', $org_id);
            update_user_meta($customer_id, '_monarch_user_id', $user_id);
            update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);
            update_user_meta($customer_id, '_monarch_connected_date', current_time('mysql'));

            // Store the purchaser org's API credentials for transactions
            // The Monarch API requires using the purchaser's own credentials for sale transactions
            $org_api = $org_result['data']['api'] ?? null;
            if ($org_api) {
                $credentials_key = $this->testmode ? 'sandbox' : 'prod';
                $org_credentials = $org_api[$credentials_key] ?? null;
                if ($org_credentials) {
                    update_user_meta($customer_id, '_monarch_org_api_key', $org_credentials['api_key']);
                    update_user_meta($customer_id, '_monarch_org_app_id', $org_credentials['app_id']);
                }
            }

            // Log successful bank connection
            $logger->log_customer_event('bank_connected_manual', $customer_id, array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id,
                'bank_name' => $bank_name
            ));

            wp_send_json_success(array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id,
                'message' => 'Bank account connected successfully'
            ));

        } catch (Exception $e) {
            wp_send_json_error('Manual bank entry failed: ' . $e->getMessage());
        }
    }

    /**
     * Find existing organization by email
     */
    private function find_organization_by_email($email) {
        $api_url = $this->testmode
            ? 'https://devapi.monarch.is/v1'
            : 'https://api.monarch.is/v1';

        // Search for organization by email
        $response = wp_remote_get($api_url . '/organization?email=' . urlencode($email), array(
            'headers' => array(
                'accept' => 'application/json',
                'X-API-KEY' => $this->api_key,
                'X-APP-ID' => $this->app_id
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 200 && $status_code < 300 && !empty($body)) {
            // Return first matching organization
            if (is_array($body) && isset($body[0])) {
                return $body[0];
            }
            // If single object returned
            if (isset($body['orgId']) || isset($body['_id'])) {
                return $body;
            }
        }

        return null;
    }
}