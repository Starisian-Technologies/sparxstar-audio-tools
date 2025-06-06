// game-ui.js
window.AIWA_MandinkaQuiz = window.AIWA_MandinkaQuiz || {};

AIWA_MandinkaQuiz.GameUI = (function() {
    // Cache DOM elements that are frequently accessed
    const elements = {
        welcomeArea: document.getElementById('aiwa_welcome_area'),
        quizContent: document.getElementById('aiwa_quiz_content'),
        questionPrompt: document.getElementById('aiwa_question_prompt'),
        wordToTranslate: document.getElementById('aiwa_word_to_translate'),
        answerInput: document.getElementById('aiwa_answer_input'),
        submitButton: document.getElementById('aiwa_submit_answer'),
        nextButton: document.getElementById('aiwa_next_question'),
        speakButton: document.getElementById('aiwa_speak_word'),
        feedbackArea: document.getElementById('aiwa_feedback_area'),
        currentScore: document.getElementById('aiwa_current_score'),
        totalQuestions: document.getElementById('aiwa_total_questions'),
        currentStreak: document.getElementById('aiwa_current_streak'),
        progressBar: document.getElementById('aiwa_progress_bar')
        // Add other elements as needed
    };

    return {
        init: function() {
            // Check if all critical elements exist (optional, good for debugging)
            for (const key in elements) {
                if (!elements[key]) {
                    console.warn(`GameUI: Element with ID for '${key}' not found.`);
                }
            }
        },

        showWelcomeScreen: function() {
            if (elements.welcomeArea) elements.welcomeArea.style.display = 'block';
            if (elements.quizContent) elements.quizContent.style.display = 'none';
        },

        showQuizScreen: function() {
            if (elements.welcomeArea) elements.welcomeArea.style.display = 'none';
            if (elements.quizContent) elements.quizContent.style.display = 'block';
        },

        displayQuestion: function(word, promptText = "Translate:") {
            if (elements.wordToTranslate) elements.wordToTranslate.textContent = word;
            if (elements.questionPrompt) elements.questionPrompt.textContent = promptText;
            if (elements.answerInput) {
                elements.answerInput.value = '';
                elements.answerInput.disabled = false;
                elements.answerInput.focus();
            }
        },

        getUserAnswer: function() {
            return elements.answerInput ? elements.answerInput.value.trim() : '';
        },

        showFeedback: function(message, isCorrect, correctAnswerText = '') {
            if (!elements.feedbackArea) return;
            elements.feedbackArea.innerHTML = ''; // Clear previous feedback
            elements.feedbackArea.classList.remove('correct', 'incorrect');

            const feedbackTextSpan = document.createElement('span');
            feedbackTextSpan.className = 'feedback-text';
            feedbackTextSpan.textContent = message;
            elements.feedbackArea.appendChild(feedbackTextSpan);

            if (isCorrect) {
                elements.feedbackArea.classList.add('correct');
            } else {
                elements.feedbackArea.classList.add('incorrect');
                if (correctAnswerText) {
                    const correctAnswerSpan = document.createElement('span');
                    correctAnswerSpan.className = 'correct-answer-text';
                    correctAnswerSpan.textContent = `Correct: ${correctAnswerText}`;
                    elements.feedbackArea.appendChild(correctAnswerSpan);
                }
            }
        },

        clearFeedback: function() {
            if (!elements.feedbackArea) return;
            elements.feedbackArea.innerHTML = '';
            elements.feedbackArea.classList.remove('correct', 'incorrect');
        },

        updateScore: function(score, total) {
            if (elements.currentScore) elements.currentScore.textContent = score;
            if (elements.totalQuestions) elements.totalQuestions.textContent = total;
        },

        updateStreak: function(streak) {
            if (elements.currentStreak) {
                // Check for the fire emoji and handle its presence or absence
                const fireEmoji = elements.currentStreak.nextSibling && elements.currentStreak.nextSibling.nodeValue && elements.currentStreak.nextSibling.nodeValue.includes('🔥') ? ' 🔥' : '';
                elements.currentStreak.textContent = streak;
                // If you want to always ensure the emoji is there or not based on JS logic:
                // elements.currentStreak.parentNode.innerHTML = `Streak: <span id="aiwa_current_streak">${streak}</span> ${streak > 0 ? '🔥' : ''}`;

            }
        },

        updateProgressBar: function(current, total) {
            if (elements.progressBar && total > 0) {
                const percentage = (current / total) * 100;
                elements.progressBar.style.width = percentage + '%';
            } else if (elements.progressBar) {
                 elements.progressBar.style.width = '0%';
            }
        },

        showSubmitButton: function() {
            if (elements.submitButton) elements.submitButton.style.display = 'block';
            if (elements.nextButton) elements.nextButton.style.display = 'none';
            if (elements.answerInput) elements.answerInput.disabled = false;
        },

        showNextButton: function() {
            if (elements.submitButton) elements.submitButton.style.display = 'none';
            if (elements.nextButton) elements.nextButton.style.display = 'block';
            if (elements.answerInput) elements.answerInput.disabled = true; // Disable input when showing "Next"
            if (elements.nextButton) elements.nextButton.focus();
        },

        showSpeakButton: function(show = true) {
            if(elements.speakButton) elements.speakButton.style.display = show ? 'inline-block' : 'none';
        },

        resetQuizUI: function(totalQuestions = 0) {
            this.updateScore(0, totalQuestions);
            this.updateStreak(0);
            this.updateProgressBar(0, totalQuestions);
            this.clearFeedback();
            this.showSubmitButton(); // Or welcome screen based on flow
        }
        // Add more generic UI functions as needed
    };
})();