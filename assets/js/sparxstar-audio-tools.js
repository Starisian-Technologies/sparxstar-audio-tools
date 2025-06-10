// In: assets/js/sparxstar-audio-tools.js

jQuery(document).ready(function ($) {
    /**
     * This script acts as the main controller for integrating the editor
     * into the ACF 'track' post type admin screen.
     */

    const TARGET_FIELD_KEY = 'field_678ea0f178a96';
    const $fieldWrapper = $(`.acf-field[data-key="${TARGET_FIELD_KEY}"]`);

    if (!$fieldWrapper.length) {
        return; // Exit if the target ACF field is not on this page.
    }

    // This function injects the "Edit Audio" button when a file is ready.
    function setupEditorButton() {
        // Don't add the button if it already exists.
        if ($('#sparxstar-launch-editor').length > 0) {
            return;
        }

        const fileName = $fieldWrapper.find('.file-name').text();
        if (fileName) {
            $('#sparxstar-editor-root').html(
                `<div class="sparxstar-acf-panel">
                    <p>File selected: <strong>${fileName}</strong></p>
                    <button type="button" class="button" id="sparxstar-launch-editor">Edit Audio & Add Metadata</button>
                </div>`
            );
        }
    }

    // Use a MutationObserver to watch for when ACF adds a file to the uploader.
    // This makes the button appear automatically after a user uploads a file.
    const observer = new MutationObserver(setupEditorButton);
    observer.observe($fieldWrapper[0], { childList: true, subtree: true });

    // Also run on page load in case a file is already present.
    setupEditorButton();

    // --- Event Handlers ---

    // Handle clicking the "Edit Audio" button to launch the modal.
    $('body').on('click', '#sparxstar-launch-editor', function () {
        const fileInput = $fieldWrapper.find('input[type="file"]')[0];
        const alreadyUploadedUrl = $fieldWrapper.find('a.acf-file-uploader-link').attr('href');

        let filePromise;

        if (fileInput && fileInput.files.length > 0) {
            // Case 1: A new file was just selected by the user.
            filePromise = Promise.resolve(fileInput.files[0]);
        } else if (alreadyUploadedUrl) {
            // Case 2: The file was already uploaded (e.g., page was reloaded).
            // We need to fetch it from its URL to get a Blob.
            filePromise = fetch(alreadyUploadedUrl).then(res => res.blob());
        } else {
            alert('Could not find an audio file. Please select or upload one first.');
            return;
        }

        filePromise.then(audioBlob => {
            // Hand off the audio blob to the Waveform editor to initialize.
            window.SparxATWaveform.init(audioBlob);
            $('#sparxstar-modal-container').show();
        }).catch(err => {
            console.error('Error loading audio file:', err);
            alert('Error loading audio file. Please check the console for details.');
        });
    });

    // Handle closing the modal.
    $('body').on('click', '.sparxstar-close-modal-btn', function() {
        $('#sparxstar-modal-container').hide();
        window.SparxATWaveform.destroy(); // Important for cleanup
    });
});