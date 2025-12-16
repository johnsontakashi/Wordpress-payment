<?php
/**
 * Test file for the getLatestPayToken API endpoint
 * This tests the complete embedded bank linking flow
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>Monarch getLatestPayToken API Test</h1>";
echo "<hr>";

// Get gateway settings
$gateway = new WC_Monarch_ACH_Gateway();

echo "<h2>Gateway Settings:</h2>";
echo "API Key: " . (empty($gateway->api_key) ? '<span style="color:red;">MISSING</span>' : '<span style="color:green;">Set</span>') . "<br>";
echo "App ID: " . (empty($gateway->app_id) ? '<span style="color:red;">MISSING</span>' : '<span style="color:green;">Set</span>') . "<br>";
echo "Merchant Org ID: " . ($gateway->merchant_org_id ?? '<span style="color:red;">MISSING</span>') . "<br>";
echo "Test Mode: " . ($gateway->testmode ? '<span style="color:orange;">Yes (Sandbox)</span>' : '<span style="color:green;">No (Production)</span>') . "<br>";

echo "<hr>";
echo "<h2>Step 1: Creating Test Organization...</h2>";

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

echo "<pre style='background:#f5f5f5; padding:15px; border:1px solid #ddd; overflow-x:auto;'>";

if ($result['success']) {
    echo "<span style='color:green; font-weight:bold;'>✓ Organization Created Successfully</span>\n\n";

    $data = $result['data'] ?? array();
    $org_id = $data['organizationId'] ?? $data['_id'] ?? null;
    $embedded_url = $data['partner_embedded_url'] ?? null;

    echo "Organization ID: " . ($org_id ?? 'NOT FOUND') . "\n";
    echo "Embedded URL: " . ($embedded_url ? "Found" : "NOT FOUND") . "\n\n";

    if ($org_id) {
        echo "--- Embedded Bank Linking Flow ---\n\n";
        echo "STEP 1: ✓ Organization created\n";
        echo "STEP 2: User would now link their bank through the embedded URL\n";
        echo "STEP 3: After linking, we call getLatestPayToken...\n\n";

        echo "<hr style='margin: 20px 0;'>\n\n";
        echo "<h2>Step 2: Testing getLatestPayToken API</h2>\n\n";

        // Test getLatestPayToken
        $paytoken_result = $monarch_api->get_latest_paytoken($org_id);

        echo "API Call: GET /v1/getlatestpaytoken/$org_id\n\n";

        if ($paytoken_result['success']) {
            echo "<span style='color:green; font-weight:bold;'>✓ SUCCESS</span>\n\n";

            $paytoken_data = $paytoken_result['data'] ?? array();
            $paytoken_id = $paytoken_data['_id'] ?? $paytoken_data['payTokenId'] ?? $paytoken_data['paytoken_id'] ?? null;

            echo "Response Data:\n";
            print_r($paytoken_data);

            echo "\n\nExtracted PayToken ID: " . ($paytoken_id ?? 'NOT FOUND') . "\n";

            if ($paytoken_id) {
                echo "\n<span style='color:green; font-weight:bold;'>✓ COMPLETE: PayToken retrieved successfully!</span>\n";
                echo "\nThis paytoken can now be used for transactions.\n";
            } else {
                echo "\n<span style='color:orange; font-weight:bold;'>⚠ No paytoken found</span>\n";
                echo "This is expected if no bank account has been linked yet.\n";
                echo "In the real flow, the user would link their bank first.\n";
            }

        } else {
            echo "<span style='color:red; font-weight:bold;'>✗ ERROR</span>\n\n";
            echo "Error Message: " . ($paytoken_result['error'] ?? 'Unknown') . "\n";
            echo "HTTP Status Code: " . ($paytoken_result['status_code'] ?? 'N/A') . "\n\n";

            if (isset($paytoken_result['status_code']) && $paytoken_result['status_code'] == 404) {
                echo "<span style='color:orange;'>Note: 404 is expected if no bank account has been linked to this organization yet.</span>\n";
            }

            echo "\nFull Response:\n";
            print_r($paytoken_result);
        }

    } else {
        echo "<span style='color:red;'>✗ Failed to get organization ID</span>\n";
    }

} else {
    echo "<span style='color:red; font-weight:bold;'>✗ Organization Creation Failed</span>\n\n";
    echo "Error Message: " . ($result['error'] ?? 'Unknown') . "\n";
    echo "HTTP Status Code: " . ($result['status_code'] ?? 'N/A') . "\n\n";
    echo "Full Response:\n";
    print_r($result);
}

echo "</pre>";

echo "<hr>";
echo "<h2>Testing Summary</h2>";
echo "<p>This test demonstrates the complete embedded bank linking flow:</p>";
echo "<ol>";
echo "<li><strong>POST /v1/organization</strong> - Creates organization and returns embedded URL</li>";
echo "<li><strong>User links bank</strong> - Through the embedded URL in iframe (manual step)</li>";
echo "<li><strong>GET /v1/getlatestpaytoken/[orgId]</strong> - Retrieves the paytoken after bank linking</li>";
echo "<li><strong>POST /v1/transaction/sale</strong> - Process payment with paytoken (not tested here)</li>";
echo "</ol>";

echo "<p><strong>Expected Behavior:</strong></p>";
echo "<ul>";
echo "<li>If no bank is linked: getLatestPayToken returns 404 or empty paytoken</li>";
echo "<li>After bank linking: getLatestPayToken returns the paytoken ID</li>";
echo "<li>Frontend calls this API when user clicks 'I've Connected My Bank' button</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='?'>Run test again</a> | <a href='/wp-admin/admin.php?page=wc-settings&tab=checkout&section=monarch_ach'>Gateway Settings</a></p>";
