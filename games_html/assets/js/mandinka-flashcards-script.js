// MandinkaFlashcards.js
window.AIWA_MandinkaQuiz = window.AIWA_MandinkaQuiz || {};

AIWA_MandinkaQuiz.MandinkaFlashcardsGame = (function() {
    let currentQuizWords = []; // Only holds words for the current round
    let currentQuestionIndex = 0;
    let score = 0;
    let streak = 0;
    let quizDirection = 'mandinka->english';

    // Tokenizer and stopwords remain here as they're game-specific for answer checking
    const STOPWORDS = new Set([ /* ...your stopwords... */ ]);
    function tokenizeAndFilter(text) { /* ...your tokenizer... */ }

    // This is now called with a small, pre-selected list of words
    function startNewRound(wordListForRound, direction = 'mandinka->english') {
        if (!wordListForRound || wordListForRound.length === 0) {
            console.error("MandinkaFlashcardsGame: Cannot start round with empty word list.");
            currentQuizWords = [];
            return 0;
        }
        currentQuizWords = wordListForRound; // No need to shuffle again here if DictionaryManager did
        quizDirection = direction;
        currentQuestionIndex = 0;
        score = 0;
        streak = 0;
        return currentQuizWords.length;
    }

    // getCurrentQuestion, checkAnswer, nextQuestion, isQuizOver, etc., remain largely the same
    // but operate on the smaller `currentQuizWords`.
    function getCurrentQuestion() {
        if (currentQuestionIndex < currentQuizWords.length) {
            const entry = currentQuizWords[currentQuestionIndex];
            return {
                word: quizDirection === 'mandinka->english' ? entry.mandinka_word : entry.english_translations.map(t=>t.english_word).join(' / '),
                prompt: quizDirection === 'mandinka->english' ? "Translate this Mandinka word:" : "Translate this English word:",
                wordToSpeak: quizDirection === 'mandinka->english' ? entry.mandinka_word : entry.english_translations.map(t=>t.english_word).join(' or '),
                langToSpeak: quizDirection === 'mandinka->english' ? 'mnk' : 'en-US'
            };
        }
        return null;
    }

    function checkAnswer(userAnswer) {
        // ... (logic remains the same as your original, using tokenizeAndFilter)
        // but operates on `currentQuizWords[currentQuestionIndex]`
        if (currentQuestionIndex >= currentQuizWords.length) return null;
        const entry = currentQuizWords[currentQuestionIndex];
        // ... rest of your checkAnswer logic ...
        const userWords = tokenizeAndFilter(userAnswer);
        let isCorrect = false;
        let correctAnswerDisplay = "";

        if (quizDirection === 'mandinka->english') {
            const englishTranslations = entry.english_translations.map(t => t.english_word);
            correctAnswerDisplay = englishTranslations.join(', ');
            const translationWordLists = englishTranslations.map(e => tokenizeAndFilter(e));
            isCorrect = translationWordLists.some(translationWords =>
                userWords.length > 0 && translationWords.length > 0 &&
                userWords.some(userWord => translationWords.includes(userWord))
            );
        } else { // english->mandinka
            const mandinkaWord = entry.mandinka_word;
            correctAnswerDisplay = mandinkaWord;
            const mandinkaWords = tokenizeAndFilter(mandinkaWord);
            isCorrect = userWords.length > 0 && mandinkaWords.length > 0 &&
                        userWords.some(userWord => mandinkaWords.includes(userWord));
        }

        if (isCorrect) {
            score++;
            streak++;
        } else {
            streak = 0;
        }
        return {
            isCorrect: isCorrect,
            correctAnswer: correctAnswerDisplay,
            currentScore: score,
            currentStreak: streak
        };
    }

    function nextQuestion() { /* ... */ }
    function isQuizOver() { return currentQuestionIndex >= currentQuizWords.length; }
    function getTotalQuestionsInRound() { return currentQuizWords.length; }
    function getScore() { return score; }
    function getStreak() { return streak; }
    function getCurrentQuestionIndex() { return currentQuestionIndex; } // Renamed for clarity

    return {
        startNewRound: startNewRound,
        getCurrentQuestion: getCurrentQuestion,
        checkAnswer: checkAnswer,
        nextQuestion: nextQuestion,
        isQuizOver: isQuizOver,
        getTotalQuestionsInRound: getTotalQuestionsInRound,
        getScore: getScore,
        getStreak: getStreak,
        getCurrentQuestionIndex: getCurrentQuestionIndex
    };
})();