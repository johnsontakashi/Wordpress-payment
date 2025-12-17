jQuery(document).ready(function($) {
    'use strict';
    
    // Test API connection
    $('#test-api-connection').on('click', function() {
        const $button = $(this);
        const $spinner = $('#test-spinner');
        const $results = $('#test-results');
        const $notice = $('#test-notice');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $results.hide();
        
        $.ajax({
            url: monarch_admin_params.ajax_url,
            type: 'POST',
            data: {
                action: 'monarch_test_connection',
                nonce: monarch_admin_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    $notice.removeClass('notice-error').addClass('notice-success')
                           .html('<p><strong>Success:</strong> ' + response.data + '</p>');
                } else {
                    $notice.removeClass('notice-success').addClass('notice-error')
                           .html('<p><strong>Error:</strong> ' + response.data + '</p>');
                }
                $results.show();
            },
            error: function() {
                $notice.removeClass('notice-success').addClass('notice-error')
                       .html('<p><strong>Error:</strong> Connection test failed</p>');
                $results.show();
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // View transaction details
    $('.view-details').on('click', function() {
        const transactionId = $(this).data('transaction');
        // Implementation for viewing transaction details
        // This could open a modal or navigate to a details page
        alert('View details for transaction: ' + transactionId);
    });
    
    // Auto-refresh transaction status
    function refreshTransactionStatus() {
        $('.status-badge.status-pending').each(function() {
            const $this = $(this);
            const $row = $this.closest('tr');
            // Implementation to check and update status
        });
    }
    
    // Refresh every 30 seconds if on transactions page
    if ($('.monarch-admin-section .wp-list-table').length) {
        setInterval(refreshTransactionStatus, 30000);
    }

    // Manual status update button is handled by inline script in the Status Sync tab
    // to ensure it works regardless of external JS loading issues

    // Fix PayToken button - Re-assign PayToken to fix "Invalid PayToken" errors
    $(document).on('click', '.monarch-reassign-paytoken', function(e) {
        e.preventDefault();

        const $button = $(this);
        const userId = $button.data('user-id');
        const orgId = $button.data('org-id');
        const paytokenId = $button.data('paytoken-id');

        if (!confirm('This will re-assign the PayToken to the organization.\n\nThis can fix "paytoken is Invalid" errors.\n\nContinue?')) {
            return;
        }

        const originalText = $button.text();
        $button.prop('disabled', true).text('Fixing...');

        $.ajax({
            url: monarch_admin_params.ajax_url,
            type: 'POST',
            data: {
                action: 'monarch_reassign_paytoken',
                nonce: monarch_admin_params.nonce,
                user_id: userId,
                org_id: orgId,
                paytoken_id: paytokenId
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Fixed!').addClass('button-primary');
                    alert('Success: ' + response.data.message);
                    setTimeout(function() {
                        $button.text(originalText).removeClass('button-primary').prop('disabled', false);
                    }, 2000);
                } else {
                    alert('Error: ' + response.data);
                    $button.text(originalText).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Request failed: ' + error);
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
});