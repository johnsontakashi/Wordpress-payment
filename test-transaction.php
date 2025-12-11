<?php
/**
 * Test Monarch Full 4-Step Payment Flow
 * Run: php test-transaction.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Your Monarch sandbox credentials
$api_key = '556e3d05-f342-4ee7-9047-84ce23c4d361';
$app_id = 'e4de843f';
$merchant_org_id = '5253091918';
$partner_name = 'houseph';
$base_url = 'https://devapi.monarch.is/v1';

// Test customer data
$test_customer = array(
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'jastinmax888' . time() . '@gmail.com',
    'phone' => '5551234567',
    'dob' => '01/15/1990'
);

// Test bank data
$test_bank = array(
    'bank_name' => 'Monarch Bank',
    'routing_number' => '122000247',
    'account_number' => '328375647485',
    'account_type' => 'CHECKING'
);

function make_request($method, $url, $data, $api_key, $app_id) {
    $headers = array(
        'accept: application/json',
        'X-API-KEY: ' . $api_key,
        'X-APP-ID: ' . $app_id,
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return array(
        'http_code' => $http_code,
        'response' => json_decode($response, true),
        'error' => $error
    );
}

echo "=== Monarch Full Payment Flow Test ===\n\n";

// Step 1: Create Organization
echo "--- STEP 1: Create Organization ---\n";
$org_data = array(
    'first_name' => $test_customer['first_name'],
    'last_name' => $test_customer['last_name'],
    'email' => $test_customer['email'],
    'password' => 'TestPass123',
    'odfi_endpoint' => 'ODFI180',
    'orgType' => 'purchaser',
    'originationClient' => 'partner_app',
    'partnerName' => $partner_name,
    'authType' => '',
    'parentOrgId' => '',
    'user_metadata' => array(
        'phone' => $test_customer['phone'],
        'companyName' => '',
        'dob' => $test_customer['dob'],
        'add1' => '123 Test Street',
        'add2' => '',
        'city' => 'Los Angeles',
        'state' => 'CA',
        'zip' => '90001',
        'country' => 'US'
    )
);

$result1 = make_request('POST', $base_url . '/organization', $org_data, $api_key, $app_id);
echo "HTTP: " . $result1['http_code'] . "\n";
print_r($result1['response']);

if ($result1['http_code'] < 200 || $result1['http_code'] >= 300) {
    echo "\n❌ Step 1 FAILED - Cannot continue\n";
    exit;
}

$user_id = $result1['response']['_id'] ?? null;
$org_id = $result1['response']['orgId'] ?? null;
echo "\n✅ Step 1 SUCCESS - user_id: $user_id, org_id: $org_id\n\n";

// Step 2: Create PayToken
echo "--- STEP 2: Create PayToken ---\n";
$paytoken_data = array(
    'pay_type' => 'Helox',
    'bankName' => $test_bank['bank_name'],
    'userId' => $user_id,
    'dda' => $test_bank['account_number'],
    'routing' => $test_bank['routing_number'],
    'accountId' => $test_bank['account_number'],
    'providerAccountId' => $test_bank['account_number'],
    'accountType' => $test_bank['account_type'],
    'currentBalance' => array('currency' => 'USD', 'amount' => 0),
    'yodlee' => true,
    'networkId' => '',
    'cc_account_number' => '',
    'cc_card_number' => '',
    'cvv' => '',
    'cc_expiration_month' => '',
    'cc_expiration_year' => ''
);

$result2 = make_request('POST', $base_url . '/paytoken', $paytoken_data, $api_key, $app_id);
echo "HTTP: " . $result2['http_code'] . "\n";
print_r($result2['response']);

if ($result2['http_code'] < 200 || $result2['http_code'] >= 300) {
    echo "\n❌ Step 2 FAILED - Cannot continue\n";
    exit;
}

$paytoken_id = $result2['response']['payToken'] ?? null;
echo "\n✅ Step 2 SUCCESS - paytoken_id: $paytoken_id\n\n";

// Step 3: Assign PayToken
echo "--- STEP 3: Assign PayToken ---\n";
$assign_data = array(
    'payTokenId' => $paytoken_id,
    'orgId' => $org_id
);

$result3 = make_request('PUT', $base_url . '/organization/paytoken/assign', $assign_data, $api_key, $app_id);
echo "HTTP: " . $result3['http_code'] . "\n";
print_r($result3['response']);

if ($result3['http_code'] < 200 || $result3['http_code'] >= 300) {
    echo "\n❌ Step 3 FAILED - Cannot continue\n";
    exit;
}

echo "\n✅ Step 3 SUCCESS - PayToken assigned\n\n";

// Wait a moment for data to propagate
echo "Waiting 2 seconds for data propagation...\n";
sleep(2);

// Step 4: Sale Transaction
echo "--- STEP 4: Sale Transaction ---\n";
echo "Using orgId: $org_id\n";
echo "Using payTokenId: $paytoken_id\n";
echo "Using merchantOrgId: $merchant_org_id\n";
$sale_data = array(
    'amount' => 1.00,
    'orgId' => $org_id,
    'comment' => 'Test transaction ' . date('Y-m-d H:i:s'),
    'service_origin' => 'partner_app',
    'partnerName' => $partner_name,
    'account_type' => 'C',
    'payTokenId' => $paytoken_id,
    'subscription_plan_id' => '',
    'taxRemittance' => '',
    'merchantOrgId' => $merchant_org_id
);
echo "Request body:\n";
print_r($sale_data);

$result4 = make_request('POST', $base_url . '/transaction/sale', $sale_data, $api_key, $app_id);
echo "HTTP: " . $result4['http_code'] . "\n";
print_r($result4['response']);

if ($result4['http_code'] < 200 || $result4['http_code'] >= 300) {
    echo "\n❌ Step 4 FAILED\n";
    echo "Error: " . ($result4['response']['error']['message'] ?? $result4['response']['message'] ?? 'Unknown error') . "\n";
} else {
    echo "\n✅ Step 4 SUCCESS - Transaction completed!\n";
    echo "Transaction ID: " . ($result4['response']['id'] ?? 'N/A') . "\n";
}

echo "\n=== Test Complete ===\n";
