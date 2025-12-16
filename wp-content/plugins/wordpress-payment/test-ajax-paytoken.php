<?php
/**
 * Test AJAX endpoint for getLatestPayToken
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

echo "<h1>Test AJAX getLatestPayToken Endpoint</h1>";
echo "<hr>";

// Check if user is logged in
echo "<h2>User Status:</h2>";
echo "Logged in: " . (is_user_logged_in() ? '<span style="color:green;">Yes (User ID: ' . get_current_user_id() . ')</span>' : '<span style="color:red;">No</span>') . "<br>";

// Get nonce
$nonce = wp_create_nonce('monarch_ach_nonce');
echo "Nonce created: <code>$nonce</code><br>";

// Test organization ID (use a real one from your database or create one)
$test_org_id = '5887435610'; // Use the org ID from your error

echo "<hr>";
echo "<h2>Testing AJAX Call:</h2>";

?>

<script src="<?php echo site_url('/wp-includes/js/jquery/jquery.min.js'); ?>"></script>
<script>
jQuery(document).ready(function($) {
    console.log('Testing AJAX call...');

    var testData = {
        action: 'monarch_get_latest_paytoken',
        nonce: '<?php echo $nonce; ?>',
        org_id: '<?php echo $test_org_id; ?>'
    };

    console.log('Sending data:', testData);

    $.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        method: 'POST',
        data: testData,
        dataType: 'json',
        success: function(response) {
            console.log('Success response:', response);
            $('#result').html('<pre style="background:#d4edda; padding:15px; border:1px solid #c3e6cb;">' +
                '<strong style="color:green;">✓ SUCCESS</strong>\n\n' +
                JSON.stringify(response, null, 2) +
                '</pre>');
        },
        error: function(xhr, status, error) {
            console.error('Error response:', xhr);
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response text:', xhr.responseText);

            $('#result').html('<pre style="background:#f8d7da; padding:15px; border:1px solid #f5c6cb;">' +
                '<strong style="color:red;">✗ ERROR</strong>\n\n' +
                'Status Code: ' + xhr.status + '\n' +
                'Status: ' + status + '\n' +
                'Error: ' + error + '\n\n' +
                'Response Text:\n' + xhr.responseText +
                '</pre>');
        }
    });
});
</script>

<div id="result">
    <p>Waiting for AJAX response...</p>
</div>

<hr>
<h2>Debug Information:</h2>
<pre style="background:#f5f5f5; padding:15px; border:1px solid #ddd;">
AJAX URL: <?php echo admin_url('admin-ajax.php'); ?>
Action: monarch_get_latest_paytoken
Nonce: <?php echo $nonce; ?>
Org ID: <?php echo $test_org_id; ?>

Check browser console for detailed request/response information.
</pre>

<hr>
<p><a href="?">Reload Test</a></p>
