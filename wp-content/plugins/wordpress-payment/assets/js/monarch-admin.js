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

    // Manual status update button
    $('#manual-status-update').on('click', function() {
        const $button = $(this);
        const $spinner = $('#status-sync-spinner');
        const $results = $('#status-sync-results');
        const $notice = $('#status-sync-notice');

        if (!confirm('This will check all pending transactions with the Monarch API. Continue?')) {
            return;
        }

        $button.prop('disabled', true).text('Updating...');
        $spinner.addClass('is-active');
        $results.hide();

        $.ajax({
            url: monarch_admin_params.ajax_url,
            type: 'POST',
            data: {
                action: 'monarch_manual_status_update',
                nonce: monarch_admin_params.nonce
            },
            timeout: 300000, // 5 minutes timeout for large batches
            success: function(response) {
                if (response.success) {
                    $notice.removeClass('notice-error').addClass('notice-success')
                           .html('<p><strong>Success:</strong> ' + response.data.message + '</p>');
                } else {
                    $notice.removeClass('notice-success').addClass('notice-error')
                           .html('<p><strong>Error:</strong> ' + response.data + '</p>');
                }
                $results.show();
            },
            error: function(xhr, status, error) {
                let errorMsg = 'Status update failed';
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. The update may still be running in the background.';
                } else if (error) {
                    errorMsg = error;
                }
                $notice.removeClass('notice-success').addClass('notice-error')
                       .html('<p><strong>Error:</strong> ' + errorMsg + '</p>');
                $results.show();
            },
            complete: function() {
                $button.prop('disabled', false).text('Update Transaction Statuses Now');
                $spinner.removeClass('is-active');
            }
        });
    });
});