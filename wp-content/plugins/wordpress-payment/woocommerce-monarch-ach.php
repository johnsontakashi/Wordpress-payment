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

define('WC_MONARCH_ACH_VERSION', '1.0.0');
define('WC_MONARCH_ACH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_MONARCH_ACH_PLUGIN_URL', plugin_dir_url(__FILE__));

class WC_Monarch_ACH_Gateway_Plugin {
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        
        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-logger.php';
        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-monarch-api.php';
        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-ach-gateway.php';
        include_once WC_MONARCH_ACH_PLUGIN_PATH . 'includes/class-wc-monarch-admin.php';
    }
    
    public function add_gateway_class($gateways) {
        $gateways[] = 'WC_Monarch_ACH_Gateway';
        return $gateways;
    }
    
    public function enqueue_scripts() {
        if (is_checkout() || is_account_page()) {
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
                'test_mode' => get_option('woocommerce_monarch_ach_settings')['testmode'] ?? 'yes'
            ));
        }
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
    }
}

new WC_Monarch_ACH_Gateway_Plugin();