<?php
/**
 * Debug page to verify API credentials and compare organization creation vs getLatestPayToken calls
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

echo "<h1>Monarch API Credentials Debug</h1>";
echo "<hr>";

// Get gateway settings
$gateway = new WC_Monarch_ACH_Gateway();

echo "<h2>Current Gateway Settings:</h2>";
echo "<pre style='background:#f5f5f5; padding:15px; border:1px solid #ddd;'>";
echo "API Key (last 4): " . substr($gateway->api_key, -4) . "\n";
echo "App ID (last 4): " . substr($gateway->app_id, -4) . "\n";
echo "Merchant Org ID: " . $gateway->merchant_org_id . "\n";
echo "Partner Name: " . $gateway->partner_name . "\n";
echo "Test Mode: " . ($gateway->testmode ? 'Yes (Sandbox)' : 'No (Production)') . "\n";
echo "Base URL: " . ($gateway->testmode ? 'https://devapi.monarch.is/v1' : 'https://api.monarch.is/v1') . "\n";
echo "</pre>";

echo "<hr>";
echo "<h2>Recent WooCommerce Logs:</h2>";

// Get log files
$log_dir = WP_CONTENT_DIR . '/uploads/wc-logs/';
if (is_dir($log_dir)) {
    $log_files = glob($log_dir . 'monarch-*.log');

    if (empty($log_files)) {
        echo "<p style='color:orange;'>No log files found. Make sure WP_DEBUG is enabled in wp-config.php</p>";
        echo "<p>To enable debug logging, add this to wp-config.php:<br><code>define('WP_DEBUG', true);</code></p>";
    } else {
        // Sort by modification time, newest first
        usort($log_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        echo "<p>Found " . count($log_files) . " log file(s). Showing the most recent:</p>";

        // Show the most recent log file
        $latest_log = $log_files[0];
        echo "<h3>Latest Log File: " . basename($latest_log) . "</h3>";
        echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($latest_log)) . "</p>";

        // Read last 100 lines
        $content = file_get_contents($latest_log);
        $lines = explode("\n", $content);
        $recent_lines = array_slice($lines, -100);

        echo "<pre style='background:#f5f5f5; padding:15px; border:1px solid #ddd; max-height:500px; overflow-y:auto;'>";
        echo htmlspecialchars(implode("\n", $recent_lines));
        echo "</pre>";

        echo "<hr>";
        echo "<p><strong>All Log Files:</strong></p>";
        echo "<ul>";
        foreach ($log_files as $log_file) {
            $size = filesize($log_file);
            $modified = date('Y-m-d H:i:s', filemtime($log_file));
            echo "<li>" . basename($log_file) . " - " . number_format($size) . " bytes - Modified: $modified</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color:red;'>Log directory not found: $log_dir</p>";
}

echo "<hr>";
echo "<h2>Test Instructions:</h2>";
echo "<ol>";
echo "<li>Make sure <code>WP_DEBUG</code> is set to <code>true</code> in wp-config.php</li>";
echo "<li>Go through the checkout flow and click 'Connect Bank Account'</li>";
echo "<li>After bank linking, click 'I've Connected My Bank'</li>";
echo "<li>Reload this page to see the debug logs</li>";
echo "<li>Compare the credentials used for organization creation vs. getLatestPayToken</li>";
echo "</ol>";

echo "<hr>";
echo "<h2>Expected Behavior:</h2>";
echo "<p>Both <code>ajax_create_organization</code> and <code>ajax_get_latest_paytoken</code> should use the SAME credentials:</p>";
echo "<ul>";
echo "<li>API Key (last 4): " . substr($gateway->api_key, -4) . "</li>";
echo "<li>App ID (last 4): " . substr($gateway->app_id, -4) . "</li>";
echo "<li>Merchant Org ID: " . $gateway->merchant_org_id . "</li>";
echo "<li>Test Mode: " . ($gateway->testmode ? 'Sandbox' : 'Production') . "</li>";
echo "</ul>";

echo "<p>If the credentials don't match, that's the cause of the 'Invalid request headers' error.</p>";

echo "<hr>";
echo "<p><a href='?'>Refresh Logs</a> | <a href='/wp-admin/admin.php?page=wc-settings&tab=checkout&section=monarch_ach'>Gateway Settings</a></p>";
