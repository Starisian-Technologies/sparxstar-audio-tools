// File: sparxstar-audio-tools.js
//             console.log('Audio file uploaded successfully:', data);
// This file is part of the SparxStar Audio Tools plugin for WordPress.
//             if (data.success) {
//                 window.SparxATWaveform.updateStatus('success', 'Audio file uploaded successfully!');
//             } else {
//                 window.SparxATWaveform.updateStatus('error', `Upload failed: ${data.message}`);
//             }

// This script integrates the SparxStar Audio Tools editor into the ACF 'track' post type admin screen.
// It adds an "Edit Audio" button to the ACF field when a file is selected,
// allowing users to edit audio files and add metadata using the SparxATWaveform editor.
// It uses MutationObserver to dynamically update the UI when files are added or removed.

jQuery(document).ready(function ($) {
    /**
     * This script acts as the main controller for integrating the editor
     * into the ACF 'track' post type admin screen.
     */
    const TARGET_FIELD_KEY = 'field_678ea0f178a96'; // Your ACF Field Key
    const $fieldWrapper = $(`.acf-field[data-key="${TARGET_FIELD_KEY}"]`);

    if (!$fieldWrapper.length) {
        console.log("SparxStar Target ACF Field not found. Editor button not added.");
        return; // Exit if the target ACF field is not on this page.
    }

    // Function to inject the "Edit Audio" button
    function setupEditorButton() {
        if ($('#sparxstar-launch-editor').length > 0) {
            return; // Button already exists
        }

        const fileName = $fieldWrapper.find('.file-name').text();
        // Ensure the editor root div exists if you're injecting into it.
        // It might be better to append/prepend relative to $fieldWrapper or a known ACF element.
        // Assuming #sparxstar-editor-root is a placeholder div you add in the ACF field group or via PHP.
        // If not, you might append the button directly to an element within $fieldWrapper.
        let $buttonContainer = $fieldWrapper.find('.acf-input'); // Example: append to the input area
        if (!$buttonContainer.length) $buttonContainer = $fieldWrapper; // Fallback

        // Clear previous button if any (e.g., if file was removed and re-added)
        $buttonContainer.find('#sparxstar-launch-editor-container').remove();

        if (fileName) {
            $buttonContainer.append(
                `<div id="sparxstar-launch-editor-container" class="sparxstar-acf-panel" style="margin-top: 10px;">
                    <p style="margin-bottom: 5px;">File: <strong>${fileName}</strong></p>
                    <button type="button" class="button" id="sparxstar-launch-editor">Edit Audio & Add Metadata</button>
                </div>`
            );
        } else {
             $('#sparxstar-launch-editor-container').remove(); // Remove button if no file
        }
    }

    // MutationObserver to watch for ACF file changes
    const observer = new MutationObserver(function(mutationsList, observer) {
        // We're interested in changes to the file-name or link,
        // or if the uploader adds/removes the 'has-value' class.
        setupEditorButton();
    });
    observer.observe($fieldWrapper[0], { childList: true, subtree: true, attributes: true });

    setupEditorButton(); // Initial setup on page load

    // Event Handler for launching the editor modal
    $('body').on('click', '#sparxstar-launch-editor', function () {
        const fileInput = $fieldWrapper.find('input[type="file"]')[0];
        const alreadyUploadedUrl = $fieldWrapper.find('a.acf-file-uploader-link').attr('href');
        let filePromise;

        if (fileInput && fileInput.files && fileInput.files.length > 0) {
            filePromise = Promise.resolve(fileInput.files[0]);
        } else if (alreadyUploadedUrl) {
            filePromise = fetch(alreadyUploadedUrl)
                            .then(res => {
                                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                                return res.blob();
                            })
                            .then(blob => {
                                // Try to reconstruct original filename if possible (though blob won't have it directly)
                                const originalName = $fieldWrapper.find('.file-name').text() || 'uploaded_audio_file';
                                return new File([blob], originalName, { type: blob.type });
                            });
        } else {
            alert('Could not find an audio file. Please select or upload one first.');
            return;
        }

        filePromise.then(audioFileObject => { // Expecting a File object now
            if (window.SparxATWaveform && typeof window.SparxATWaveform.init === 'function') {
                window.SparxATWaveform.init(audioFileObject); // Pass the File object
                $('#sparxstar-modal-container').show(); // Assuming this ID is for your modal
            } else {
                alert('Audio editor (SparxATWaveform) is not available.');
                console.error('SparxATWaveform or SparxATWaveform.init is not defined.');
            }
        }).catch(err => {
            console.error('Error loading audio file for editor:', err);
            alert('Error loading audio file. Please check the console for details.');
        });
    });

    // Event Handler for closing the modal
    $('body').on('click', '.sparxstar-close-modal-btn', function() { // Ensure your modal has this button
        $('#sparxstar-modal-container').hide();
        if (window.SparxATWaveform && typeof window.SparxATWaveform.destroy === 'function') {
            window.SparxATWaveform.destroy();
        }
    });
});

/** OLD CODE 
jQuery(document).ready(function ($) {
    /**
     * This script acts as the main controller for integrating the editor
     * into the ACF 'track' post type admin screen.
     */

   /* const TARGET_FIELD_KEY = 'field_678ea0f178a96';
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