// This file is part of the SparxStar Audio Tools plugin for WordPress.
// File: assets/js/sparxstar-audio-tools-waveform.js

// Option 1: Using ES6 Imports (requires a build step like Webpack/Rollup/Parcel)
// import WaveSurfer from 'wavesurfer.js'; // From node_modules
// import RegionsPlugin from 'wavesurfer.js/dist/plugins/regions.esm.js';
// import TimelinePlugin from 'wavesurfer.js/dist/plugins/timeline.esm.js';
// import * as Tone from 'tone';
// import ID3Writer from 'browser-id3-writer';

// Option 2: If you are including these libraries via separate <script> tags (e.g., from CDN in your modal's HTML)
// then you don't use these import statements here. The libraries (WaveSurfer, Tone, ID3Writer)
// would be globally available. Make sure they are loaded *before* this script runs.

(function() { // IIFE to encapsulate the code and avoid polluting global scope too much

    // --- Helper: bufferToWaveBlob (ensure this is defined once) ---
    function bufferToWaveBlob(buffer) {
        const numOfChan = buffer.numberOfChannels, length = buffer.length * numOfChan * 2 + 44;
        const bufferArr = new ArrayBuffer(length), view = new DataView(bufferArr);
        const channels = []; let i, sample, offset = 0, pos = 0;
        setUint32(0x46464952); setUint32(length - 8); setUint32(0x45564157);
        setUint32(0x20746d66); setUint32(16); setUint16(1); setUint16(numOfChan);
        setUint32(buffer.sampleRate); setUint32(buffer.sampleRate * 2 * numOfChan);
        setUint16(numOfChan * 2); setUint16(16); setUint32(0x61746164);
        setUint32(length - pos - 4);
        for(i=0;i<buffer.numberOfChannels;i++)channels.push(buffer.getChannelData(i));
        while(pos<length){for(i=0;i<numOfChan;i++){sample=Math.max(-1,Math.min(1,channels[i][offset]));sample=(0.5+sample<0?sample*32768:sample*32767)|0;view.setInt16(pos,sample,true);pos+=2;}offset++;}
        return new Blob([view],{type:'audio/wav'});
        function setUint16(data){view.setUint16(pos,data,true);pos+=2;}
        function setUint32(data){view.setUint32(pos,data,true);pos+=4;}
    }

    // --- Helper: blobToAudioBuffer ---
    async function blobToAudioBuffer(blob) {
        const arrayBuffer = await blob.arrayBuffer();
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        return audioContext.decodeAudioData(arrayBuffer);
    }
    
    // --- Helper: cleanupAudio (using Tone.js) ---
    async function cleanupAudioWithTone(blob) {
        if (typeof Tone === 'undefined') {
            console.warn('Tone.js is not loaded. Skipping audio cleanup.');
            return blob; // Return original blob if Tone is not available
        }
        const originalUrl = URL.createObjectURL(blob);
        const gate = new Tone.Gate(-30, 0.2).toDestination();
        const compressor = new Tone.Compressor(-24, 12).connect(gate);
        let bufferDuration;
        try {
            const tempPlayer = new Tone.Player(originalUrl);
            await tempPlayer.load(originalUrl);
            bufferDuration = tempPlayer.buffer.duration;
            tempPlayer.dispose(); // Clean up temporary player
        } catch(e) {
            URL.revokeObjectURL(originalUrl);
            throw new Error('Failed to load audio into Tone.Player for duration check: ' + e.message);
        }

        if (bufferDuration <= 0) {
             URL.revokeObjectURL(originalUrl);
             throw new Error('Audio buffer duration is invalid for Tone.Offline processing.');
        }

        const processedBuffer = await Tone.Offline(async ({ transport }) => {
            const player = new Tone.Player(originalUrl).connect(compressor);
            player.sync().start(0);
            transport.start();
        }, bufferDuration);

        URL.revokeObjectURL(originalUrl);
        return bufferToWaveBlob(processedBuffer);
    }


    window.SparxATWaveform = {
        ws: null,
        wsRegions: null,
        activeRegion: null,
        originalFileObject: null, // Store the original File object
        originalCleanedBuffer: null, // AudioBuffer of the initially loaded (and possibly cleaned) audio

        init: async function(audioFileObject) { // Expects a File object
            this.originalFileObject = audioFileObject; // Store it
            this.destroy(); // Clear any previous instance

            // Ensure WaveSurfer, RegionsPlugin, TimelinePlugin are available
            if (typeof WaveSurfer === 'undefined' || typeof RegionsPlugin === 'undefined' || typeof TimelinePlugin === 'undefined') {
                this.updateStatus('error', 'WaveSurfer or its plugins are not loaded.');
                console.error('WaveSurfer or required plugins not found.');
                return;
            }

            this.ws = WaveSurfer.create({
                container: '#sparxstar_waveform', // From your modal's HTML template
                waveColor: '#C800C8',
                progressColor: '#640064',
                height: 128,
                minPxPerSec: 100, // Adjust as needed
                plugins: [TimelinePlugin.create({ height: 20 })],
                // Consider 'file' parameter for WaveSurfer if it can take a File object directly
                // url: URL.createObjectURL(audioFileObject) // Or create object URL
            });
            this.wsRegions = this.ws.registerPlugin(RegionsPlugin.create());
            this.attachWsEvents(); // Attach WaveSurfer events

            this.updateStatus('processing', 'Loading audio & preparing waveform...');
            try {
                // Option 1: If you want to clean audio first
                // const cleanedBlob = await cleanupAudioWithTone(audioFileObject);
                // this.originalCleanedBuffer = await blobToAudioBuffer(cleanedBlob);
                // await this.ws.loadBlob(cleanedBlob);

                // Option 2: Load original directly, then offer cleanup as an option later
                this.originalCleanedBuffer = await blobToAudioBuffer(audioFileObject);
                await this.ws.loadBlob(audioFileObject); // WaveSurfer can often load File objects directly or via blob
                
                this.updateStatus('info', 'Audio loaded. Waveform generated.');
            } catch (error) {
                this.updateStatus('error', `Error loading/processing audio: ${error.message}`);
                console.error("Waveform Init Error:", error);
            }
        },

        attachWsEvents: function() {
            if (!this.ws || !this.wsRegions) return;

            this.ws.on('decode', (duration) => {
                // console.log("Decoded duration:", duration);
                // this.findSoundRegions(this.ws.getDecodedData(), duration); // If you want auto regions
            });

            this.wsRegions.on('region-clicked', (region, e) => {
                e.stopPropagation();
                if (this.activeRegion && this.activeRegion !== region) {
                    this.activeRegion.element.classList.remove('sparxstar_selected_region');
                }
                this.activeRegion = region;
                region.element.classList.add('sparxstar_selected_region');
                // Enable action buttons (assuming they are in your modal HTML)
                document.querySelectorAll('#sparxstar-actions-panel .button').forEach(btn => btn.disabled = false);
                region.play();
            });
            
            this.wsRegions.on('region-created', (region) => {
                // Example: Allow only one region at a time for simplicity
                Object.values(this.wsRegions.getRegions()).forEach(r => {
                    if (r !== region) r.remove();
                });
                this.activeRegion = region;
                 region.element.classList.add('sparxstar_selected_region');
                document.querySelectorAll('#sparxstar-actions-panel .button').forEach(btn => btn.disabled = false);
            });


            // Hook up playback controls from your modal's HTML template
            $('#sparxstar_btn_play').off().on('click', () => this.ws.playPause());
            $('#sparxstar_btn_backward').off().on('click', () => this.ws.skip(-5)); // Example skip amount
            $('#sparxstar_btn_forward').off().on('click', () => this.ws.skip(5)); // Example skip amount
            
            // Example: if you have a zoom slider in your modal HTML
            $('#sparxstar_zoom_slider').off().on('input', (e) => this.ws.zoom(Number(e.target.value)));

            this.ws.on('play', () => { $('#sparxstar_btn_play').text('Pause'); }); // Update button text
            this.ws.on('pause', () => { $('#sparxstar_btn_play').text('Play'); });

            // Hook up Action Buttons (Save, Master, Export) from your modal
            $('#sparxstar-btn-save').off().on('click', () => this.processAction('save'));
            $('#sparxstar-btn-master').off().on('click', () => this.processAction('master'));
            $('#sparxstar-btn-export').off().on('click', () => this.processAction('export'));
            
            // Add region creation button if you don't do it automatically
            $('#sparxstar-btn-add-region').off().on('click', () => {
                if (this.ws.getDuration()) {
                     this.wsRegions.addRegion({
                        start: 0,
                        end: this.ws.getDuration() / 2, // Example: select first half
                        color: 'rgba(200, 0, 200, 0.2)',
                        drag: true,
                        resize: true
                    });
                }
            });
        },

        processAction: async function(actionType) {
            if (!this.activeRegion && actionType !== 'export_full') { // Allow full export without region
                // If action requires a region (like trim/save specific part)
                if (actionType === 'save' || actionType === 'master' || actionType === 'export') {
                     alert('Please select or create an audio region first.');
                     return;
                }
            }

            this.updateStatus('processing', 'Preparing audio...');
            let audioBlobToProcess;

            if (this.activeRegion && (actionType === 'save' || actionType === 'master' || actionType === 'export')) {
                // Get blob for the selected region
                const { blob } = await this.getRegionBlob(this.activeRegion);
                audioBlobToProcess = blob;
            } else if (this.originalFileObject && (actionType === 'export_full' || actionType === 'save_full' || actionType === 'master_full')) {
                // Use the originally loaded (and possibly cleaned) audio file for full actions
                // If originalCleanedBuffer was populated with a cleaned version, use that
                // audioBlobToProcess = this.originalCleanedBuffer ? bufferToWaveBlob(this.originalCleanedBuffer) : this.originalFileObject;
                
                // For simplicity, let's re-use originalFileObject, assuming it's what user expects for "full"
                audioBlobToProcess = this.originalFileObject;
            } else {
                alert('No audio data available for this action.');
                return;
            }


            this.updateStatus('processing', 'Scraping form & embedding metadata (if applicable)...');
            const metadata = this.scrapeACFForm(); // Scrape metadata from the main WP post edit page

            if (actionType.includes('save') || actionType.includes('master') || actionType === 'export') { // Embed for these actions
                if (!metadata.title && (actionType.includes('save') || actionType.includes('master')) ) { // Title required for server saving/mastering
                    this.updateStatus('error', 'The "Track Title" field (Post Title) cannot be empty when saving or mastering.');
                    return;
                }
                try {
                    if (typeof ID3Writer === 'undefined') {
                        this.updateStatus('warning', 'ID3Writer not loaded. Skipping metadata embedding.');
                    } else {
                        audioBlobToProcess = await this.embedMetadata(audioBlobToProcess, metadata);
                        this.updateStatus('info', 'Metadata embedded.');
                    }
                } catch(e) {
                     this.updateStatus('error', 'Error embedding metadata: ' + e.message);
                     return;
                }
            }
            
            this.updateStatus('processing', 'Handing off for processing...');
            
            // Use SparxATUploader
            if (window.SparxATUploader && typeof window.SparxATUploader.upload === 'function') {
                // Determine filename based on metadata, or fallback if metadata is sparse for export
                const filenamePrefix = (metadata.artist || 'UnknownArtist') + ' - ' + (metadata.title || 'UntitledTrack');
                let finalFilename = `${filenamePrefix}_${actionType}.mp3`; // Default to mp3 for tagged files
                if (actionType === 'export' || actionType === 'export_full') { // For client-side export, ensure .wav if original was wav
                    finalFilename = `${filenamePrefix}_export.${audioBlobToProcess.type === 'audio/wav' ? 'wav' : 'mp3'}`;
                }

                window.SparxATUploader.upload(audioBlobToProcess, metadata, actionType, finalFilename);
            } else {
                this.updateStatus('error', 'Uploader module (SparxATUploader) not found.');
                console.error('SparxATUploader is not defined.');
            }
        },

        scrapeACFForm: function() {
            // This function needs to reliably get values from the MAIN post edit page,
            // NOT from within the modal (unless you replicate fields there).
            const $acfFieldArtist = jQuery('body').find('.acf-field[data-key="field_678ebd83aacc9"] .acf-selection .selection-item'); // More specific selector
            return {
                title: jQuery('body').find('input#title').val() || 'Untitled Track', // WP Post Title
                artist: $acfFieldArtist.length ? $acfFieldArtist.text().replace('Remove', '').trim() : '',
                album: '', // Placeholder, as getting this is complex from typical forms
                copyright: jQuery('body').find('.acf-field[data-key="field_678e9ed978a92"] input[type="text"]').val() || '', // Adjust data-key
                year: jQuery('body').find('.acf-field[data-key="field_678e9fa878a93"] input[type="text"]').val() || '', // Adjust data-key
                isrc: jQuery('body').find('.acf-field[data-key="field_678e9c6e78a8b"] input[type="text"]').val() || '', // Adjust data-key
            };
        },

        embedMetadata: async function(audioBlob, metadata) {
            // Ensure ID3Writer is loaded
            if (typeof ID3Writer === 'undefined') {
                console.warn('ID3Writer library is not available. Skipping metadata embedding.');
                return audioBlob; // Return original blob if writer not found
            }
            const writer = new ID3Writer(await audioBlob.arrayBuffer());
            if (metadata.title) writer.setFrame('TIT2', metadata.title);
            if (metadata.artist) writer.setFrame('TPE1', [metadata.artist]);
            if (metadata.album) writer.setFrame('TALB', metadata.album);
            if (metadata.year) writer.setFrame('TYER', metadata.year);
            if (metadata.copyright) writer.setFrame('TCOP', metadata.copyright);
            if (metadata.isrc) writer.setFrame('TSRC', metadata.isrc);
            writer.addTag();
            return writer.getBlob(); // This will be an MP3 blob if tags are added
        },

        getRegionBlob: async function(region) { // Pass the region object
            if (!this.originalCleanedBuffer || !region) {
                throw new Error("Original audio buffer or region not available for slicing.");
            }
            const start = region.start;
            const end = region.end;
            const sampleRate = this.originalCleanedBuffer.sampleRate;
            const startSample = Math.floor(start * sampleRate);
            const endSample = Math.floor(end * sampleRate);

            if (endSample <= startSample) {
                 throw new Error("Region end time must be after start time.");
            }

            const trimmedBuffer = new AudioContext().createBuffer(
                this.originalCleanedBuffer.numberOfChannels,
                endSample - startSample,
                sampleRate
            );
            for (let i = 0; i < this.originalCleanedBuffer.numberOfChannels; i++) {
                trimmedBuffer.copyToChannel(
                    this.originalCleanedBuffer.getChannelData(i).slice(startSample, endSample),
                    i
                );
            }
            const blob = bufferToWaveBlob(trimmedBuffer); // Use the global helper
            return { blob };
        },

        updateStatus: function(type, message) {
            const $statusDiv = jQuery('#sparxstar-status-display'); // Ensure this ID matches your modal HTML
            if ($statusDiv.length) {
                $statusDiv.removeClass('sparxstar-status-info sparxstar-status-success sparxstar-status-error sparxstar-status-processing')
                          .addClass(`sparxstar-status-${type}`);
                $statusDiv.text(message).show();
            } else {
                console.log(`Status [${type}]: ${message}`);
            }
        },

        destroy: function() {
            if (this.ws) {
                this.ws.destroy();
                this.ws = null;
                this.wsRegions = null; // Also clear wsRegions
                this.activeRegion = null;
                this.originalCleanedBuffer = null;
                this.originalFileObject = null;
            }
            this.updateStatus('info', 'Editor closed.');
            // Disable buttons, clear waveform container content etc.
            jQuery('#sparxstar_waveform').empty(); // Clear waveform display
            jQuery('#sparxstar-actions-panel .button').prop('disabled', true); // Disable action buttons in modal
        }
    }; // End window.SparxATWaveform

    window.SparxATUploader = {
        upload: async function(audioBlob, metadata, actionType, finalFilenameToUse) {
            // Use finalFilenameToUse if provided, else construct one
            const finalFilename = finalFilenameToUse || `${metadata.artist || 'Artist'} - ${metadata.title || 'Untitled'}.mp3`;

            if (actionType === 'export' || actionType === 'export_full') { // Handle client-side download
                const url = URL.createObjectURL(audioBlob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = finalFilename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                if (window.SparxATWaveform) window.SparxATWaveform.updateStatus('success', 'Download started!');
                return;
            }
            
            // For 'save' and 'master', send to WordPress REST API
            const formData = new FormData();
            formData.append('action_type', actionType); // 'save' or 'master'
            formData.append('original_post_id', sparxstar_editor_ajax_params.post_id); // Ensure this localized var name
            formData.append('audio_blob', audioBlob, finalFilename); // Send the blob with filename
            formData.append('original_filename', finalFilename); // Send filename separately too for REST API to use

            try {
                // Ensure sparxstar_editor_ajax_params.nonce is a REST API nonce
                const response = await fetch('/wp-json/sparxstar/v1/update-track-audio', {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': sparxstar_editor_ajax_params.nonce },
                    body: formData,
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Server error during upload.');
                }

                if (window.SparxATWaveform) window.SparxATWaveform.updateStatus('success', data.message || 'Audio processed and saved successfully!');

                // If ACF JS API is available and field key is correct
                if (typeof acf !== 'undefined' && acf.getField) {
                    const acfField = acf.getField('field_678ea0f178a96'); // Your ACF field key
                    if (acfField && data.attachment_id) {
                        acfField.setValue(data.attachment_id); // Update ACF field in UI
                    }
                }
                
                // Optionally close modal
                setTimeout(() => {
                    jQuery('.sparxstar-close-modal-btn').click();
                }, 2500);

            } catch (error) {
                if (window.SparxATWaveform) window.SparxATWaveform.updateStatus('error', `Upload Failed: ${error.message}`);
                console.error("SparxATUploader Error:", error);
            }
        }
    }; // End window.SparxATUploader

})(); // End IIFE



/** OLD CODE
// Import required libraries from a CDN
import WaveSurfer from 'https://cdn.jsdelivr.net/npm/wavesurfer.js@7/dist/wavesurfer.esm.js';
import RegionsPlugin from 'https://cdn.jsdelivr.net/npm/wavesurfer.js@7/dist/plugins/regions.esm.js';
import TimelinePlugin from 'https://cdn.jsdelivr.net/npm/wavesurfer.js@7/dist/plugins/timeline.esm.js';
import * as Tone from 'https://cdn.jsdelivr.net/npm/tone@14.7.77/build/Tone.js';
import ID3Writer from 'https://cdn.jsdelivr.net/npm/browser-id3-writer@4.4.0/dist/browser-id3-writer.mjs';

// Expose the editor logic on the window object so the controller can call it.
window.SparxATWaveform = {
    // Properties to hold state
    ws: null,
    wsRegions: null,
    activeRegion: null,
    originalCleanedBuffer: null,

    /**
     * Initializes the entire editor UI and loads the audio.
     * @param {Blob} audioFileBlob The audio file to load.
     */
    /*init: async function(audioFileBlob) {
        // --- 1. Setup WaveSurfer Instance ---
        this.ws = WaveSurfer.create({
            container: '#sparxstar_waveform', // From your template file
            waveColor: '#C800C8',
            progressColor: '#640064',
            height: 128,
            minPxPerSec: 100,
            plugins: [TimelinePlugin.create({ height: 20 })],
        });
        this.wsRegions = this.ws.registerPlugin(RegionsPlugin.create());
        this.attachWsEvents();

        // --- 2. Clean and Load Audio ---
        this.updateStatus('processing', 'Cleaning audio & loading waveform...');
        try {
            const cleanedBlob = await this.cleanupAudio(audioFileBlob);
            this.originalCleanedBuffer = await this.blobToAudioBuffer(cleanedBlob);
            await this.ws.loadBlob(cleanedBlob);
            this.updateStatus('info', 'Load complete. Click a purple region to select it.');
        } catch (error) {
            this.updateStatus('error', `Error processing audio: ${error.message}`);
            console.error(error);
        }
    },

    /**
     * Attaches all necessary WaveSurfer and UI event listeners.
     */
   /* attachWsEvents: function() {
        this.ws.on('decode', (duration) => {
            this.findSoundRegions(this.ws.getDecodedData(), duration);
        });

        this.wsRegions.on('region-clicked', (region, e) => {
            e.stopPropagation();
            if (this.activeRegion) {
                this.activeRegion.element.classList.remove('sparxstar_selected_region');
            }
            this.activeRegion = region;
            region.element.classList.add('sparxstar_selected_region');
            document.querySelectorAll('.sparxstar-actions-panel .button').forEach(btn => btn.disabled = false);
            region.play();
        });

        // Hook up playback controls from the template
        $('#sparxstar_btn_play').off().on('click', () => this.ws.playPause());
        this.ws.on('play', () => { $('#sparxstar_btn_play').text('⏸️'); });
        this.ws.on('pause', () => { $('#sparxstar_btn_play').text('▶️'); });
        // etc. for other controls...
        
        // --- Hook up Action Buttons ---
        $('#sparxstar-btn-save').off().on('click', () => this.processAction('save'));
        $('#sparxstar-btn-master').off().on('click', () => this.processAction('master'));
        $('#sparxstar-btn-export').off().on('click', () => this.processAction('export'));
    },

    /**
     * Main handler for when an action button (Save, Master, Export) is clicked.
     * @param {string} actionType 'save', 'master', or 'export'.
     */
   /* processAction: async function(actionType) {
        if (!this.activeRegion) {
            alert('Please select a sound region first.');
            return;
        }
        
        this.updateStatus('processing', 'Step 1/3: Preparing audio clip...');
        const { blob: rawBlob } = await this.getRegionBlob();
        
        this.updateStatus('processing', 'Step 2/3: Scraping form & embedding metadata...');
        const metadata = this.scrapeACFForm();
        if (!metadata.title) {
            this.updateStatus('error', 'The "Track Title" field cannot be empty.');
            return;
        }
        
        const taggedBlob = this.embedMetadata(rawBlob, metadata);

        this.updateStatus('processing', 'Step 3/3: Handing off for processing...');
        
        // Hand off to the uploader module
        window.SparxATUploader.upload(taggedBlob, metadata, actionType);
    },

    /**
     * Scrapes data from the main ACF form on the page.
     * @returns {object} An object containing the track's metadata.
     */
   /* scrapeACFForm: function() {
        const artistPost = $('[data-key="field_678ebd83aacc9"]').find('.acf-selection .selection-item');
        return {
            title: $('input[name="post_title"]').val() || 'Untitled Track',
            artist: artistPost.length ? artistPost.text().replace('Remove', '').trim() : '',
            album: '', // This is harder to get, requires another lookup or pre-loaded data
            copyright: $('input[name="acf[field_678e9ed978a92]"]').val(),
            year: $('input[name="acf[field_678e9fa878a93]"]').val(),
            isrc: $('input[name="acf[field_678e9c6e78a8b]"]').val(),
        };
    },

    /**
     * Embeds ID3 tags into an audio blob.
     * @param {Blob} rawBlob The audio data.
     * @param {object} metadata The metadata to write.
     * @returns {Blob} A new blob with the ID3 tag embedded.
     */
   /* embedMetadata: async function(rawBlob, metadata) {
        const writer = new ID3Writer(await rawBlob.arrayBuffer());
        writer.setFrame('TIT2', metadata.title)
              .setFrame('TPE1', [metadata.artist]) // Artist
              .setFrame('TALB', metadata.album)   // Album
              .setFrame('TYER', metadata.year)     // Year
              .setFrame('TCOP', metadata.copyright) // Copyright
              .setFrame('TSRC', metadata.isrc);   // ISRC
        writer.addTag();
        return writer.getBlob();
    },

    /**
     * Extracts the selected audio region into a new Blob.
     * @returns {object} An object containing the new blob and a generated filename.
     */
   /* getRegionBlob: async function() {
        const start = this.activeRegion.start;
        const end = this.activeRegion.end;
        const sampleRate = this.originalCleanedBuffer.sampleRate;
        const startSample = Math.floor(start * sampleRate);
        const endSample = Math.floor(end * sampleRate);

        const trimmedBuffer = new AudioContext().createBuffer(
            this.originalCleanedBuffer.numberOfChannels,
            endSample - startSample,
            sampleRate
        );
        for (let i = 0; i < this.originalCleanedBuffer.numberOfChannels; i++) {
            trimmedBuffer.copyToChannel(
                this.originalCleanedBuffer.getChannelData(i).slice(startSample, endSample),
                i
            );
        }
        
        const filename = `sparxstar-clip.wav`;
        const blob = this.bufferToWaveBlob(trimmedBuffer);
        return { blob, filename };
    },

    updateStatus: function(type, message) {
        const $statusDiv = $('#sparxstar-status-display');
        $statusDiv.removeClass('sparxstar-status-info sparxstar-status-success sparxstar-status-error sparxstar-status-processing').addClass(`sparxstar-status-${type}`);
        $statusDiv.text(message).show();
    },

    destroy: function() {
        if (this.ws) {
            this.ws.destroy();
            this.ws = null;
            this.activeRegion = null;
        }
        // Clear status and disable buttons
        this.updateStatus('info', 'Editor closed.').hide();
        document.querySelectorAll('.sparxstar-actions-panel .button').forEach(btn => btn.disabled = true);
    },
    ws.on('decode', (duration) => {
        findSoundRegions(ws.getDecodedData(), duration);
    });

    // --- Playback Controls ---
    btnPlay.onclick = () => { ws.playPause(); };
    btnBackward.onclick = () => { ws.skip(-5); };
    btnForward.onclick = () => { ws.skip(5); };
    zoomSlider.oninput = (e) => ws.zoom(Number(e.target.value));
    speedSlider.oninput = (e) => {
        const speed = Number(e.target.value);
        ws.setPlaybackRate(speed);
        speedLabel.textContent = `${speed.toFixed(1)}x`;
    };
    ws.on('play', () => { btnPlay.textContent = '⏸️'; });
    ws.on('pause', () => { btnPlay.textContent = '▶️'; });

    // --- Region Logic ---
    function findSoundRegions(decodedData, duration) {
        wsRegions.clearRegions();
        const audioData = decodedData.getChannelData(0);
        const scale = duration / audioData.length;
        const minSilenceDuration = 0.2;
        let silentRegions = [];
        let start = 0, isSilent = false;

        // 1. Find silences
        for (let i = 0; i < audioData.length; i++) {
            if (Math.abs(audioData[i]) < 0.01 && !isSilent) {
                start = i; isSilent = true;
            } else if (Math.abs(audioData[i]) >= 0.01 && isSilent) {
                if ((i - start) * scale > minSilenceDuration) {
                    silentRegions.push({ start: start * scale, end: i * scale });
                }
                isSilent = false;
            }
        }
        
        // 2. Invert silences to get sound regions
        let lastEnd = 0;
        silentRegions.forEach(r => {
            if(r.start > lastEnd) {
                createSoundRegion(lastEnd, r.start);
            }
            lastEnd = r.end;
        });
        if(duration > lastEnd) {
            createSoundRegion(lastEnd, duration);
        }
    }

    function createSoundRegion(start, end) {
        wsRegions.addRegion({
            start: start,
            end: end,
            color: 'rgba(200, 0, 200, 0.2)',
            drag: false, resize: false,
            attributes: { class: 'sparxstar_sound_region' }
        });
    }

    wsRegions.on('region-clicked', (region, e) => {
        e.stopPropagation();

        // Remove highlight from previously selected region
        if (activeRegion) {
            activeRegion.element.classList.remove('sparxstar_selected_region');
        }

        // Highlight new region and enable export
        activeRegion = region;
        region.element.classList.add('sparxstar_selected_region');
        btnExport.disabled = false;
        
        region.play();
    });
    
    // --- Export Logic ---
    btnExport.onclick = () => {
        if (!activeRegion || !originalCleanedBuffer) return;

        const start = activeRegion.start;
        const end = activeRegion.end;
        const sampleRate = originalCleanedBuffer.sampleRate;

        // Calculate start and end samples
        const startSample = Math.floor(start * sampleRate);
        const endSample = Math.floor(end * sampleRate);
        const length = endSample - startSample;

        // Create a new buffer for the trimmed audio
        const trimmedBuffer = new AudioContext().createBuffer(
            originalCleanedBuffer.numberOfChannels,
            length,
            sampleRate
        );

        // Copy the data from the original buffer to the new one
        for (let i = 0; i < originalCleanedBuffer.numberOfChannels; i++) {
            trimmedBuffer.copyToChannel(
                originalCleanedBuffer.getChannelData(i).slice(startSample, endSample),
                i
            );
        }
        
        const wavBlob = bufferToWaveBlob(trimmedBuffer);
        const url = URL.createObjectURL(wavBlob);
        
        // Trigger download
        const a = document.createElement('a');
        a.href = url;
        a.download = `sparxstar-export-${start.toFixed(1)}s-${end.toFixed(1)}s.wav`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    // --- Helper Functions ---
    async function cleanupAudio(blob) {
        const url = URL.createObjectURL(blob);
        const gate = new Tone.Gate(-30, 0.2).toDestination();
        const compressor = new Tone.Compressor(-24, 12).connect(gate);
        const bufferDuration = (await new Tone.Player(url).load(url)).buffer.duration;
        const processedBuffer = await Tone.Offline(async ({ transport }) => {
            const player = new Tone.Player(url).connect(compressor);
            player.sync().start(0);
            transport.start();
        }, bufferDuration);
        URL.revokeObjectURL(url);
        return bufferToWaveBlob(processedBuffer);
    }
    
    async function blobToAudioBuffer(blob) {
        const arrayBuffer = await blob.arrayBuffer();
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        return audioContext.decodeAudioData(arrayBuffer);
    }

    function bufferToWaveBlob(buffer) {
        // (This function is the same as the previous response, omitted for brevity)
        // ... see previous response for the full bufferToWaveBlob code ...
        // It converts an AudioBuffer to a WAV file blob.
        const numOfChan = buffer.numberOfChannels, length = buffer.length * numOfChan * 2 + 44;
        const bufferArr = new ArrayBuffer(length), view = new DataView(bufferArr);
        const channels = []; let i, sample, offset = 0, pos = 0;
        setUint32(0x46464952); setUint32(length - 8); setUint32(0x45564157);
        setUint32(0x20746d66); setUint32(16); setUint16(1); setUint16(numOfChan);
        setUint32(buffer.sampleRate); setUint32(buffer.sampleRate * 2 * numOfChan);
        setUint16(numOfChan * 2); setUint16(16); setUint32(0x61746164);
        setUint32(length - pos - 4);
        for(i=0;i<buffer.numberOfChannels;i++)channels.push(buffer.getChannelData(i));
        while(pos<length){for(i=0;i<numOfChan;i++){sample=Math.max(-1,Math.min(1,channels[i][offset]));sample=(0.5+sample<0?sample*32768:sample*32767)|0;view.setInt16(pos,sample,true);pos+=2;}offset++;}
        return new Blob([view],{type:'audio/wav'});
        function setUint16(data){view.setUint16(pos,data,true);pos+=2;}
        function setUint32(data){view.setUint32(pos,data,true);pos+=4;}
    }

  

// --- DOM Elements with Prefixes ---
const fileInput = document.getElementById('sparxstar_audioFile');
const fileLabel = document.getElementById('sparxstar_fileLabel');
const zoomSlider = document.getElementById('sparxstar_zoomSlider');
const cutButton = document.getElementById('sparxstar_cutButton');
const waveformContainer = '#sparxstar_waveform';

// Guard against errors if the elements are not on the page
if (!fileInput || !waveformContainer) {
    console.log("Sparxstar Audio Tool elements not found. Exiting script.");
} else {
    // --- Wavesurfer Instance ---
    const ws = WaveSurfer.create({
      container: waveformContainer,
      waveColor: '#C800C8',
      progressColor: '#640064',
      height: 128,
      minPxPerSec: 50,
      plugins: [
        TimelinePlugin.create({ height: 20, primaryLabelInterval: 5 }),
        SpectrogramPlugin.create({
          labels: true,
          height: 100,
          scale: 'mel',
          frequencyMax: 8000,
          labelsColor: '#f0f0f0',
          labelsBackground: '#2a2a2a'
        }),
      ],
    });
    
    const wsRegions = ws.registerPlugin(RegionsPlugin.create());

    // Helper function to convert a Tone.Buffer into a WAV Blob
    function bufferToWaveBlob(buffer) {
        const numOfChan = buffer.numberOfChannels;
        const length = buffer.length * numOfChan * 2 + 44;
        const bufferArr = new ArrayBuffer(length);
        const view = new DataView(bufferArr);
        const channels = [];
        let i, sample;
        let offset = 0;
        let pos = 0;

        setUint32(0x46464952); // "RIFF"
        setUint32(length - 8);
        setUint32(0x45564157); // "WAVE"
        setUint32(0x20746d66); // "fmt "
        setUint32(16);
        setUint16(1);
        setUint16(numOfChan);
        setUint32(buffer.sampleRate);
        setUint32(buffer.sampleRate * 2 * numOfChan);
        setUint16(numOfChan * 2);
        setUint16(16);
        setUint32(0x61746164); // "data"
        setUint32(length - pos - 4);

        for (i = 0; i < buffer.numberOfChannels; i++) channels.push(buffer.getChannelData(i));

        while (pos < length) {
            for (i = 0; i < numOfChan; i++) {
                sample = Math.max(-1, Math.min(1, channels[i][offset]));
                sample = (0.5 + sample < 0 ? sample * 32768 : sample * 32767) | 0;
                view.setInt16(pos, sample, true);
                pos += 2;
            }
            offset++;
        }
        return new Blob([view], { type: 'audio/wav' });

        function setUint16(data) { view.setUint16(pos, data, true); pos += 2; }
        function setUint32(data) { view.setUint32(pos, data, true); pos += 4; }
    }

    async function cleanupAudio(blob) {
      const originalUrl = URL.createObjectURL(blob);
      const gate = new Tone.Gate(-30, 0.2).toDestination();
      const compressor = new Tone.Compressor(-24, 12).connect(gate);
      const bufferDuration = (await new Tone.Player(originalUrl).load(originalUrl)).buffer.duration;
      const processedBuffer = await Tone.Offline(async ({ transport }) => {
        const player = new Tone.Player(originalUrl).connect(compressor);
        player.sync().start(0);
        transport.start();
      }, bufferDuration);

      URL.revokeObjectURL(originalUrl);
      return bufferToWaveBlob(processedBuffer);
    }

    // --- Event Listeners ---
    fileInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      fileLabel.textContent = 'Processing & Cleaning...';
      cutButton.disabled = true;
      try {
        const cleanedBlob = await cleanupAudio(file);
        fileLabel.textContent = 'Loading Waveform...';
        await ws.loadBlob(cleanedBlob);
        fileLabel.textContent = `✅ Loaded: ${file.name}`;
      } catch (error) {
        console.error("Error processing audio:", error);
        fileLabel.textContent = `Error: ${error.message}`;
      }
    });

    ws.once('interaction', () => ws.play());
    zoomSlider.oninput = (e) => ws.zoom(Number(e.target.value));

    ws.on('decode', (duration) => {
      wsRegions.clearRegions();
      const data = ws.getDecodedData();
      const audioData = data.getChannelData(0);
      const scale = duration / audioData.length;
      const silentRegions = [];
      let start = 0, isSilent = false;
      for (let i = 0; i < audioData.length; i++) {
        const value = Math.abs(audioData[i]);
        if (value < 0.01 && !isSilent) {
          start = i; isSilent = true;
        } else if (value >= 0.01 && isSilent) {
          if ((i - start) * scale > 0.2) {
            silentRegions.push({ start: start * scale, end: i * scale });
          }
          isSilent = false;
        }
      }
      silentRegions.forEach((r) => wsRegions.addRegion({
        start: r.start, end: r.end, color: 'rgba(255, 0, 0, 0.1)', content: `Silence`, drag: false, resize: false
      }));
      if (wsRegions.getRegions().length >= 2) cutButton.disabled = false;
    });

    let activeRegion = null;
    wsRegions.on('region-clicked', (region, e) => {
      e.stopPropagation(); activeRegion = region; region.play();
    });
    ws.on('timeupdate', (t) => {
      if (activeRegion && t >= activeRegion.end) { ws.pause(); activeRegion = null; }
    });

    cutButton.addEventListener('click', () => {
      const regions = Object.values(wsRegions.getRegions()).sort((a, b) => a.start - b.start);
      if (regions.length >= 2) {
        ws.play(regions[0].end, regions[1].start);
      }
    });
}
} else {
    console.log("Sparxstar elements not found. Exiting.");
}
// END OF window.SparxATWaveform definition