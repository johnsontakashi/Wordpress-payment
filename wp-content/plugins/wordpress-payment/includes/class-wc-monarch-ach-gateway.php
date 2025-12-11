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
        add_action('wp_ajax_monarch_disconnect_bank', array($this, 'ajax_disconnect_bank'));
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
                'description' => 'Place the payment gateway in test mode using test API credentials.',
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'partner_name' => array(
                'title' => 'Partner Name',
                'type' => 'text',
                'description' => 'Your partner name as registered with Monarch.',
                'default' => '',
                'desc_tip' => true,
            ),
            'test_api_key' => array(
                'title' => 'Test API Key',
                'type' => 'text',
                'description' => 'Get your API credentials from Monarch.',
                'default' => '',
                'desc_tip' => true,
            ),
            'test_app_id' => array(
                'title' => 'Test App ID',
                'type' => 'text',
                'description' => 'Get your API credentials from Monarch.',
                'default' => '',
                'desc_tip' => true,
            ),
            'test_merchant_org_id' => array(
                'title' => 'Test Merchant Org ID',
                'type' => 'text',
                'description' => 'Your merchant organization ID from Monarch.',
                'default' => '',
                'desc_tip' => true,
            ),
            'live_api_key' => array(
                'title' => 'Live API Key',
                'type' => 'text',
                'description' => 'Get your API credentials from Monarch.',
                'default' => '',
                'desc_tip' => true,
            ),
            'live_app_id' => array(
                'title' => 'Live App ID',
                'type' => 'text',
                'description' => 'Get your API credentials from Monarch.',
                'default' => '',
                'desc_tip' => true,
            ),
            'live_merchant_org_id' => array(
                'title' => 'Live Merchant Org ID',
                'type' => 'text',
                'description' => 'Your merchant organization ID from Monarch.',
                'default' => '',
                'desc_tip' => true,
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

            <p class="form-row form-row-wide">
                <label for="monarch_phone">Phone Number <span class="required">*</span></label>
                <input id="monarch_phone" name="monarch_phone" type="tel" required>
            </p>

            <p class="form-row form-row-wide">
                <label for="monarch_dob">Date of Birth <span class="required">*</span></label>
                <input id="monarch_dob" name="monarch_dob" type="date" required>
            </p>

            <p class="form-row form-row-wide">
                <button type="button" id="monarch-connect-bank" class="button alt">Connect Bank Account</button>
                <span id="monarch-connect-spinner" class="spinner" style="display:none; float:none; margin-left:10px;"></span>
            </p>

            <p class="monarch-security-notice">
                You will be redirected to our secure banking partner to verify your bank account.
            </p>

            <input type="hidden" id="monarch_org_id" name="monarch_org_id" value="">
            <input type="hidden" id="monarch_paytoken_id" name="monarch_paytoken_id" value="">
            <input type="hidden" id="monarch_bank_verified" name="monarch_bank_verified" value="">
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
            $monarch_api = new Monarch_API(
                $this->api_key,
                $this->app_id,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );

            // Get verified org_id and paytoken_id from user meta
            $org_id = get_user_meta($customer_id, '_monarch_org_id', true);
            $paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);

            // Bank account must be connected through Monarch's verification flow
            if (!$org_id || !$paytoken_id) {
                throw new Exception('Please connect your bank account before placing an order.');
            }

            $order->add_order_note('Using verified bank account - orgId: ' . $org_id . ', payTokenId: ' . $paytoken_id);

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

            $order->add_order_note('Transaction created - ID: ' . ($transaction_result['data']['id'] ?? 'N/A'));

            // Save transaction data
            $this->save_transaction_data($order_id, $transaction_result['data'], $org_id, $paytoken_id);

            $order->payment_complete();
            $order->add_order_note('ACH payment processed. Transaction ID: ' . ($transaction_result['data']['id'] ?? 'N/A'));

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
     * AJAX handler for creating organization and getting bank linking URL
     */
    public function ajax_create_organization() {
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
            
            // Prepare customer data
            $phone = preg_replace('/[^0-9]/', '', sanitize_text_field($_POST['monarch_phone']));
            $phone = substr($phone, -10); // Last 10 digits

            $dob_raw = sanitize_text_field($_POST['monarch_dob']);
            $dob = date('m/d/Y', strtotime($dob_raw)); // Convert to mm/dd/yyyy

            $customer_data = array(
                'first_name' => $current_user->user_firstname ?: sanitize_text_field($_POST['billing_first_name']),
                'last_name' => $current_user->user_lastname ?: sanitize_text_field($_POST['billing_last_name']),
                'email' => $current_user->user_email,
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

            // Handle "email already in use" error - look up existing organization
            if (!$org_result['success']) {
                $error_message = strtolower($org_result['error'] ?? '');

                if (strpos($error_message, 'email') !== false && strpos($error_message, 'already') !== false) {
                    // Try to find existing organization by email
                    $existing_org = $this->find_organization_by_email($current_user->user_email);

                    if ($existing_org) {
                        $user_id = $existing_org['_id'] ?? $existing_org['userId'] ?? '';
                        $org_id = $existing_org['orgId'] ?? '';
                        $bank_linking_url = $existing_org['partner_embedded_url'] ?? $existing_org['bankLinkingUrl'] ?? '';

                        // Generate bank linking URL if not present
                        if (empty($bank_linking_url)) {
                            $bank_linking_url = $this->testmode
                                ? 'https://dev.monarch.is/link-bank/' . $org_id . '?price={price}&redirectUrl={redirectUrl}'
                                : 'https://app.monarch.is/link-bank/' . $org_id . '?price={price}&redirectUrl={redirectUrl}';
                        }

                        // Save and return existing org data
                        $customer_id = get_current_user_id();
                        update_user_meta($customer_id, '_monarch_temp_org_id', $org_id);
                        update_user_meta($customer_id, '_monarch_temp_user_id', $user_id);

                        wp_send_json_success(array(
                            'org_id' => $org_id,
                            'user_id' => $user_id,
                            'bank_linking_url' => $bank_linking_url,
                            'existing' => true
                        ));
                        return;
                    }
                }

                wp_send_json_error($org_result['error']);
            }

            $user_id = $org_result['data']['_id'];
            $org_id = $org_result['data']['orgId'];
            $bank_linking_url = $org_result['data']['partner_embedded_url'] ?? $org_result['data']['bankLinkingUrl'] ?? $org_result['data']['connectionUrl'] ?? '';
            
            // Save organization data temporarily (will be permanent after bank connection)
            $customer_id = get_current_user_id();
            update_user_meta($customer_id, '_monarch_temp_org_id', $org_id);
            update_user_meta($customer_id, '_monarch_temp_user_id', $user_id);
            
            // Log organization creation
            $logger = WC_Monarch_Logger::instance();
            $logger->log_customer_event('organization_created', $customer_id, array(
                'org_id' => $org_id,
                'user_id' => $user_id
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
            $monarch_api = new Monarch_API(
                $this->api_key,
                $this->app_id,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );
            
            // Assign PayToken to organization
            $assign_result = $monarch_api->assign_paytoken($paytoken_id, $org_id);
            
            if (!$assign_result['success']) {
                wp_send_json_error('Failed to assign bank account: ' . $assign_result['error']);
            }
            
            // Save permanent user data
            update_user_meta($customer_id, '_monarch_org_id', $org_id);
            update_user_meta($customer_id, '_monarch_user_id', $user_id);
            update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);
            
            // Clean up temporary data
            delete_user_meta($customer_id, '_monarch_temp_org_id');
            delete_user_meta($customer_id, '_monarch_temp_user_id');
            
            // Log bank connection
            $logger = WC_Monarch_Logger::instance();
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
            // Query Monarch API to get organization details including paytokens
            $api_url = $this->testmode
                ? 'https://devapi.monarch.is/v1'
                : 'https://api.monarch.is/v1';

            $response = wp_remote_get($api_url . '/organization/' . $org_id, array(
                'headers' => array(
                    'accept' => 'application/json',
                    'X-API-KEY' => $this->api_key,
                    'X-APP-ID' => $this->app_id
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
        delete_user_meta($customer_id, '_monarch_temp_org_id');
        delete_user_meta($customer_id, '_monarch_temp_user_id');

        // Log the disconnection
        $logger = WC_Monarch_Logger::instance();
        $logger->log_customer_event('bank_disconnected', $customer_id, array());

        wp_send_json_success(array('message' => 'Bank account disconnected successfully'));
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