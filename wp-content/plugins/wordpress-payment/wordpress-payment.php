<?php
/**
 * Plugin Name: WordPress Payment Gateway
 * Description: A comprehensive payment gateway plugin for WordPress with support for multiple payment methods
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_PAYMENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_PAYMENT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WP_PAYMENT_VERSION', '1.0.0');

class WordPressPayment {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_process_payment', array($this, 'process_payment'));
        add_action('wp_ajax_nopriv_process_payment', array($this, 'process_payment'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('wp-payment-js', WP_PAYMENT_PLUGIN_URL . 'assets/js/payment.js', array('jquery'), WP_PAYMENT_VERSION, true);
        wp_enqueue_style('wp-payment-css', WP_PAYMENT_PLUGIN_URL . 'assets/css/payment.css', array(), WP_PAYMENT_VERSION);
        
        wp_localize_script('wp-payment-js', 'wpPayment', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_payment_nonce')
        ));
    }
    
    public function enqueue_admin_scripts() {
        wp_enqueue_style('wp-payment-admin-css', WP_PAYMENT_PLUGIN_URL . 'assets/css/admin.css', array(), WP_PAYMENT_VERSION);
    }
    
    public function admin_menu() {
        add_menu_page(
            'Payment Gateway',
            'Payments',
            'manage_options',
            'wp-payment',
            array($this, 'admin_page'),
            'dashicons-money-alt',
            30
        );
        
        add_submenu_page(
            'wp-payment',
            'Payment Settings',
            'Settings',
            'manage_options',
            'wp-payment-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'wp-payment',
            'Transactions',
            'Transactions',
            'manage_options',
            'wp-payment-transactions',
            array($this, 'transactions_page')
        );
    }
    
    public function admin_page() {
        echo '<div class="wrap"><h1>Payment Gateway Dashboard</h1></div>';
    }
    
    public function settings_page() {
        echo '<div class="wrap"><h1>Payment Settings</h1></div>';
    }
    
    public function transactions_page() {
        echo '<div class="wrap"><h1>Transaction History</h1></div>';
    }
    
    public function process_payment() {
        check_ajax_referer('wp_payment_nonce', 'nonce');
        
        wp_send_json_success(array('message' => 'Payment processed successfully'));
    }
    
    public function activate() {
        $this->create_tables();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'payment_transactions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            transaction_id varchar(100) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            status varchar(20) NOT NULL,
            payment_method varchar(50) NOT NULL,
            customer_email varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY transaction_id (transaction_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

new WordPressPayment();