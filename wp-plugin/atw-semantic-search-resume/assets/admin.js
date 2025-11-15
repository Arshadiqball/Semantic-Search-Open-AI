/**
 * Admin JavaScript for ATW Semantic Search Resume Plugin
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Generate Dummy Jobs button handler
        $('#atw_generate_dummy_jobs_btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $status = $('#atw_dummy_jobs_status');
            var $message = $('#atw_dummy_jobs_message');
            var nonce = $btn.data('nonce');
            
            // Disable button and show loading
            $btn.prop('disabled', true);
            $status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> Generating jobs...');
            $message.html('');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'atw_generate_dummy_jobs',
                    nonce: nonce,
                    count: 100
                },
                timeout: 300000, // 5 minutes
                success: function(response) {
                    $btn.prop('disabled', false);
                    $status.html('');
                    
                    if (response.success) {
                        var count = response.data.count || 0;
                        $message.html(
                            '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> ' +
                            'Successfully generated ' + count + ' dummy jobs. You can now test the resume matching functionality.</p></div>'
                        );
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error occurred';
                        $message.html(
                            '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> ' +
                            errorMsg + '</p></div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false);
                    $status.html('');
                    
                    var errorMsg = 'Failed to generate jobs. ';
                    if (status === 'timeout') {
                        errorMsg += 'Request timed out. The jobs may still be generating in the background.';
                    } else {
                        errorMsg += error || 'Please try again.';
                    }
                    
                    $message.html(
                        '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> ' +
                        errorMsg + '</p></div>'
                    );
                }
            });
        });
        
        // Sync WordPress Jobs button handler
        $('#atw_sync_wordpress_jobs_btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $status = $('#atw_sync_jobs_status');
            var $message = $('#atw_sync_jobs_message');
            var nonce = $btn.data('nonce');
            
            // Disable button and show loading
            $btn.prop('disabled', true);
            $status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> Syncing jobs...');
            $message.html('');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'atw_sync_wordpress_jobs',
                    nonce: nonce
                },
                timeout: 300000, // 5 minutes
                success: function(response) {
                    $btn.prop('disabled', false);
                    $status.html('');
                    
                    if (response.success) {
                        var data = response.data;
                        var message = '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> ';
                        message += 'Synced ' + (data.processed || 0) + ' jobs. ';
                        message += 'Created: ' + (data.created || 0) + ', Updated: ' + (data.updated || 0) + '</p></div>';
                        $message.html(message);
                        
                        // Update jobs count
                        if (data.total !== undefined) {
                            $('#atw_wp_jobs_count').text(data.total);
                        }
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error occurred';
                        $message.html(
                            '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> ' +
                            errorMsg + '</p></div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false);
                    $status.html('');
                    
                    var errorMsg = 'Failed to sync jobs. ';
                    if (status === 'timeout') {
                        errorMsg += 'Request timed out. The sync may still be processing in the background.';
                    } else {
                        errorMsg += error || 'Please try again.';
                    }
                    
                    $message.html(
                        '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> ' +
                        errorMsg + '</p></div>'
                    );
                }
            });
        });
    });
    
})(jQuery);

