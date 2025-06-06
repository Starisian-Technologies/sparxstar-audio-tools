jQuery(document).ready(function($) {
    // Start Mastering / Retry Button
    $('body').on('click', '.aim-start-mastering-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $wrapper = $button.closest('.aim-status-wrapper');
        const postId = $wrapper.data('postid');
        const $messageDiv = $wrapper.find('.aim-ajax-message');

        $button.prop('disabled', true).text('Processing...');
        $messageDiv.hide().removeClass('aim-error aim-success');

        $.ajax({
            url: aim_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'aim_frontend_action',
                _ajax_nonce: aim_ajax_object.nonce,
                sub_action: 'start_mastering',
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    $messageDiv.text('Mastering process initiated. Refresh the page or click "Refresh Status" to see updates.').addClass('aim-success').show();
                    // Optionally, you could update the status display directly
                    // For simplicity, we'll ask for a page refresh or use the refresh button.
                    $wrapper.find('.aim-status-current').html('<p><strong>Status:</strong> Submitted for Processing</p><p>Refresh to see Job ID.</p>'); // Basic update
                    $button.remove(); // Remove start button
                     // Add refresh button if not present
                    if ($wrapper.find('.aim-refresh-status-btn').length === 0) {
                        $wrapper.find('.aim-status-current').after('<button class="aim-button aim-refresh-status-btn">Refresh Status</button>');
                    }
                } else {
                    $messageDiv.text('Error: ' + response.data.message).addClass('aim-error').show();
                    $button.prop('disabled', false).text($button.hasClass('aim-retry-btn') ? 'Retry Mastering' : 'Start AI Mastering');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $messageDiv.text('AJAX Error: ' + textStatus + ' - ' + errorThrown).addClass('aim-error').show();
                $button.prop('disabled', false).text($button.hasClass('aim-retry-btn') ? 'Retry Mastering' : 'Start AI Mastering');
            }
        });
    });

    // Refresh Status Button
    $('body').on('click', '.aim-refresh-status-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $wrapper = $button.closest('.aim-status-wrapper');
        const postId = $wrapper.data('postid');
        const $messageDiv = $wrapper.find('.aim-ajax-message');

        $button.prop('disabled', true).text('Refreshing...');
        $messageDiv.hide().removeClass('aim-error aim-success');

        $.ajax({
            url: aim_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'aim_frontend_action',
                _ajax_nonce: aim_ajax_object.nonce,
                sub_action: 'refresh_status',
                post_id: postId
            },
            success: function(response) {
                $button.prop('disabled', false).text('Refresh Status');
                if (response.success) {
                    $messageDiv.text('Status updated.').addClass('aim-success').show().fadeOut(3000);
                    // For a full refresh of the content, reload the shortcode content (more complex)
                    // or just update key fields based on response.data
                    // Simplest: Reload the whole page or ask user to.
                    // For a better UX, update the displayed HTML based on response.data.new_html
                    // For now, we just show a success message. A full page reload would show changes.
                    // Or, if response.data.html is the new shortcode output:
                    if(response.data.html) {
                        $wrapper.replaceWith(response.data.html);
                    } else {
                        // Fallback: just update status text
                        $wrapper.find('.aim-status-text').text(response.data.status_text || 'Refreshed');
                        // This part needs to be more robust if not doing a full HTML replacement
                    }

                } else {
                    $messageDiv.text('Error: ' + response.data.message).addClass('aim-error').show();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $button.prop('disabled', false).text('Refresh Status');
                $messageDiv.text('AJAX Error: ' + textStatus + ' - ' + errorThrown).addClass('aim-error').show();
            }
        });
    });
});