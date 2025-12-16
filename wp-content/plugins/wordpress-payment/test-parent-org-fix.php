<?php
/**
 * Quick test to verify parentOrgId fix is working
 */

// Load WordPress - try different path formats for cross-platform compatibility
$wp_load_paths = array(
    __DIR__ . '/../../../../wp-load.php',  // Linux/Mac
    dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php',  // Alternative
    $_SERVER['DOCUMENT_ROOT'] . '/payment/wp-load.php',  // Windows XAMPP
);

$loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die('Could not find wp-load.php. Tried paths: ' . implode(', ', $wp_load_paths));
}

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>Parent Org ID Fix Verification</h1>";
echo "<hr>";

// Get gateway settings
$gateway = new WC_Monarch_ACH_Gateway();

echo "<h2>Merchant Settings:</h2>";
echo "<pre style='background:#f5f5f5; padding:15px; border:1px solid #ddd;'>";
echo "Merchant Org ID: " . $gateway->merchant_org_id . "\n";
echo "API Key (last 4): " . substr($gateway->api_key, -4) . "\n";
echo "App ID (last 4): " . substr($gateway->app_id, -4) . "\n";
echo "Test Mode: " . ($gateway->testmode ? 'Yes (Sandbox)' : 'No (Production)') . "\n";
echo "</pre>";

echo "<hr>";
echo "<h2>Testing Organization Creation with Correct Parent...</h2>";

// Create test organization
$monarch_api = new Monarch_API(
    $gateway->api_key,
    $gateway->app_id,
    $gateway->merchant_org_id,
    $gateway->partner_name,
    $gateway->testmode
);

$test_customer_data = array(
    'first_name' => 'Parent',
    'last_name' => 'Test',
    'email' => 'parenttest' . time() . '@example.com',
    'password' => 'TestPassword123!',
    'phone' => '2025551234',
    'company_name' => 'Test Company',
    'dob' => '01/01/1990',
    'address_1' => '123 Test St',
    'address_2' => '',
    'city' => 'Test City',
    'state' => 'CA',
    'zip' => '90210',
    'country' => 'US'
);

$result = $monarch_api->create_organization($test_customer_data);

echo "<pre style='background:#f5f5f5; padding:15px; border:1px solid #ddd; overflow-x:auto;'>";

if ($result['success']) {
    echo "<span style='color:green; font-weight:bold;'>✓ Organization Created Successfully</span>\n\n";

    $data = $result['data'] ?? array();
    $org_id = $data['organizationId'] ?? $data['orgId'] ?? $data['_id'] ?? null;
    $parent_org_id = $data['parentOrgId'] ?? null;

    echo "Organization ID: " . ($org_id ?? 'NOT FOUND') . "\n";
    echo "Parent Org ID: " . ($parent_org_id ?? 'NOT FOUND') . "\n\n";

    // Check if parent is correct
    if ($parent_org_id === $gateway->merchant_org_id) {
        echo "<span style='color:green; font-weight:bold; font-size:18px;'>✓✓✓ FIX CONFIRMED! ✓✓✓</span>\n\n";
        echo "The organization was created with the correct parentOrgId!\n";
        echo "Expected: " . $gateway->merchant_org_id . "\n";
        echo "Actual: " . $parent_org_id . "\n\n";
        echo "This means getLatestPayToken should now work correctly!\n";
    } else {
        echo "<span style='color:red; font-weight:bold;'>✗ PARENT ORG ID MISMATCH</span>\n\n";
        echo "Expected: " . $gateway->merchant_org_id . "\n";
        echo "Actual: " . ($parent_org_id ?? 'EMPTY') . "\n";
    }

    echo "\n--- Full API Response ---\n";
    echo json_encode($data, JSON_PRETTY_PRINT);

    if ($org_id) {
        echo "\n\n<hr>\n";
        echo "<h2>Testing getLatestPayToken with Fixed Organization...</h2>\n";

        // Test getLatestPayToken
        $paytoken_result = $monarch_api->get_latest_paytoken($org_id);

        echo "API Call: GET /v1/getlatestpaytoken/$org_id\n";
        echo "Using Merchant Credentials: ...{$gateway->api_key} / ...{$gateway->app_id}\n\n";

        if ($paytoken_result['success']) {
            echo "<span style='color:green; font-weight:bold;'>✓ API CALL SUCCESSFUL!</span>\n\n";
            echo "This confirms the fix is working - the merchant credentials can now access the child organization!\n\n";

            $paytoken_data = $paytoken_result['data'] ?? array();
            echo "Response:\n";
            echo json_encode($paytoken_data, JSON_PRETTY_PRINT);

        } else {
            $error = $paytoken_result['error'] ?? 'Unknown';
            $status_code = $paytoken_result['status_code'] ?? 'N/A';

            if ($status_code == 404 && strpos($error, 'Invalid request headers') !== false) {
                echo "<span style='color:red; font-weight:bold;'>✗ STILL GETTING AUTH ERROR</span>\n\n";
                echo "The fix may not have been applied correctly.\n";
                echo "Error: $error\n";
            } else if ($status_code == 404) {
                echo "<span style='color:orange; font-weight:bold;'>⚠ 404 - No PayToken Found (Expected)</span>\n\n";
                echo "This is normal - no bank account has been linked yet.\n";
                echo "The important thing is we're NOT getting 'Invalid request headers' error!\n";
                echo "This means the merchant credentials CAN access the organization now.\n\n";
                echo "<span style='color:green; font-weight:bold; font-size:18px;'>✓✓✓ FIX IS WORKING! ✓✓✓</span>\n";
            } else {
                echo "<span style='color:orange; font-weight:bold;'>⚠ ERROR</span>\n\n";
                echo "Error: $error\n";
                echo "Status Code: $status_code\n";
            }

            echo "\nFull Response:\n";
            echo json_encode($paytoken_result, JSON_PRETTY_PRINT);
        }
    }

} else {
    echo "<span style='color:red; font-weight:bold;'>✗ Organization Creation Failed</span>\n\n";
    echo "Error: " . ($result['error'] ?? 'Unknown') . "\n";
    echo "Status Code: " . ($result['status_code'] ?? 'N/A') . "\n\n";
    echo "Full Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
}

echo "</pre>";

echo "<hr>";
echo "<h2>What This Test Does:</h2>";
echo "<ol>";
echo "<li>Creates a test purchaser organization with <code>parentOrgId</code> set to your merchant org ID</li>";
echo "<li>Verifies the API response includes the correct <code>parentOrgId</code></li>";
echo "<li>Calls <code>getLatestPayToken</code> using merchant credentials</li>";
echo "<li>Confirms merchant credentials can access the child organization</li>";
echo "</ol>";

echo "<h2>Success Criteria:</h2>";
echo "<ul>";
echo "<li>✓ Organization created with <code>parentOrgId: " . $gateway->merchant_org_id . "</code></li>";
echo "<li>✓ getLatestPayToken returns 404 'No paytoken found' (NOT 'Invalid request headers')</li>";
echo "<li>✗ If you see 'Invalid request headers', the fix didn't work</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='?'>Run Test Again</a> | <a href='test-credentials-debug.php'>View Debug Logs</a> | <a href='/wp-admin/admin.php?page=wc-settings&tab=checkout&section=monarch_ach'>Gateway Settings</a></p>";
