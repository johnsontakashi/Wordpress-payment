<?php
/**
 * Plugin Name: Monarch WooCommerce Payment Gateway
 * Description: Monarch Payment Gateway.
 * Version: 1.0.1
 * Author: Monarch Technologies Inc.
 * License: GPL v2 or later
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

define('WC_MONARCH_ACH_VERSION', '1.0.1');
define('WC_MONARCH_ACH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_MONARCH_ACH_PLUGIN_URL', plugin_dir_url(__FILE__));

class WC_Monarch_ACH_Gateway_Plugin {

    private static $gateway_instance = null;

    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Register AJAX handlers early (before gateway is instantiated)
        add_action('init', array($this, 'register_ajax_handlers'));

        // Ensure rewrite rules are flushed if needed (on admin)
        add_action('admin_init', array($this, 'maybe_flush_rewrite_rules'));
    }

    /**
     * Check if rewrite rules need to be flushed
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('monarch_ach_rewrite_rules_version') !== WC_MONARCH_ACH_VERSION) {
            $this->register_callback_endpoint();
            flush_rewrite_rules();
            update_option('monarch_ach_rewrite_rules_version', WC_MONARCH_ACH_VERSION);
        }
    }

    public function init() {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-logger.php';
        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-monarch-api.php';
        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-ach-gateway.php';
        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-admin.php';
        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-cron.php';

        // Register WooCommerce Blocks support
        add_action('woocommerce_blocks_loaded', array($this, 'register_blocks_support'));

        // Register customer-facing transaction display hook (must be here, not in gateway constructor)
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_transaction_details_for_customer'), 10, 1);
    }

    /**
     * Display transaction details to customers on order view page (My Account → Orders → View)
     * This is registered in plugin init() to ensure it always runs, not just when gateway is instantiated
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

        // If no transaction in custom table, try to get from order meta
        if (!$transaction) {
            $transaction_id = $order->get_transaction_id();
            $monarch_transaction_id = $order->get_meta('_monarch_transaction_id');

            if ($transaction_id || $monarch_transaction_id) {
                // Create a pseudo-transaction object from order meta
                $transaction = (object) array(
                    'transaction_id' => $transaction_id ?: $monarch_transaction_id,
                    'status' => $this->map_order_status_to_transaction_status($order->get_status()),
                    'amount' => $order->get_total(),
                    'created_at' => $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i:s') : ''
                );
            }
        }

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
                    <?php if (!empty($transaction->created_at)): ?>
                    <tr>
                        <th>Date</th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at))); ?></td>
                    </tr>
                    <?php endif; ?>
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
     * Map WooCommerce order status to transaction status
     */
    private function map_order_status_to_transaction_status($order_status) {
        $status_map = array(
            'pending' => 'pending',
            'processing' => 'processing',
            'on-hold' => 'pending',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed'
        );
        return $status_map[$order_status] ?? 'pending';
    }

    /**
     * Register WooCommerce Blocks payment method support
     */
    public function register_blocks_support() {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-blocks.php';

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function($payment_method_registry) {
                $payment_method_registry->register(new WC_Monarch_ACH_Blocks_Support());
            }
        );
    }

    /**
     * Register AJAX handlers
     */
    public function register_ajax_handlers() {
        add_action('wp_ajax_monarch_disconnect_bank', array($this, 'ajax_disconnect_bank'));
        add_action('wp_ajax_monarch_create_organization', array($this, 'ajax_create_organization'));
        add_action('wp_ajax_nopriv_monarch_create_organization', array($this, 'ajax_create_organization'));
        add_action('wp_ajax_monarch_bank_connection_complete', array($this, 'ajax_bank_connection_complete'));
        add_action('wp_ajax_nopriv_monarch_bank_connection_complete', array($this, 'ajax_bank_connection_complete'));
        add_action('wp_ajax_monarch_check_bank_status', array($this, 'ajax_check_bank_status'));
        add_action('wp_ajax_nopriv_monarch_check_bank_status', array($this, 'ajax_check_bank_status'));
        add_action('wp_ajax_monarch_get_latest_paytoken', array($this, 'ajax_get_latest_paytoken'));
        add_action('wp_ajax_nopriv_monarch_get_latest_paytoken', array($this, 'ajax_get_latest_paytoken'));
        add_action('wp_ajax_monarch_manual_bank_entry', array($this, 'ajax_manual_bank_entry'));
        add_action('wp_ajax_nopriv_monarch_manual_bank_entry', array($this, 'ajax_manual_bank_entry'));
        // CRON manual status update handler
        add_action('wp_ajax_monarch_manual_status_update', array($this, 'ajax_manual_status_update'));

        // Handle bank callback redirect (prevents 404 error from Yodlee redirect)
        // Use multiple hooks at different stages to ensure we catch the redirect
        // Priority 1 ensures we run before anything else
        add_action('init', array($this, 'handle_bank_callback_init'), 1);
        add_action('send_headers', array($this, 'handle_bank_callback_early'), 1);
        add_action('wp', array($this, 'handle_bank_callback'), 1);
        add_action('template_redirect', array($this, 'handle_bank_callback'), 1);

        // Register a dedicated callback endpoint
        add_action('init', array($this, 'register_callback_endpoint'), 10);
        add_action('parse_request', array($this, 'handle_callback_endpoint'));
    }

    /**
     * Very early handler for bank callback - runs at init stage (priority 1)
     * This is before WordPress even determines the query
     */
    public function handle_bank_callback_init() {
        // Check immediately on init for callback parameters
        if (isset($_GET['monarch_bank_callback']) && $_GET['monarch_bank_callback'] === '1') {
            $org_id = isset($_GET['org_id']) ? sanitize_text_field($_GET['org_id']) : '';
            $this->output_ok_page($org_id);
        }
    }

    /**
     * Register callback endpoint rewrite rule
     */
    public function register_callback_endpoint() {
        add_rewrite_rule('^monarch-bank-callback/?', 'index.php?monarch_bank_ok=1', 'top');
        add_rewrite_tag('%monarch_bank_ok%', '1');
    }

    /**
     * Handle the callback endpoint request
     */
    public function handle_callback_endpoint($wp) {
        if (isset($wp->query_vars['monarch_bank_ok']) ||
            (isset($_GET['monarch_bank_ok']) && $_GET['monarch_bank_ok'] === '1')) {
            $this->output_ok_page();
        }
    }

    /**
     * Output the OK success page
     */
    private function output_ok_page($org_id = '') {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>OK</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    background: #f0fff4;
                }
                .success-container {
                    text-align: center;
                    padding: 60px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                .success-icon {
                    font-size: 72px;
                    color: #28a745;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #28a745;
                    font-size: 48px;
                    margin: 0 0 10px 0;
                }
                p {
                    color: #666;
                    font-size: 16px;
                    margin: 5px 0;
                }
            </style>
        </head>
        <body>
            <div class="success-container">
                <div class="success-icon">✓</div>
                <h1>OK</h1>
                <p>Bank connection complete!</p>
                <p style="font-size: 14px; color: #999;">You can close this window.</p>
            </div>
            <script>
                // Notify parent window that bank connection is complete
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'MONARCH_BANK_CALLBACK',
                        status: 'SUCCESS',
                        org_id: '<?php echo esc_js($org_id); ?>'
                    }, '*');
                }
                // Try to trigger verification
                try {
                    if (window.parent && window.parent.jQuery) {
                        window.parent.jQuery('#monarch-bank-connected-btn').click();
                    }
                } catch (e) {}
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Early handler for bank callback - runs at send_headers stage
     * This is the earliest point we can catch the request before 404 is sent
     */
    public function handle_bank_callback_early() {
        $this->handle_bank_callback();
    }

    /**
     * Handle bank connection callback from Yodlee iframe redirect
     * This prevents 404 errors when Yodlee redirects back to the checkout page
     */
    public function handle_bank_callback() {
        // Check if this is a bank callback request - check multiple possible indicators
        $is_callback = false;
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Method 1: Our explicit callback parameter
        if (isset($_GET['monarch_bank_callback']) && $_GET['monarch_bank_callback'] === '1') {
            $is_callback = true;
        }

        // Method 2: Check for /monarch-callback/ in URL path
        if (strpos($request_uri, 'monarch-callback') !== false || strpos($request_uri, 'monarch_callback') !== false) {
            $is_callback = true;
        }

        // Method 3: Yodlee FastLink callback parameters
        if (isset($_GET['status']) || isset($_GET['providerAccountId']) || isset($_GET['requestId'])) {
            $is_callback = true;
        }

        // Method 4: Yodlee may also use these parameters
        if (isset($_GET['code']) && isset($_GET['state'])) {
            $is_callback = true;
        }

        // Method 5: Check for Yodlee site parameter
        if (isset($_GET['sites']) || isset($_GET['site'])) {
            $is_callback = true;
        }

        // Method 6: Check for fnToCall parameter (Yodlee FastLink)
        if (isset($_GET['fnToCall']) || isset($_GET['callback'])) {
            $is_callback = true;
        }

        // Method 7: Check HTTP Referer - if coming from Yodlee/FastLink domain
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (strpos($referer, 'yodlee') !== false || strpos($referer, 'fastlink') !== false) {
            $is_callback = true;
        }

        if (!$is_callback) {
            return;
        }

        // Get organization ID from callback (if available)
        $org_id = isset($_GET['org_id']) ? sanitize_text_field($_GET['org_id']) : '';

        // Output the OK page
        $this->output_ok_page($org_id);
    }

    /**
     * Get the gateway instance
     */
    private function get_gateway() {
        if (self::$gateway_instance === null) {
            if (!class_exists('WC_Monarch_ACH_Gateway')) {
                include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-logger.php';
                include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-monarch-api.php';
                include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-ach-gateway.php';
            }
            self::$gateway_instance = new WC_Monarch_ACH_Gateway();
        }
        return self::$gateway_instance;
    }

    /**
     * AJAX handler for disconnecting bank account
     */
    public function ajax_disconnect_bank() {
        $gateway = $this->get_gateway();
        $gateway->ajax_disconnect_bank();
    }

    /**
     * AJAX handler for creating organization
     */
    public function ajax_create_organization() {
        $gateway = $this->get_gateway();
        $gateway->ajax_create_organization();
    }

    /**
     * AJAX handler for bank connection complete
     */
    public function ajax_bank_connection_complete() {
        $gateway = $this->get_gateway();
        $gateway->ajax_bank_connection_complete();
    }

    /**
     * AJAX handler for checking bank status
     */
    public function ajax_check_bank_status() {
        $gateway = $this->get_gateway();
        $gateway->ajax_check_bank_status();
    }

    /**
     * AJAX handler for getting latest paytoken
     */
    public function ajax_get_latest_paytoken() {
        $gateway = $this->get_gateway();
        $gateway->ajax_get_latest_paytoken();
    }

    /**
     * AJAX handler for manual bank entry
     */
    public function ajax_manual_bank_entry() {
        $gateway = $this->get_gateway();
        $gateway->ajax_manual_bank_entry();
    }

    /**
     * AJAX handler for manual status update (CRON)
     */
    public function ajax_manual_status_update() {
        check_ajax_referer('monarch_admin_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // Include required files if not already loaded
        if (!class_exists('WC_Monarch_Logger')) {
            include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-logger.php';
        }
        if (!class_exists('Monarch_API')) {
            include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-monarch-api.php';
        }
        if (!class_exists('WC_Monarch_ACH_Gateway')) {
            include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-ach-gateway.php';
        }
        if (!class_exists('WC_Monarch_Cron')) {
            include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-cron.php';
        }

        $cron = WC_Monarch_Cron::instance();
        $result = $cron->update_pending_transactions();

        wp_send_json_success(array(
            'message' => sprintf(
                'Status update complete. Processed: %d, Updated: %d, Errors: %d',
                $result['processed'],
                $result['updated'],
                $result['errors']
            ),
            'processed' => $result['processed'],
            'updated' => $result['updated'],
            'errors' => $result['errors']
        ));
    }

    public function add_gateway_class($gateways) {
        $gateways[] = 'WC_Monarch_ACH_Gateway';
        return $gateways;
    }
    
    public function enqueue_scripts() {
        // Scripts are now handled by the gateway's payment_scripts() method
        // This ensures proper loading only when the gateway is available and enabled
    }
    
    public function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'monarch_ach_transactions';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            transaction_id varchar(100) NOT NULL,
            monarch_org_id varchar(50) NOT NULL,
            paytoken_id varchar(100) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            status varchar(20) NOT NULL,
            api_response longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY transaction_id (transaction_id),
            KEY order_id (order_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Schedule CRON job on activation
        if (!wp_next_scheduled('monarch_ach_update_transaction_status')) {
            wp_schedule_event(time(), 'every_two_hours', 'monarch_ach_update_transaction_status');
        }

        // Register rewrite rules and flush them
        $this->register_callback_endpoint();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation - clean up CRON jobs
     */
    public function deactivate() {
        // Unschedule CRON job
        $timestamp = wp_next_scheduled('monarch_ach_update_transaction_status');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'monarch_ach_update_transaction_status');
        }
    }
}

new WC_Monarch_ACH_Gateway_Plugin();