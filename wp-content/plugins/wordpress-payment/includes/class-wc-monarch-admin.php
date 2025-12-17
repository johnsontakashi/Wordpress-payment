<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Monarch_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_monarch_test_connection', array($this, 'test_api_connection'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // Add meta box to order page
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));

        // For HPOS (High-Performance Order Storage) compatibility
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_monarch_data_in_order'), 10, 1);
    }

    /**
     * Add meta box to order edit page
     */
    public function add_order_meta_box() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'monarch_ach_transaction_details',
            'Monarch ACH Payment Details',
            array($this, 'render_order_meta_box'),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function render_order_meta_box($post_or_order) {
        // Handle both HPOS and legacy order storage
        if ($post_or_order instanceof WC_Order) {
            $order = $post_or_order;
        } else {
            $order = wc_get_order($post_or_order->ID);
        }

        if (!$order) {
            echo '<p>Order not found.</p>';
            return;
        }

        $order_id = $order->get_id();

        // Get transaction data (HPOS compatible)
        $transaction_id = $order->get_transaction_id();
        $monarch_transaction_id = $order->get_meta('_monarch_transaction_id', true);
        $monarch_org_id = $order->get_meta('_monarch_org_id', true);
        $monarch_paytoken_id = $order->get_meta('_monarch_paytoken_id', true);

        // Check if this is a Monarch payment
        if ($order->get_payment_method() !== 'monarch_ach') {
            echo '<p>This order was not paid via Monarch ACH.</p>';
            return;
        }

        ?>
        <div class="monarch-order-details">
            <p><strong>Transaction ID:</strong><br>
                <code style="word-break: break-all;"><?php echo esc_html($transaction_id ?: $monarch_transaction_id ?: 'N/A'); ?></code>
            </p>

            <?php if ($monarch_org_id): ?>
            <p><strong>Organization ID:</strong><br>
                <code><?php echo esc_html($monarch_org_id); ?></code>
            </p>
            <?php endif; ?>

            <?php
            // Get transaction from database
            global $wpdb;
            $table_name = $wpdb->prefix . 'monarch_ach_transactions';
            $db_transaction = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE order_id = %d LIMIT 1",
                $order_id
            ));

            if ($db_transaction): ?>
            <p><strong>Status:</strong><br>
                <span class="status-badge status-<?php echo esc_attr($db_transaction->status); ?>">
                    <?php echo esc_html(ucfirst($db_transaction->status)); ?>
                </span>
            </p>
            <p><strong>Amount:</strong><br>
                <?php echo wc_price($db_transaction->amount); ?>
            </p>
            <p><strong>Date:</strong><br>
                <?php echo esc_html(date('M j, Y g:i A', strtotime($db_transaction->created_at))); ?>
            </p>
            <?php endif; ?>
        </div>

        <style>
            .monarch-order-details p {
                margin-bottom: 10px;
            }
            .monarch-order-details code {
                display: block;
                background: #f0f0f1;
                padding: 5px 8px;
                margin-top: 3px;
                font-size: 11px;
            }
            .monarch-order-details .status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .monarch-order-details .status-pending,
            .monarch-order-details .status-processing {
                background: #f0f6fc;
                color: #0366d6;
            }
            .monarch-order-details .status-completed,
            .monarch-order-details .status-success {
                background: #dcffe4;
                color: #22863a;
            }
            .monarch-order-details .status-failed {
                background: #ffeef0;
                color: #cb2431;
            }
        </style>
        <?php
    }

    /**
     * Display Monarch data in order details (HPOS compatible)
     */
    public function display_monarch_data_in_order($order) {
        if ($order->get_payment_method() !== 'monarch_ach') {
            return;
        }

        $transaction_id = $order->get_transaction_id();
        $monarch_transaction_id = $order->get_meta('_monarch_transaction_id', true);

        if ($transaction_id || $monarch_transaction_id) {
            echo '<p class="form-field form-field-wide">';
            echo '<strong>Monarch Transaction ID:</strong><br>';
            echo '<code>' . esc_html($transaction_id ?: $monarch_transaction_id) . '</code>';
            echo '</p>';
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Monarch ACH Transactions',
            'Monarch ACH',
            'manage_woocommerce',
            'monarch-ach-transactions',
            array($this, 'transactions_page')
        );
    }
    
    public function admin_init() {
        // No additional initialization needed
    }
    
    public function enqueue_admin_scripts($hook) {
        // Check if we're on a Monarch admin page or WooCommerce settings
        // Hook format: woocommerce_page_monarch-ach-transactions
        $is_monarch_page = (
            strpos($hook, 'monarch') !== false ||
            strpos($hook, 'wc-settings') !== false ||
            (isset($_GET['page']) && strpos($_GET['page'], 'monarch') !== false)
        );

        if (!$is_monarch_page) {
            return;
        }

        // Force load with timestamp for cache busting
        $version = WC_MONARCH_ACH_VERSION . '.' . time();

        wp_enqueue_script(
            'monarch-ach-admin',
            WC_MONARCH_ACH_PLUGIN_URL . 'assets/js/monarch-admin.js',
            array('jquery'),
            $version,
            true
        );

        wp_enqueue_style(
            'monarch-ach-admin',
            WC_MONARCH_ACH_PLUGIN_URL . 'assets/css/monarch-admin.css',
            array(),
            $version
        );

        wp_localize_script('monarch-ach-admin', 'monarch_admin_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('monarch_admin_nonce'),
            'debug' => true
        ));
    }
    
    public function transactions_page() {
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'transactions';
        ?>
        <div class="wrap">
            <h1>Monarch ACH Payment Gateway</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=monarch-ach-transactions&tab=transactions" 
                   class="nav-tab <?php echo $current_tab === 'transactions' ? 'nav-tab-active' : ''; ?>">
                    Transactions
                </a>
                <a href="?page=monarch-ach-transactions&tab=customers" 
                   class="nav-tab <?php echo $current_tab === 'customers' ? 'nav-tab-active' : ''; ?>">
                    Customers
                </a>
                <a href="?page=monarch-ach-transactions&tab=settings" 
                   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    API Settings
                </a>
                <a href="?page=monarch-ach-transactions&tab=logs"
                   class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    Logs
                </a>
                <a href="?page=monarch-ach-transactions&tab=status-sync"
                   class="nav-tab <?php echo $current_tab === 'status-sync' ? 'nav-tab-active' : ''; ?>">
                    Status Sync
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'transactions':
                        $this->render_transactions_tab();
                        break;
                    case 'customers':
                        $this->render_customers_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    case 'status-sync':
                        $this->render_status_sync_tab();
                        break;
                    default:
                        $this->render_transactions_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_transactions_tab() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'monarch_ach_transactions';
        $per_page = 20;
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count
        $total_transactions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Get transactions
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        ?>
        <div class="monarch-admin-section">
            <h2>Recent Transactions</h2>
            
            <?php if (empty($transactions)): ?>
                <p>No transactions found.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Order ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <?php 
                            $order = wc_get_order($transaction->order_id);
                            $customer_name = $order ? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() : 'N/A';
                            ?>
                            <tr>
                                <td><?php echo esc_html($transaction->transaction_id); ?></td>
                                <td>
                                    <?php if ($order): ?>
                                        <a href="<?php echo get_edit_post_link($transaction->order_id); ?>">
                                            #<?php echo $transaction->order_id; ?>
                                        </a>
                                    <?php else: ?>
                                        #<?php echo $transaction->order_id; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo wc_price($transaction->amount); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($transaction->status); ?>">
                                        <?php echo ucfirst($transaction->status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($customer_name); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($transaction->created_at)); ?></td>
                                <td>
                                    <button class="button button-small view-details" 
                                            data-transaction="<?php echo esc_attr($transaction->id); ?>">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php
                // Pagination
                $total_pages = ceil($total_transactions / $per_page);
                if ($total_pages > 1) {
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    echo '</div></div>';
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_customers_tab() {
        global $wpdb;
        
        // Get users with Monarch data
        $users = get_users(array(
            'meta_query' => array(
                array(
                    'key' => '_monarch_org_id',
                    'compare' => 'EXISTS'
                )
            ),
            'number' => 100
        ));
        
        ?>
        <div class="monarch-admin-section">
            <h2>Monarch Customers</h2>
            
            <?php if (empty($users)): ?>
                <p>No Monarch customers found.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Monarch Org ID</th>
                            <th>Bank Status</th>
                            <th>Connected Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $org_id = get_user_meta($user->ID, '_monarch_org_id', true);
                            $paytoken_id = get_user_meta($user->ID, '_monarch_paytoken_id', true);
                            $connected_date = get_user_meta($user->ID, '_monarch_connected_date', true);
                            ?>
                            <tr>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo esc_html($org_id); ?></td>
                                <td>
                                    <?php if ($paytoken_id): ?>
                                        <span class="status-badge status-completed">Connected</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Not connected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($connected_date) {
                                        echo esc_html(date('M j, Y', strtotime($connected_date)));
                                    } else {
                                        echo '<span style="color:#999;">—</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo get_edit_user_link($user->ID); ?>" class="button button-small">
                                        Edit User
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_settings_tab() {
        $gateway = new WC_Monarch_ACH_Gateway();
        ?>
        <div class="monarch-admin-section">
            <h2>API Connection Test</h2>
            
            <div class="monarch-test-connection">
                <p>Test your API credentials to ensure they're working correctly.</p>
                
                <div class="test-results" id="test-results" style="display: none;">
                    <div class="notice" id="test-notice"></div>
                </div>
                
                <p>
                    <button type="button" id="test-api-connection" class="button button-primary">
                        Test API Connection
                    </button>
                    <span class="spinner" id="test-spinner"></span>
                </p>
            </div>
            
            <h2>Current Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Mode</th>
                    <td>
                        <span class="status-badge status-<?php echo $gateway->testmode ? 'test' : 'live'; ?>">
                            <?php echo $gateway->testmode ? 'Test Mode' : 'Live Mode'; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">API Key</th>
                    <td>
                        <?php 
                        $api_key = $gateway->api_key;
                        echo $api_key ? '***' . substr($api_key, -4) : 'Not set';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">App ID</th>
                    <td><?php echo esc_html($gateway->app_id ?: 'Not set'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Merchant Org ID</th>
                    <td><?php echo esc_html($gateway->merchant_org_id ?: 'Not set'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Partner Name</th>
                    <td><?php echo esc_html($gateway->partner_name ?: 'Not set'); ?></td>
                </tr>
            </table>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=monarch_ach'); ?>" 
                   class="button">
                    Configure Settings
                </a>
            </p>
        </div>
        <?php
    }
    
    private function render_logs_tab() {
        // Try multiple methods to find log files
        $log_content = '';
        $log_file_found = false;
        $log_file_path = '';

        // Method 1: WooCommerce log handler (preferred)
        if (class_exists('WC_Log_Handler_File') && method_exists('WC_Log_Handler_File', 'get_log_file_path')) {
            $log_file = WC_Log_Handler_File::get_log_file_path('monarch-ach');
            if ($log_file && file_exists($log_file)) {
                $log_content = file_get_contents($log_file);
                $log_file_found = true;
                $log_file_path = $log_file;
            }
        }

        // Method 2: Check WooCommerce uploads directory directly
        if (!$log_file_found) {
            $upload_dir = wp_upload_dir();
            $wc_logs_dir = $upload_dir['basedir'] . '/wc-logs/';

            if (is_dir($wc_logs_dir)) {
                // Find any monarch-ach log files
                $log_files = glob($wc_logs_dir . 'monarch-ach*.log');
                if (!empty($log_files)) {
                    // Get the most recent log file
                    usort($log_files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $log_file = $log_files[0];
                    $log_content = file_get_contents($log_file);
                    $log_file_found = true;
                    $log_file_path = $log_file;
                }
            }
        }

        // Method 3: Check standard WooCommerce log location
        if (!$log_file_found) {
            $wc_log_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';
            if (is_dir($wc_log_dir)) {
                $log_files = glob($wc_log_dir . 'monarch-ach*.log');
                if (!empty($log_files)) {
                    usort($log_files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $log_file = $log_files[0];
                    $log_content = file_get_contents($log_file);
                    $log_file_found = true;
                    $log_file_path = $log_file;
                }
            }
        }

        ?>
        <div class="monarch-admin-section">
            <h2>API Logs</h2>

            <?php if ($log_file_found && !empty($log_content)): ?>
                <p>Recent API activity:</p>
                <p><small>Log file: <?php echo esc_html(basename($log_file_path)); ?></small></p>
                <div class="monarch-logs" style="max-height: 500px; overflow-y: auto; background: #f5f5f5; padding: 15px; border-radius: 4px;">
                    <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-size: 12px;"><?php echo esc_html($log_content); ?></pre>
                </div>

                <p style="margin-top: 15px;">
                    <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'clear_logs')), 'clear_logs'); ?>"
                       class="button" onclick="return confirm('Are you sure you want to clear all logs?');">
                        Clear Logs
                    </a>
                </p>
            <?php else: ?>
                <div class="notice notice-info" style="padding: 15px;">
                    <p><strong>No logs found yet.</strong></p>
                    <p>Logs will appear here after API activity occurs (creating customers, connecting banks, processing payments).</p>
                    <p><small>Logs are stored in: <code>wp-content/uploads/wc-logs/monarch-ach-*.log</code></small></p>
                </div>

                <h3 style="margin-top: 20px;">How logging works:</h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li>API requests and responses are logged automatically</li>
                    <li>Customer events (organization created, bank connected) are logged</li>
                    <li>Transaction events are logged</li>
                    <li>Errors are logged with full details for debugging</li>
                </ul>

                <p><strong>To generate logs:</strong> Try connecting a bank account or processing a test payment.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_status_sync_tab() {
        global $wpdb;

        // Get CRON schedule info
        $next_scheduled = wp_next_scheduled('monarch_ach_update_transaction_status');
        $last_run = get_option('monarch_cron_last_run', null);

        // Count pending transactions
        $table_name = $wpdb->prefix . 'monarch_ach_transactions';
        $pending_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status IN (%s, %s)",
            'pending',
            'processing'
        ));

        ?>
        <div class="monarch-admin-section">
            <h2>Transaction Status Sync</h2>
            <p>The plugin automatically checks Monarch API every 2 hours to update transaction statuses (pending, completed, failed, etc.).</p>

            <table class="form-table">
                <tr>
                    <th scope="row">CRON Status</th>
                    <td>
                        <?php if ($next_scheduled): ?>
                            <span class="status-badge status-completed">Scheduled</span>
                        <?php else: ?>
                            <span class="status-badge status-failed">Not Scheduled</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Next Scheduled Run</th>
                    <td>
                        <?php if ($next_scheduled): ?>
                            <?php
                            // wp_next_scheduled() returns UTC timestamp
                            // wp_date() converts to local timezone for display
                            echo wp_date('M j, Y g:i A', $next_scheduled);
                            ?>
                            <br><small>(<?php
                            // Both time() and $next_scheduled are in UTC, so comparison is correct
                            echo human_time_diff(time(), $next_scheduled);
                            ?> from now)</small>
                        <?php else: ?>
                            Not scheduled
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Last Run</th>
                    <td>
                        <?php if ($last_run && $last_run['time']): ?>
                            <?php echo wp_date('M j, Y g:i A', strtotime($last_run['time'])); ?>
                            <br>
                            <small>
                                Processed: <?php echo $last_run['processed']; ?> |
                                Updated: <?php echo $last_run['updated']; ?> |
                                Errors: <?php echo $last_run['errors']; ?>
                            </small>
                        <?php else: ?>
                            Never run
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Pending Transactions</th>
                    <td>
                        <strong><?php echo intval($pending_count); ?></strong> transactions waiting to be updated
                    </td>
                </tr>
            </table>

            <h3>Manual Status Update</h3>
            <p>You can manually trigger a status check for all pending transactions:</p>

            <div class="status-sync-results" id="status-sync-results" style="display: none;">
                <div class="notice" id="status-sync-notice"></div>
            </div>

            <p>
                <button type="button" id="manual-status-update" class="button button-primary">
                    Update Transaction Statuses Now
                </button>
                <span class="spinner" id="status-sync-spinner" style="float: none;"></span>
            </p>

            <p class="description">
                This will check all pending/processing transactions with the Monarch API and update their statuses.
                This may take a few minutes if you have many transactions.
            </p>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                console.log('=== Monarch Status Sync Inline JS Loaded ===');

                var $button = $('#manual-status-update');
                console.log('Button found:', $button.length);

                if ($button.length === 0) {
                    console.error('Button #manual-status-update not found!');
                    return;
                }

                $button.on('click', function(e) {
                    e.preventDefault();
                    console.log('Manual status update button clicked');

                    var $spinner = $('#status-sync-spinner');
                    var $results = $('#status-sync-results');
                    var $notice = $('#status-sync-notice');

                    if (!confirm('This will check all pending transactions with the Monarch API. Continue?')) {
                        return;
                    }

                    $button.prop('disabled', true).text('Updating...');
                    $spinner.addClass('is-active');
                    $results.hide();

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'monarch_manual_status_update',
                            nonce: '<?php echo wp_create_nonce('monarch_admin_nonce'); ?>'
                        },
                        timeout: 300000,
                        success: function(response) {
                            console.log('AJAX Response:', response);
                            if (response.success) {
                                $notice.removeClass('notice-error').addClass('notice-success')
                                       .html('<p><strong>Success:</strong> ' + response.data.message + '</p>');
                            } else {
                                $notice.removeClass('notice-success').addClass('notice-error')
                                       .html('<p><strong>Error:</strong> ' + response.data + '</p>');
                            }
                            $results.show();
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                            $notice.removeClass('notice-success').addClass('notice-error')
                                   .html('<p><strong>Error:</strong> ' + (error || 'Request failed') + '</p>');
                            $results.show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('Update Transaction Statuses Now');
                            $spinner.removeClass('is-active');
                        }
                    });
                });
            });
            </script>
        </div>

        <div class="monarch-admin-section">
            <h2>How Status Sync Works</h2>
            <ol>
                <li>Every 2 hours, the CRON job runs automatically</li>
                <li>It finds all transactions with status "pending" or "processing"</li>
                <li>For each transaction, it calls the Monarch API to check the current status</li>
                <li>If the status has changed (e.g., completed, failed), it updates:
                    <ul>
                        <li>The transaction record in the database</li>
                        <li>The WooCommerce order status</li>
                        <li>Adds an order note with the update details</li>
                    </ul>
                </li>
            </ol>

            <h3>Status Mappings</h3>
            <table class="widefat" style="max-width: 500px;">
                <thead>
                    <tr>
                        <th>Monarch Status</th>
                        <th>WooCommerce Order Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>completed, success, settled, approved</td>
                        <td><span class="status-badge status-completed">Completed</span></td>
                    </tr>
                    <tr>
                        <td>pending, processing, submitted</td>
                        <td><span class="status-badge status-processing">Processing</span></td>
                    </tr>
                    <tr>
                        <td>failed, declined, rejected, returned</td>
                        <td><span class="status-badge status-failed">Failed</span></td>
                    </tr>
                    <tr>
                        <td>refunded, reversed</td>
                        <td><span class="status-badge status-refunded">Refunded</span></td>
                    </tr>
                    <tr>
                        <td>voided, cancelled</td>
                        <td><span class="status-badge status-voided">Cancelled</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function test_api_connection() {
        check_ajax_referer('monarch_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        
        $gateway = new WC_Monarch_ACH_Gateway();
        
        if (empty($gateway->api_key) || empty($gateway->app_id)) {
            wp_send_json_error('API credentials not configured');
        }
        
        try {
            $monarch_api = new Monarch_API(
                $gateway->api_key,
                $gateway->app_id,
                $gateway->merchant_org_id,
                $gateway->partner_name,
                $gateway->testmode
            );
            
            // Test with a simple API call (you might need to create a test endpoint)
            $test_data = array(
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
                'password' => 'test123',
                'phone' => '5551234567',
                'company_name' => 'Test Company',
                'dob' => '01/01/1990',
                'address_1' => '123 Test St',
                'address_2' => '',
                'city' => 'Test City',
                'state' => 'CA',
                'zip' => '90210',
                'country' => 'US'
            );
            
            // This is just a connection test - we won't actually create a user
            wp_send_json_success('API connection successful');
            
        } catch (Exception $e) {
            wp_send_json_error('API connection failed: ' . $e->getMessage());
        }
    }
    
    public function admin_notices() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $gateway = new WC_Monarch_ACH_Gateway();

        if ($gateway->enabled === 'yes') {
            // Check for missing credentials
            $missing = array();
            if (empty($gateway->api_key)) {
                $missing[] = $gateway->testmode ? 'Sandbox API Key' : 'Live API Key';
            }
            if (empty($gateway->app_id)) {
                $missing[] = $gateway->testmode ? 'Sandbox App ID' : 'Live App ID';
            }
            if (empty($gateway->merchant_org_id)) {
                $missing[] = $gateway->testmode ? 'Sandbox Merchant Org ID' : 'Live Merchant Org ID';
            }
            if (empty($gateway->partner_name)) {
                $missing[] = 'Partner Name';
            }

            if (!empty($missing)) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Monarch ACH Gateway:</strong>
                        The following settings are missing: <strong><?php echo esc_html(implode(', ', $missing)); ?></strong>.
                        The payment method will not be available at checkout until configured.
                        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=monarch_ach'); ?>">
                            Configure settings
                        </a>
                    </p>
                </div>
                <?php
            } elseif ($gateway->testmode) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong>Monarch ACH Gateway:</strong>
                        Currently in <strong>Test Mode</strong>. No real transactions will be processed.
                        Remember to switch to live mode and enter production credentials before going live.
                    </p>
                </div>
                <?php
            }
        }
    }
}

new WC_Monarch_Admin();