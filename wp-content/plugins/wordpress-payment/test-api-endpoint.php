<?php
/**
 * Temporary test file to check the new Monarch API endpoint
 * This will help us see what the API actually returns
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>Monarch API Endpoint Test</h1>";
echo "<p>Testing the /transaction/status/ endpoint</p>";
echo "<hr>";

// Get gateway settings
$gateway = new WC_Monarch_ACH_Gateway();

echo "<h2>Gateway Settings:</h2>";
echo "API Key: " . (empty($gateway->api_key) ? '<span style="color:red;">MISSING</span>' : '<span style="color:green;">Set (ending: ****' . substr($gateway->api_key, -4) . ')</span>') . "<br>";
echo "App ID: " . (empty($gateway->app_id) ? '<span style="color:red;">MISSING</span>' : '<span style="color:green;">Set (ending: ****' . substr($gateway->app_id, -4) . ')</span>') . "<br>";
echo "Merchant Org ID: " . ($gateway->merchant_org_id ?? '<span style="color:red;">MISSING</span>') . "<br>";
echo "Test Mode: " . ($gateway->testmode ? '<span style="color:orange;">Yes (Sandbox)</span>' : '<span style="color:green;">No (Production)</span>') . "<br>";
echo "Base URL: " . ($gateway->testmode ? 'https://devapi.monarch.is/v1' : 'https://api.monarch.is/v1') . "<br>";

echo "<hr>";

// Get a pending transaction from database
global $wpdb;
$table_name = $wpdb->prefix . 'monarch_ach_transactions';
$transaction = $wpdb->get_row("SELECT * FROM $table_name WHERE status IN ('pending', 'processing') LIMIT 1");

if (!$transaction) {
    echo "<p style='color:orange;'>No pending transactions found in database to test with.</p>";
    echo "<p>Create a test transaction first, then run this test again.</p>";
    exit;
}

echo "<h2>Test Transaction:</h2>";
echo "Transaction ID: <strong>" . esc_html($transaction->transaction_id) . "</strong><br>";
echo "Order ID: #" . esc_html($transaction->order_id) . "<br>";
echo "Status: " . esc_html($transaction->status) . "<br>";
echo "Amount: $" . esc_html($transaction->amount) . "<br>";

echo "<hr>";
echo "<h2>API Request Test:</h2>";

// Create API instance
$monarch_api = new Monarch_API(
    $gateway->api_key,
    $gateway->app_id,
    $gateway->merchant_org_id,
    $gateway->partner_name,
    $gateway->testmode
);

// Test the endpoint
echo "<p>Calling: <code>GET /v1/transaction/status/" . esc_html($transaction->transaction_id) . "</code></p>";

$result = $monarch_api->get_transaction_status($transaction->transaction_id);

echo "<h3>Result:</h3>";
echo "<pre style='background:#f5f5f5; padding:15px; border:1px solid #ddd; overflow-x:auto;'>";

if ($result['success']) {
    echo "<span style='color:green; font-weight:bold;'>✓ SUCCESS</span>\n\n";
    echo "Full Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);

    echo "\n\n";
    echo "--- Extracting Status ---\n";
    $response_data = $result['data'] ?? array();

    echo "Looking for status in:\n";
    echo "- \$result['data']['status']: " . ($response_data['status'] ?? 'NOT FOUND') . "\n";
    echo "- \$result['data']['transactionStatus']: " . ($response_data['transactionStatus'] ?? 'NOT FOUND') . "\n";
    echo "- \$result['data']['transaction']['status']: " . ($response_data['transaction']['status'] ?? 'NOT FOUND') . "\n";
    echo "- \$result['data']['transaction_status']: " . ($response_data['transaction_status'] ?? 'NOT FOUND') . "\n";

} else {
    echo "<span style='color:red; font-weight:bold;'>✗ ERROR</span>\n\n";
    echo "Error Message: " . ($result['error'] ?? 'Unknown') . "\n";
    echo "HTTP Status Code: " . ($result['status_code'] ?? 'N/A') . "\n\n";
    echo "Full Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
}

echo "</pre>";

echo "<hr>";
echo "<p><a href='?'>Run test again</a> | <a href='admin.php?page=monarch-ach-transactions'>Back to Transactions</a></p>";
