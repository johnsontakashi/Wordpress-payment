<?php
/**
 * Plugin Name: WooCommerce Monarch ACH Payment Gateway
 * Description: Custom WooCommerce payment gateway for ACH transactions via Monarch API
 * Version: 1.0.0
 * Author: Your Company
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

define('WC_MONARCH_ACH_VERSION', '1.0.3');
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
        add_action('wp_ajax_monarch_manual_bank_entry', array($this, 'ajax_manual_bank_entry'));
        add_action('wp_ajax_nopriv_monarch_manual_bank_entry', array($this, 'ajax_manual_bank_entry'));
        // CRON manual status update handler
        add_action('wp_ajax_monarch_manual_status_update', array($this, 'ajax_manual_status_update'));
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