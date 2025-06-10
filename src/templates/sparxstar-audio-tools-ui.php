<?php 
namespace SPARXSTAR\src\templates;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


?>

 <div class="sparxstar_waveform-container">
        <h1>Sparxstar Audio Editor</h1>
        
        <div class="sparxstar_upload-wrapper">
            <label for="sparxstar_audioFile" class="sparxstar_file-label" id="sparxstar_fileLabel">Click to Upload Audio</label>
            <input type="file" id="sparxstar_audioFile" accept="audio/*" />
        </div>
        
        <div id="sparxstar_waveform"></div>
        <p id="sparxstar_instructions" class="sparxstar_instructions">Upload an audio file to begin. The parts with sound will be highlighted. Click a sound region to select it.</p>

        <div class="sparxstar_controls_panel">
            <!-- Playback Controls -->
            <div class="sparxstar_control-group">
                <button id="sparxstar_btn_backward" title="Skip 5s back">⏪</button>
                <button id="sparxstar_btn_play" title="Play/Pause">▶️</button>
                <button id="sparxstar_btn_forward" title="Skip 5s forward">⏩</button>
            </div>

            <!-- Zoom and Speed -->
            <div class="sparxstar_control-group sparxstar_sliders">
                <label for="sparxstar_zoom">Zoom:</label>
                <input type="range" id="sparxstar_zoom" min="10" max="1000" value="100" />
                <label for="sparxstar_speed">Speed: <span id="sparxstar_speed_label">1.0x</span></label>
                <input type="range" id="sparxstar_speed" min="0.5" max="2" step="0.1" value="1" />
            </div>

            <!-- Action Button -->
            < class="sparxstar_control-group">
                 <button id="sparxstar_btn_export" disabled>Export Selected Sound</button>
                 <button id="sparxstar_btn_master" disabled>🎧 Master This Clip</button>
                 <button id="sparxstar_btn_save" disabled>Save Changes</button>
            </div>
        </div>
    </div>