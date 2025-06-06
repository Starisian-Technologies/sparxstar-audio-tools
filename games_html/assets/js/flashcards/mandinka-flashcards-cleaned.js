
(() => {
  const STOPWORDS = new Set([
    "a", "an", "the", "and", "or", "but", "of", "to", "from",
    "is", "in", "on", "at", "by", "for", "with", "that", "this",
    "it", "as", "be", "not", "were", "where", "which", "these", "there", "their", "here"
  ]);

  function tokenizeAndFilter(text) {
    if (!text) return [];
    const tokens = text.toLowerCase().replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g, "").split(/\s+/);
    return tokens.filter(word => word && !STOPWORDS.has(word));
  }

  const questionPromptEl = document.getElementById('aiwa_question_prompt');
  const wordToTranslateEl = document.getElementById('aiwa_word_to_translate');
  const answerInputEl = document.getElementById('aiwa_answer_input');
  const submitAnswerBtn = document.getElementById('aiwa_submit_answer');
  const speakWordBtn = document.getElementById('aiwa_speak_word');
  const feedbackAreaEl = document.getElementById('aiwa_feedback_area');
  const currentScoreEl = document.getElementById('aiwa_current_score');
  const totalQuestionsEl = document.getElementById('aiwa_total_questions');
  const startQuizBtn = document.getElementById('aiwa_start_quiz');
  const startQuizEngBtn = document.getElementById('aiwa_start_quiz_eng');
  const nextQuestionBtn = document.getElementById('aiwa_next_question');

  let dictionary = [];
  let currentQuizCards = [];
  let currentCardIndex = 0;
  let score = 0;
  let currentDirection = "mandinka->english";

  async function loadDictionary() {
    try {
      const response = await fetch(mfc_quiz_data.dictionary_url);
      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      dictionary = await response.json();
      console.log("Dictionary loaded:", dictionary.length, "entries");
      startQuizBtn.disabled = false;
      startQuizEngBtn.disabled = false;
    } catch (error) {
      console.error("Could not load dictionary:", error);
      if (feedbackAreaEl) feedbackAreaEl.textContent = "Error: Could not load dictionary data. Check console.";
      startQuizBtn.disabled = true;
      startQuizEngBtn.disabled = true;
    }
  }

  function speak(text, lang = 'en-US') {
    if ('speechSynthesis' in window) {
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = lang;
      window.speechSynthesis.speak(utterance);
    } else {
      console.warn("Speech synthesis not supported in this browser.");
    }
  }

  function startQuiz(numCards = 5, direction = "mandinka->english") {
    if (!dictionary || dictionary.length === 0) {
      feedbackAreaEl.textContent = "Dictionary not loaded or empty.";
      return;
    }
    currentDirection = direction;
    currentQuizCards = dictionary.sort(() => 0.5 - Math.random()).slice(0, Math.min(numCards, dictionary.length));
    currentCardIndex = 0;
    score = 0;
    updateScoreDisplay();
    totalQuestionsEl.textContent = currentQuizCards.length;

    document.getElementById('aiwa_quiz_area').style.display = 'block';
    startQuizBtn.style.display = 'none';
    startQuizEngBtn.style.display = 'none';
    nextQuestionBtn.style.display = 'none';
    submitAnswerBtn.style.display = 'inline-block';
    answerInputEl.disabled = false;
    speakWordBtn.style.display = 'inline-block';

    displayNextCard();
  }

  function displayNextCard() {
    if (currentCardIndex >= currentQuizCards.length) {
      endQuiz();
      return;
    }

    const entry = currentQuizCards[currentCardIndex];
    answerInputEl.value = "";
    answerInputEl.focus();
    feedbackAreaEl.textContent = "";
    submitAnswerBtn.disabled = false;
    nextQuestionBtn.style.display = 'none';
    submitAnswerBtn.style.display = 'inline-block';
    answerInputEl.disabled = false;

    const mandinkaWord = entry.mandinka_word;
    const englishTranslations = entry.english_translations.map(t => t.english_word);

    if (currentDirection === "mandinka->english") {
      questionPromptEl.textContent = "Mandinka word:";
      wordToTranslateEl.textContent = mandinkaWord;
      wordToTranslateEl.setAttribute("lang", "mnk");
      speak(`The Mandinka word is: ${mandinkaWord}`, 'en-US');
    } else {
      questionPromptEl.textContent = "English meaning:";
      wordToTranslateEl.textContent = englishTranslations.join(' / ');
      wordToTranslateEl.removeAttribute("lang");
      speak(`The English meaning is: ${englishTranslations.join(' or ')}`, 'en-US');
    }
  }

  function checkAnswer() {
    if (currentCardIndex >= currentQuizCards.length) return;

    const entry = currentQuizCards[currentCardIndex];
    const userAnswer = answerInputEl.value.trim();
    answerInputEl.disabled = true;
    submitAnswerBtn.disabled = true;

    let isCorrect = false;
    const userWords = tokenizeAndFilter(userAnswer);

    if (currentDirection === "mandinka->english") {
      const englishTranslations = entry.english_translations.map(t => t.english_word);
      const translationWordLists = englishTranslations.map(e => tokenizeAndFilter(e));

      isCorrect = translationWordLists.some(translationWords =>
        userWords.length > 0 && translationWords.length > 0 &&
        userWords.some(userWord => translationWords.includes(userWord))
      );
      const correctAnswerText = englishTranslations.join(', ');
      feedbackAreaEl.innerHTML = isCorrect ? "✅ Correct!" : `❌ Incorrect. Correct answer(s): <span class="correct-answer">${correctAnswerText}</span>`;
      speak(isCorrect ? "Correct!" : `Incorrect. The correct answer was ${correctAnswerText}`, 'en-US');
      if (isCorrect) score++;
    } else {
      const mandinkaWord = entry.mandinka_word;
      const mandinkaWords = tokenizeAndFilter(mandinkaWord);
      isCorrect = userWords.length > 0 && mandinkaWords.length > 0 &&
                  userWords.some(userWord => mandinkaWords.includes(userWord));
      feedbackAreaEl.innerHTML = isCorrect ? "✅ Correct!" : `❌ Incorrect. Correct answer: <span class="correct-answer">${mandinkaWord}</span>`;
      speak(isCorrect ? "Correct!" : `Incorrect. The correct answer was ${mandinkaWord}`, 'en-US');
      if (isCorrect) score++;
    }

    updateScoreDisplay();
    currentCardIndex++;
    if (currentCardIndex < currentQuizCards.length) {
      nextQuestionBtn.style.display = 'inline-block';
      submitAnswerBtn.style.display = 'none';
    } else {
      setTimeout(endQuiz, 2000);
    }
  }

  function updateScoreDisplay() {
    currentScoreEl.textContent = score;
  }

  function endQuiz() {
    questionPromptEl.textContent = "Quiz Finished!";
    wordToTranslateEl.textContent = `Your final score: ${score} / ${currentQuizCards.length}`;
    speak(`Quiz finished. You got ${score} out of ${currentQuizCards.length} correct.`, 'en-US');
    feedbackAreaEl.textContent = "";
    answerInputEl.style.display = 'none';
    submitAnswerBtn.style.display = 'none';
    speakWordBtn.style.display = 'none';
    nextQuestionBtn.style.display = 'none';
    startQuizBtn.style.display = 'inline-block';
    startQuizEngBtn.style.display = 'inline-block';
    document.getElementById('aiwa_quiz_area').style.display = 'none';
  }

  startQuizBtn.addEventListener('click', () => startQuiz(5, "mandinka->english"));
  startQuizEngBtn.addEventListener('click', () => startQuiz(5, "english->mandinka"));
  submitAnswerBtn.addEventListener('click', checkAnswer);
  answerInputEl.addEventListener('keypress', (event) => { if (event.key === 'Enter') checkAnswer(); });
  nextQuestionBtn.addEventListener('click', displayNextCard);
  speakWordBtn.addEventListener('click', () => {
    if (currentCardIndex < currentQuizCards.length) {
      const entry = currentQuizCards[currentCardIndex];
      if (currentDirection === "mandinka->english") {
        speak(`The Mandinka word is: ${entry.mandinka_word}`);
      } else {
        const englishTranslations = entry.english_translations.map(t => t.english_word);
        speak(`The English meaning is: ${englishTranslations.join(' or ')}`);
      }
    }
  });

  document.addEventListener('DOMContentLoaded', () => {
    startQuizBtn.disabled = true;
    startQuizEngBtn.disabled = true;
    document.getElementById('aiwa_quiz_area').style.display = 'none';
    loadDictionary();
  });
})();
