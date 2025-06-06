<?php
/*
 * mandinka-flashcards-ui.php
 * Accessible Template for Mandinka Flashcard Quiz UI
 */
?>

<section id="aiwa_mandinka_quiz" aria-labelledby="aiwa_mandinka_quiz_heading">
    <h2 id="aiwa_mandinka_quiz_heading">Mandinka Flashcard Quiz</h2>

    <div id="aiwa_quiz_area" aria-live="polite" aria-atomic="true">
        <p id="aiwa_question_prompt" role="status" aria-live="polite"></p>

        <div>
            <label for="aiwa_word_to_translate" class="aiwa_sr_only">Word to Translate</label>
            <div id="aiwa_word_to_translate" lang="mnk" aria-describedby="aiwa_word_description"></div>
            <p id="aiwa_word_description" class="aiwa_sr_only">This is the word you need to translate.</p>
        </div>

        <label for="aiwa_answer_input">Your answer</label>
        <input type="text" id="aiwa_answer_input" name="answer" placeholder="Type your translation" aria-required="true">

        <div role="group" aria-label="Answer Actions">
            <button id="aiwa_submit_answer">Submit Answer</button>
            <button id="aiwa_speak_word" style="display:none;" aria-label="Replay Word Pronunciation">🔊 Replay Word</button>
        </div>
    </div>

    <div id="aiwa_feedback_area" aria-live="polite" role="region" tabindex="0"></div>

    <div id="aiwa_score_area" role="status" aria-live="polite">
        Score: <span id="aiwa_current_score">0</span> out of <span id="aiwa_total_questions">0</span>
    </div>

    <div id="aiwa_quiz_controls" role="group" aria-label="Quiz Controls">
        <button id="aiwa_start_quiz" data-direction="mandinka->english">Start Mandinka to English</button>
        <button id="aiwa_start_quiz_eng" data-direction="english->mandinka">Start English to Mandinka</button>
        <button id="aiwa_next_question" style="display:none;">Next Question</button>
    </div>
</section>
