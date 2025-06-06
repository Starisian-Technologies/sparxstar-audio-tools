<?php
/*
 * mandinka-flashcards-ui.php
 * Accessible Template for Mandinka Flashcard Quiz UI
 */
?>

<section id="aiwa_mandinka_quiz" aria-labelledby="aiwa_mandinka_quiz_heading">
    <h2 id="aiwa_mandinka_quiz_heading">Mandinka Flashcard Quiz</h2>

    <!-- Optional: Welcome/Start Screen Area -->
    <div id="aiwa_welcome_area">
        <p>Select your quiz mode to begin!</p>
        <div id="aiwa_quiz_controls" role="group" aria-label="Quiz Controls">
            <button id="aiwa_start_quiz" class="aiwa_start_button" data-direction="mandinka->english">Mandinka to English</button>
            <button id="aiwa_start_quiz_eng" class="aiwa_start_button" data-direction="english->mandinka">English to Mandinka</button>
        </div>
    </div>

    <!-- Main Quiz Area - Initially hidden -->
    <div id="aiwa_quiz_content" style="display:none;">
        <div id="aiwa_quiz_area" aria-live="polite" aria-atomic="true">
            <p id="aiwa_question_prompt" role="status" aria-live="polite"></p>

            <div class="aiwa_word_card">
                <label for="aiwa_word_to_translate" class="aiwa_sr_only">Word to Translate</label>
                <div id="aiwa_word_to_translate" lang="mnk" aria-describedby="aiwa_word_description"></div>
                <p id="aiwa_word_description" class="aiwa_sr_only">This is the word you need to translate.</p>
            </div>

            <div class="aiwa_input_group">
                <label for="aiwa_answer_input">Your answer:</label>
                <input type="text" id="aiwa_answer_input" name="answer" placeholder="Type your translation" aria-required="true">
            </div>

            <div class="aiwa_action_buttons" role="group" aria-label="Answer Actions">
                <button id="aiwa_submit_answer">Submit</button>
                <button id="aiwa_next_question" style="display:none;">Next Question</button>
                <button id="aiwa_speak_word" style="display:none;" aria-label="Replay Word Pronunciation">🔊 <span class="aiwa_sr_only">Replay Word</span></button>
            </div>
        </div>

        <div id="aiwa_feedback_area" aria-live="polite" role="region" tabindex="0">
            <!-- Feedback will be injected here by JS -->
        </div>

        <div id="aiwa_progress_and_score">
            <div id="aiwa_score_area" role="status" aria-live="polite">
                Score: <span id="aiwa_current_score">0</span> / <span id="aiwa_total_questions">0</span>
            </div>
            <div id="aiwa_streak_area" role="status" aria-live="polite">
                Streak: <span id="aiwa_current_streak">0</span> 🔥
            </div>
            <!-- Optional: Progress Bar -->
            <div id="aiwa_progress_bar_container">
                <div id="aiwa_progress_bar"></div>
            </div>
        </div>
    </div>
</section>