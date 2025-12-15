<?php
/**
 * Test file to inspect the actual bank linking URL format from Monarch API
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>Monarch Bank Linking URL Test</h1>";
echo "<hr>";

// Get gateway settings
$gateway = new WC_Monarch_ACH_Gateway();

echo "<h2>Gateway Settings:</h2>";
echo "API Key: " . (empty($gateway->api_key) ? '<span style="color:red;">MISSING</span>' : '<span style="color:green;">Set</span>') . "<br>";
echo "App ID: " . (empty($gateway->app_id) ? '<span style="color:red;">MISSING</span>' : '<span style="color:green;">Set</span>') . "<br>";
echo "Merchant Org ID: " . ($gateway->merchant_org_id ?? '<span style="color:red;">MISSING</span>') . "<br>";
echo "Test Mode: " . ($gateway->testmode ? '<span style="color:orange;">Yes (Sandbox)</span>' : '<span style="color:green;">No (Production)</span>') . "<br>";

echo "<hr>";
echo "<h2>Creating Test Organization...</h2>";

// Create test organization
$monarch_api = new Monarch_API(
    $gateway->api_key,
    $gateway->app_id,
    $gateway->merchant_org_id,
    $gateway->partner_name,
    $gateway->testmode
);

$test_customer_data = array(
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'email' => 'test' . time() . '@example.com',
    'password' => 'TestPassword123!',
    'phone' => '1234567890',
    'company_name' => 'Test Company',
    'dob' => '1990-01-01',
    'address_1' => '123 Test St',
    'address_2' => '',
    'city' => 'Test City',
    'state' => 'CA',
    'zip' => '12345',
    'country' => 'US'
);

$result = $monarch_api->create_organization($test_customer_data);

echo "<h3>API Response:</h3>";
echo "<pre style='background:#f5f5f5; padding:15px; border:1px solid #ddd; overflow-x:auto;'>";

if ($result['success']) {
    echo "<span style='color:green; font-weight:bold;'>✓ SUCCESS</span>\n\n";
    echo "Full Response:\n";
    print_r($result);

    echo "\n\n--- Analyzing Bank Linking URL ---\n";

    $data = $result['data'] ?? array();

    // Check all possible URL fields
    $url_fields = array(
        'partner_embedded_url',
        'bankLinkingUrl',
        'connectionUrl',
        'embedded_url',
        'link_url'
    );

    foreach ($url_fields as $field) {
        if (isset($data[$field])) {
            echo "\nFound URL in field: $field\n";
            echo "Raw value: " . $data[$field] . "\n";
            echo "URL-decoded: " . urldecode($data[$field]) . "\n";

            $url = $data[$field];

            // Analyze URL structure
            if (strpos($url, '{redirectUrl}') !== false) {
                echo "⚠ Contains {redirectUrl} placeholder\n";
            }
            if (strpos($url, '{price}') !== false) {
                echo "⚠ Contains {price} placeholder\n";
            }

            $hash_count = substr_count($url, '#');
            if ($hash_count > 1) {
                echo "⚠ Contains multiple hash fragments ($hash_count total)\n";
            }

            echo "\n";
        }
    }

} else {
    echo "<span style='color:red; font-weight:bold;'>✗ ERROR</span>\n\n";
    echo "Error Message: " . ($result['error'] ?? 'Unknown') . "\n";
    echo "HTTP Status Code: " . ($result['status_code'] ?? 'N/A') . "\n\n";
    echo "Full Response:\n";
    print_r($result);
}

echo "</pre>";
