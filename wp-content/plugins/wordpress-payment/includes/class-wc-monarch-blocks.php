<?php
/**
 * WooCommerce Blocks Integration for Monarch ACH Payment Gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Monarch ACH Blocks Integration
 */
final class WC_Monarch_ACH_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * Payment method name
     * @var string
     */
    protected $name = 'monarch_ach';

    /**
     * Gateway instance
     * @var WC_Monarch_ACH_Gateway
     */
    private $gateway;

    /**
     * Initialize the payment method type
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_monarch_ach_settings', []);
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways[$this->name]) ? $gateways[$this->name] : null;
    }

    /**
     * Check if the payment method is active
     * @return bool
     */
    public function is_active() {
        if (!$this->gateway) {
            return false;
        }
        return $this->gateway->is_available();
    }

    /**
     * Register scripts for the payment method
     * @return array
     */
    public function get_payment_method_script_handles() {
        $script_path = WC_MONARCH_ACH_PLUGIN_URL . 'assets/js/blocks/monarch-ach-block.js';
        $script_asset = [
            'dependencies' => ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-data', 'jquery'],
            'version' => WC_MONARCH_ACH_VERSION
        ];

        wp_register_script(
            'wc-monarch-ach-blocks',
            $script_path,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        return ['wc-monarch-ach-blocks'];
    }

    /**
     * Get payment method data to pass to the frontend
     * @return array
     */
    public function get_payment_method_data() {
        $customer_id = get_current_user_id();
        $has_saved_bank = false;
        $saved_org_id = '';
        $saved_paytoken_id = '';

        if ($customer_id) {
            $saved_org_id = get_user_meta($customer_id, '_monarch_org_id', true);
            $saved_paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);
            $has_saved_bank = !empty($saved_org_id) && !empty($saved_paytoken_id);
        }

        return [
            'title' => $this->get_setting('title', 'ACH Bank Transfer'),
            'description' => $this->get_setting('description', 'Pay securely using your bank account via ACH transfer.'),
            'supports' => $this->get_supported_features(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('monarch_ach_nonce'),
            'test_mode' => 'yes' === $this->get_setting('testmode', 'yes'),
            'has_saved_bank' => $has_saved_bank,
            'saved_org_id' => $saved_org_id,
            'saved_paytoken_id' => $saved_paytoken_id
        ];
    }

    /**
     * Get supported features
     * @return array
     */
    public function get_supported_features() {
        return $this->gateway ? $this->gateway->supports : ['products'];
    }
}
