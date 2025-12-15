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
                            <th>PayToken ID</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php 
                            $org_id = get_user_meta($user->ID, '_monarch_org_id', true);
                            $paytoken_id = get_user_meta($user->ID, '_monarch_paytoken_id', true);
                            ?>
                            <tr>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td><?php echo esc_html($org_id); ?></td>
                                <td><?php echo esc_html($paytoken_id ?: 'Not connected'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user->user_registered)); ?></td>
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
        $log_file = WC_Log_Handler_File::get_log_file_path('monarch-ach');

        ?>
        <div class="monarch-admin-section">
            <h2>API Logs</h2>

            <?php if (file_exists($log_file)): ?>
                <p>Recent API activity:</p>
                <div class="monarch-logs">
                    <pre><?php echo esc_html(file_get_contents($log_file)); ?></pre>
                </div>

                <p>
                    <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'clear_logs')), 'clear_logs'); ?>"
                       class="button" onclick="return confirm('Are you sure you want to clear all logs?');">
                        Clear Logs
                    </a>
                </p>
            <?php else: ?>
                <p>No logs found.</p>
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
                            <?php echo date('M j, Y g:i A', $next_scheduled); ?>
                            <br><small>(<?php echo human_time_diff($next_scheduled); ?> from now)</small>
                        <?php else: ?>
                            Not scheduled
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Last Run</th>
                    <td>
                        <?php if ($last_run && $last_run['time']): ?>
                            <?php echo date('M j, Y g:i A', strtotime($last_run['time'])); ?>
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