// MainQuizApp.js
window.AIWA_MandinkaQuiz = window.AIWA_MandinkaQuiz || {};

// You can optionally create shorter aliases if you use them frequently within this file,
// but it's often clearer to use the full namespace.
// const GameUI = AIWA_MandinkaQuiz.GameUI;
// const DictionaryManager = AIWA_MandinkaQuiz.DictionaryManager;
// const FlashcardsGame = AIWA_MandinkaQuiz.MandinkaFlashcardsGame;

document.addEventListener('DOMContentLoaded', function() {
    AIWA_MandinkaQuiz.GameUI.init();

    const startMandinkaButton = document.getElementById('aiwa_start_quiz');
    const startEnglishButton = document.getElementById('aiwa_start_quiz_eng');
    // ... other elements from GameUI.elements

    let numQuestionsPerRound = 10; // Configurable

    function setUILoadingState(isLoading, message = '', isError = false) {
        // ... (Similar to before, shows a loading message in aiwa_welcome_area or aiwa_feedback_area)
        // ... (Disables/Enables startMandinkaButton, startEnglishButton)
        if (AIWA_MandinkaQuiz.GameUI.elements.welcomeArea) {
            const loadingMsgId = 'aiwa_loading_message';
            let loadingMsgEl = document.getElementById(loadingMsgId);
            if (isLoading || message) {
                if (!loadingMsgEl) {
                    loadingMsgEl = document.createElement('p');
                    loadingMsgEl.id = loadingMsgId;
                    loadingMsgEl.setAttribute('aria-live', 'polite');
                    const quizControls = AIWA_MandinkaQuiz.GameUI.elements.welcomeArea.querySelector('#aiwa_quiz_controls');
                    if (quizControls) AIWA_MandinkaQuiz.GameUI.elements.welcomeArea.insertBefore(loadingMsgEl, quizControls);
                    else AIWA_MandinkaQuiz.GameUI.elements.welcomeArea.appendChild(loadingMsgEl);
                }
                loadingMsgEl.textContent = message || "Loading...";
                if (isError) loadingMsgEl.style.color = 'red'; else loadingMsgEl.style.color = '';
            } else if (loadingMsgEl) {
                loadingMsgEl.remove();
            }
        }
        if (startMandinkaButton) startMandinkaButton.disabled = isLoading;
        if (startEnglishButton) startEnglishButton.disabled = isLoading;
    }

    async function initializeApp() {
        setUILoadingState(true, "Loading dictionary, please wait...");
        let dictionaryPath = "dictionary_json/dictionary_mandinka.json"; // Fallback
        if (typeof mandinkaQuizData !== 'undefined' && mandinkaQuizData.dictionaryPath) {
            dictionaryPath = mandinkaQuizData.dictionaryPath;
        }

        try {
            const loaded = await AIWA_MandinkaQuiz.DictionaryManager.loadDictionary(dictionaryPath);
            if (loaded) {
                setUILoadingState(false, `Dictionary loaded (${AIWA_MandinkaQuiz.DictionaryManager.getDictionarySize()} entries). Ready to start!`);
                // Message will clear on next UI action or can be explicitly cleared
                setTimeout(() => {
                    if (document.getElementById('aiwa_loading_message')?.textContent.includes('Dictionary loaded')) {
                        setUILoadingState(false); // Clear "Dictionary loaded" message
                    }
                }, 3000);
            } else {
                setUILoadingState(false, "Failed to load dictionary.", true);
            }
        } catch (error) {
            setUILoadingState(false, "Error initializing dictionary. Please try again later.", true);
        }
    }

    function setupNewQuizSession(direction) {
        if (!AIWA_MandinkaQuiz.DictionaryManager.isDictionaryLoaded()) {
            AIWA_MandinkaQuiz.GameUI.showFeedback("Dictionary not ready...", false);
            return;
        }
        const wordsForRound = AIWA_MandinkaQuiz.DictionaryManager.getQuizWords(numQuestionsPerRound);
        if (wordsForRound.length === 0) {
            AIWA_MandinkaQuiz.GameUI.showFeedback("Could not get words for the quiz. Dictionary might be empty.", false);
            return;
        }

        const actualNumQuestions = AIWA_MandinkaQuiz.MandinkaFlashcardsGame.startNewRound(wordsForRound, direction);
        AIWA_MandinkaQuiz.GameUI.resetQuizUI(actualNumQuestions);
        AIWA_MandinkaQuiz.GameUI.showQuizScreen();
        displayNextQuestionInUI();
    }

    function displayNextQuestionInUI() {
        if (AIWA_MandinkaQuiz.MandinkaFlashcardsGame.isQuizOver()) {
            handleQuizEndInUI();
            return;
        }
        const questionData = AIWA_MandinkaQuiz.MandinkaFlashcardsGame.getCurrentQuestion();
        if (questionData) {
            AIWA_MandinkaQuiz.GameUI.displayQuestion(questionData.word, questionData.prompt);
            AIWA_MandinkaQuiz.GameUI.showSubmitButton();
            AIWA_MandinkaQuiz.GameUI.clearFeedback();
            AIWA_MandinkaQuiz.GameUI.updateProgressBar(AIWA_MandinkaQuiz.MandinkaFlashcardsGame.getCurrentQuestionIndex(), AIWA_MandinkaQuiz.MandinkaFlashcardsGame.getTotalQuestionsInRound());
            // GameUI.showSpeakButton(!!questionData.wordToSpeak); // If speak button implemented
        }
    }

    function handleSubmitAnswer() {
        const userAnswer = GameUI.getUserAnswer();
        if (userAnswer === '') return;

        const result = AIWA_MandinkaQuiz.MandinkaFlashcardsGame.checkAnswer(userAnswer);
        if (result) {
            GameUI.showFeedback(
                result.isCorrect ? "Correct!" : "Oops!",
                result.isCorrect,
                result.isCorrect ? '' : result.correctAnswer
            );
            AIWA_MandinkaQuiz.GameUI.updateScore(result.currentScore, AIWA_MandinkaQuiz.MandinkaFlashcardsGame.getTotalQuestionsInRound());
            AIWA_MandinkaQuiz.GameUI.updateStreak(result.currentStreak);
            AIWA_MandinkaQuiz.GameUI.showNextButton();

            if (AIWA_MandinkaQuiz.MandinkaFlashcardsGame.isQuizOver()) {
                if(AIWA_MandinkaQuiz.GameUI.elements.nextButton) AIWA_MandinkaQuiz.GameUI.elements.nextButton.textContent = "Show Results";
            }
        }
    }

    function handleQuizEndInUI() {
        AIWA_MandinkaQuiz.GameUI.showFeedback(`Quiz Finished! Score: ${AIWA_MandinkaQuiz.MandinkaFlashcardsGame.getScore()} / ${AIWA_MandinkaQuiz.MandinkaFlashcardsGame.getTotalQuestionsInRound()}`, true);
        if(AIWA_MandinkaQuiz.GameUI.elements.nextButton) {
            AIWA_MandinkaQuiz.GameUI.elements.nextButton.textContent = "Play Again?";
            const playAgainHandler = () => {
                AIWA_MandinkaQuiz.GameUI.showWelcomeScreen();
                AIWA_MandinkaQuiz.GameUI.elements.nextButton.removeEventListener('click', playAgainHandler); // Clean up
                // Re-attach original next button listener for next quiz round
                GAIWA_MandinkaQuiz.ameUI.elements.nextButton.addEventListener('click', handleNextButtonClick);
            };
            AIWA_MandinkaQuiz.GameUI.elements.nextButton.removeEventListener('click', handleNextButtonClick); // remove old
            AIWA_MandinkaQuiz.GameUI.elements.nextButton.addEventListener('click', playAgainHandler); // add new
        }
        if(AIWA_MandinkaQuiz.GameUI.elements.submitButton) AIWA_MandinkaQuiz.GameUI.elements.submitButton.style.display = 'none';
        if(AIWA_MandinkaQuiz.GameUI.elements.answerInput) AIWA_MandinkaQuiz.GameUI.elements.answerInput.disabled = true;
    }

    // Event Listeners
    if (startMandinkaButton) {
        startMandinkaButton.addEventListener('click', function() {
            setupNewQuizSession(this.dataset.direction || 'mandinka->english');
        });
    }
    // ... (similar for startEnglishButton)
    if (startEnglishButton) {
        startEnglishButton.addEventListener('click', function() {
            setupNewQuizSession(this.dataset.direction || 'english->mandinka');
        });
    }

    if (GameUI.elements.submitButton) {
        GameUI.elements.submitButton.addEventListener('click', handleSubmitAnswer);
    }
    if (AIWA_MandinkaQuiz.GameUI.elements.answerInput) {
        AIWA_MandinkaQuiz.GameUI.elements.answerInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter' && AIWA_MandinkaQuiz.GameUI.elements.submitButton.style.display !== 'none') {
                handleSubmitAnswer();
            }
        });
    }

    const handleNextButtonClick = () => {
        AIWA_MandinkaQuiz.MandinkaFlashcardsGame.nextQuestion(); // Advance game state
        if (AIWA_MandinkaQuiz.MandinkaFlashcardsGame.isQuizOver() && AIWA_MandinkaQuiz.GameUI.elements.nextButton.textContent === "Show Results") {
            handleQuizEndInUI();
        } else {
            displayNextQuestionInUI();
        }
    };
    if (AIWA_MandinkaQuiz.GameUI.elements.nextButton) {
        AIWA_MandinkaQuiz.GameUI.elements.nextButton.addEventListener('click', handleNextButtonClick);
    }
    // ... (Speak button listener if implemented, calling AIWA_MandinkaQuiz.GameUI.speak() and getting text from AIWA_MandinkaQuiz.MandinkaFlashcardsGame.getCurrentQuestion())

    // Initial App Setup
    AIWA_MandinkaQuiz.GameUI.showWelcomeScreen();
    // AIWA_MandinkaQuiz.GameUI.showSpeakButton(false);
    initializeApp(); // Load dictionary and update UI accordingly
});