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
            echo '<p><strong>✓ Bank account connected</strong></p>';
            echo '<input type="hidden" name="monarch_org_id" value="' . esc_attr($monarch_org_id) . '">';
            echo '<input type="hidden" name="monarch_paytoken_id" value="' . esc_attr($paytoken_id) . '">';
            return;
        }
        
        ?>
        <div id="monarch-ach-form">
            <p><strong>Bank Account Information</strong></p>
            
            <p class="form-row form-row-first">
                <label for="monarch_bank_name">Bank Name <span class="required">*</span></label>
                <input id="monarch_bank_name" name="monarch_bank_name" type="text" required>
            </p>
            
            <p class="form-row form-row-last">
                <label for="monarch_account_type">Account Type <span class="required">*</span></label>
                <select id="monarch_account_type" name="monarch_account_type" required>
                    <option value="">Select Account Type</option>
                    <option value="CHECKING">Checking</option>
                    <option value="SAVINGS">Savings</option>
                </select>
            </p>
            
            <p class="form-row form-row-first">
                <label for="monarch_routing_number">Routing Number <span class="required">*</span></label>
                <input id="monarch_routing_number" name="monarch_routing_number" type="text" maxlength="9" required>
            </p>
            
            <p class="form-row form-row-last">
                <label for="monarch_account_number">Account Number <span class="required">*</span></label>
                <input id="monarch_account_number" name="monarch_account_number" type="text" required>
            </p>
            
            <p class="form-row">
                <label for="monarch_confirm_account">Confirm Account Number <span class="required">*</span></label>
                <input id="monarch_confirm_account" name="monarch_confirm_account" type="text" required>
            </p>
            
            <div class="clear"></div>
            
            <p><strong>Additional Information</strong></p>
            
            <p class="form-row form-row-first">
                <label for="monarch_phone">Phone Number <span class="required">*</span></label>
                <input id="monarch_phone" name="monarch_phone" type="tel" required>
            </p>
            
            <p class="form-row form-row-last">
                <label for="monarch_company">Company Name</label>
                <input id="monarch_company" name="monarch_company" type="text">
            </p>
            
            <p class="form-row">
                <label for="monarch_dob">Date of Birth <span class="required">*</span></label>
                <input id="monarch_dob" name="monarch_dob" type="date" required>
            </p>
            
            <div class="clear"></div>
            
            <div id="monarch-ach-errors" style="display:none; color: red; margin: 10px 0;"></div>
        </div>
        <?php
    }
    
    public function validate_fields() {
        $customer_id = get_current_user_id();
        $monarch_org_id = get_user_meta($customer_id, '_monarch_org_id', true);
        
        if ($monarch_org_id) {
            return true; // Customer already registered
        }
        
        if (empty($_POST['monarch_bank_name'])) {
            wc_add_notice('Bank name is required.', 'error');
            return false;
        }
        
        if (empty($_POST['monarch_account_type'])) {
            wc_add_notice('Account type is required.', 'error');
            return false;
        }
        
        if (empty($_POST['monarch_routing_number']) || strlen($_POST['monarch_routing_number']) !== 9) {
            wc_add_notice('Valid routing number is required (9 digits).', 'error');
            return false;
        }
        
        if (empty($_POST['monarch_account_number'])) {
            wc_add_notice('Account number is required.', 'error');
            return false;
        }
        
        if ($_POST['monarch_account_number'] !== $_POST['monarch_confirm_account']) {
            wc_add_notice('Account numbers do not match.', 'error');
            return false;
        }
        
        if (empty($_POST['monarch_phone'])) {
            wc_add_notice('Phone number is required.', 'error');
            return false;
        }
        
        if (empty($_POST['monarch_dob'])) {
            wc_add_notice('Date of birth is required.', 'error');
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
            
            // Check if customer already exists
            $monarch_org_id = get_user_meta($customer_id, '_monarch_org_id', true);
            $paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);
            
            if (!$monarch_org_id || !$paytoken_id) {
                // Create new customer and bank account
                $result = $this->setup_customer_and_bank_account($order, $monarch_api);
                if (!$result['success']) {
                    throw new Exception($result['error']);
                }
                $monarch_org_id = $result['org_id'];
                $paytoken_id = $result['paytoken_id'];
            }
            
            // Process the transaction
            $transaction_data = array(
                'amount' => $order->get_total(),
                'org_id' => $monarch_org_id,
                'paytoken_id' => $paytoken_id,
                'comment' => 'Order #' . $order_id
            );
            
            $transaction_result = $monarch_api->create_sale_transaction($transaction_data);
            
            if (!$transaction_result['success']) {
                throw new Exception($transaction_result['error']);
            }
            
            // Save transaction data
            $this->save_transaction_data($order_id, $transaction_result['data'], $monarch_org_id, $paytoken_id);
            
            // Log transaction
            $logger = WC_Monarch_Logger::instance();
            $logger->log_transaction('payment_processed', $order_id, $transaction_result['data']);
            
            // Mark order as processing
            $order->payment_complete();
            $order->add_order_note('ACH payment processed via Monarch API. Transaction ID: ' . $transaction_result['data']['id']);
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
            
        } catch (Exception $e) {
            wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
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
}