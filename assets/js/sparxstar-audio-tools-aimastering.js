import $ from 'jquery';

jQuery(document).ready(($) => {
    // Start Mastering / Retry Button
    $('body').on('click', '.SparxAT-start-mastering-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $wrapper = $button.closest('.SparxAT-status-wrapper');
        const postId = $wrapper.data('postid');
        const $messageDiv = $wrapper.find('.SparxAT-ajax-message');

        $button.prop('disabled', true).text('Processing...');
        $messageDiv.hide().removeClass('SparxAT-error SparxAT-success');

        $.ajax({
            url: SparxAT_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'SparxAT_frontend_action',
                _ajax_nonce: SparxAT_ajax_object.nonce,
                sub_action: 'start_mastering',
                post_id: postId
            },
            success(response) {
                if (response.success) {
                    $messageDiv.text('Mastering process initiated. Refresh the page or click "Refresh Status" to see updates.').addClass('SparxAT-success').show();
                    // Optionally, you could update the status display directly
                    // For simplicity, we'll ask for a page refresh or use the refresh button.
                    $wrapper.find('.SparxAT-status-current').html('<p><strong>Status:</strong> Submitted for Processing</p><p>Refresh to see Job ID.</p>'); // Basic update
                    $button.remove(); // Remove start button
                     // Add refresh button if not present
                    if ($wrapper.find('.SparxAT-refresh-status-btn').length === 0) {
                        $wrapper.find('.SparxAT-status-current').after('<button class="SparxAT-button SparxAT-refresh-status-btn">Refresh Status</button>');
                    }
                    return;
                }
                $messageDiv.text(`Error: ${response.data.message}`).addClass('SparxAT-error').show();
                $button.prop('disabled', false).text($button.hasClass('SparxAT-retry-btn') ? 'Retry Mastering' : 'Start AI Mastering');
            },
            error(jqXHR, textStatus, errorThrown) {
                $messageDiv.text(`AJAX Error: ${textStatus} - ${errorThrown}`).addClass('SparxAT-error').show();
                $button.prop('disabled', false).text($button.hasClass('SparxAT-retry-btn') ? 'Retry Mastering' : 'Start AI Mastering');
            }
        });
    });

    // Refresh Status Button
    $('body').on('click', '.SparxAT-refresh-status-btn', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $wrapper = $button.closest('.SparxAT-status-wrapper');
        const postId = $wrapper.data('postid');
        const $messageDiv = $wrapper.find('.SparxAT-ajax-message');

        $button.prop('disabled', true).text('Refreshing...');
        $messageDiv.hide().removeClass('SparxAT-error SparxAT-success');

        $.ajax({
            url: SparxAT_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'SparxAT_frontend_action',
                _ajax_nonce: SparxAT_ajax_object.nonce,
                sub_action: 'refresh_status',
                post_id: postId
            },
            success(response) {
                $button.prop('disabled', false).text('Refresh Status');
                if (response.success) {
                    $messageDiv.text('Status updated.').addClass('SparxAT-success').show().fadeOut(3000);
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
                        $wrapper.find('.SparxAT-status-text').text(response.data.status_text || 'Refreshed');
                        // This part needs to be more robust if not doing a full HTML replacement
                    }

                } else {
                    $messageDiv.text(`Error: ${response.data.message}`).addClass('SparxAT-error').show();
                }
            },
            error(jqXHR, textStatus, errorThrown) {
                $button.prop('disabled', false).text('Refresh Status');
                $messageDiv.text(`AJAX Error: ${textStatus} - ${errorThrown}`).addClass('SparxAT-error').show();
            }
        });
    });
});

window.SparxATUploader = {
    /**
     * Handles the final step: uploading the tagged blob to the WordPress backend.
     * @param {Blob} taggedBlob The final audio blob with metadata.
     * @param {object} metadata The scraped metadata, used for filename.
     * @param {string} actionType The action to perform ('save', 'master', 'export').
     */
    upload: async function(taggedBlob, metadata, actionType) {
        const finalFilename = `${metadata.artist || 'Artist'} - ${metadata.title || 'Untitled'}.mp3`;

        // Handle client-side 'export' (download) directly
        if (actionType === 'export') {
            const url = URL.createObjectURL(taggedBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = finalFilename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            window.SparxATWaveform.updateStatus('success', 'Download started!');
            return;
        }
        
        // For 'save' and 'master', we send the file to WordPress
        const formData = new FormData();
        formData.append('action_type', actionType);
        formData.append('original_post_id', sparxstar_ajax.post_id);
        formData.append('audio_blob', taggedBlob, finalFilename);
        formData.append('filename', finalFilename);

        try {
            const response = await fetch('/wp-json/sparxstar/v1/update-track-audio', {
                method: 'POST',
                headers: { 'X-WP-Nonce': sparxstar_ajax.nonce },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'An unknown server error occurred.');
            }

            // SUCCESS!
            window.SparxATWaveform.updateStatus('success', data.message);

            // Use the ACF JS API to update the file field in the browser
            const acfField = acf.getField('field_678ea0f178a96');
            if (acfField) {
                acfField.val(data.attachment_id);
            }
            
            // Close the modal after a short delay
            setTimeout(() => {
                $('.sparxstar-close-modal-btn').click();
            }, 3000);

        } catch (error) {
            window.SparxATWaveform.updateStatus('error', `Upload Failed: ${error.message}`);
            console.error(error);
        }
    }
};