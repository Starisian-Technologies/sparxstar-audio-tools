// Original:
// loadDictionary("dictionary_json/dictionary_mandinka.json"); // In DOMContentLoaded
// const response = await fetch(jsonPath); // Inside loadDictionary

// Change it to use the localized data:
async function loadDictionary() { // Remove jsonPath parameter
  try {
    // Access the URL passed from PHP via wp_localize_script
    // The object name is 'mfc_quiz_data' and the property is 'dictionary_url'
    const response = await fetch(mfc_quiz_data.dictionary_url);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    dictionary = await response.json();
    console.log("Dictionary loaded:", dictionary.length, "entries");
    startQuizBtn.disabled = false;
    startQuizEngBtn.disabled = false;
  } catch (error) {
    console.error("Could not load dictionary:", error);
    if (feedbackAreaEl) { // Check if element exists
        feedbackAreaEl.textContent = "Error: Could not load dictionary data. Check console.";
    }
    if (startQuizBtn) startQuizBtn.disabled = true;
    if (startQuizEngBtn) startQuizEngBtn.disabled = true;
  }
}

// --- Stopwords and Tokenizer (can be reused directly) ---
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

// --- DOM Elements ---
const questionPromptEl = document.getElementById('question-prompt');
const wordToTranslateEl = document.getElementById('word-to-translate');
const answerInputEl = document.getElementById('answer-input');
const submitAnswerBtn = document.getElementById('submit-answer');
const speakWordBtn = document.getElementById('speak-word');
const feedbackAreaEl = document.getElementById('feedback-area');
const currentScoreEl = document.getElementById('current-score');
const totalQuestionsEl = document.getElementById('total-questions');
const startQuizBtn = document.getElementById('start-quiz');
const startQuizEngBtn = document.getElementById('start-quiz-eng');
const nextQuestionBtn = document.getElementById('next-question');


// --- Global Quiz State ---
let dictionary = [];
let currentQuizCards = [];
let currentCardIndex = 0;
let score = 0;
let currentDirection = "mandinka->english"; // Default

// --- Load Dictionary ---
async function loadDictionary(jsonPath) {
  try {
    const response = await fetch(jsonPath);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    dictionary = await response.json();
    console.log("Dictionary loaded:", dictionary.length, "entries");
    // Enable start buttons once data is loaded
    startQuizBtn.disabled = false;
    startQuizEngBtn.disabled = false;
  } catch (error) {
    console.error("Could not load dictionary:", error);
    feedbackAreaEl.textContent = "Error: Could not load dictionary data. Please check the console.";
    startQuizBtn.disabled = true;
    startQuizEngBtn.disabled = true;
  }
}

// --- Text-to-Speech (Web Speech API) ---
function speak(text, lang = 'en-US') { // Default to English, Mandinka might not be supported
  if ('speechSynthesis' in window) {
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = lang;
    // Try to find a Mandinka voice if possible, otherwise default
    if (lang.toLowerCase().startsWith('mnk') || text.includes("Mandinka")) { // Heuristic
        // You might need to check available voices: speechSynthesis.getVoices()
        // and select one that supports Mandinka if available.
        // For now, let's assume the default voice or user's preferred voice for the language.
        // A specific Mandinka TTS engine is unlikely to be standard in browsers.
        // Often, 'say' on Mac uses a generic voice for unrecognised languages.
        // We can try to get an English voice to pronounce Mandinka phonetically.
    }
    window.speechSynthesis.speak(utterance);
  } else {
    console.warn("Speech synthesis not supported in this browser.");
  }
}

// --- Quiz Logic ---
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

  document.getElementById('quiz-area').style.display = 'block';
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
    speak(`The Mandinka word is: ${mandinkaWord}`, 'en-US'); // Or try 'mnk' if a voice exists
  } else { // english->mandinka
    questionPromptEl.textContent = "English meaning:";
    wordToTranslateEl.textContent = englishTranslations.join(' / ');
    speak(`The English meaning is: ${englishTranslations.join(' or ')}`, 'en-US');
  }
}

function checkAnswer() {
  if (currentCardIndex >= currentQuizCards.length) return; // Quiz already ended

  const entry = currentQuizCards[currentCardIndex];
  const userAnswer = answerInputEl.value.trim();
  answerInputEl.disabled = true;
  submitAnswerBtn.disabled = true; // Prevent multiple submissions

  let isCorrect = false;
  const userWords = tokenizeAndFilter(userAnswer);

  if (currentDirection === "mandinka->english") {
    const englishTranslations = entry.english_translations.map(t => t.english_word);
    const translationWordLists = englishTranslations.map(e => tokenizeAndFilter(e));

    isCorrect = translationWordLists.some(translationWords =>
      userWords.length > 0 && translationWords.length > 0 && // Ensure there are words to compare
      userWords.some(userWord => translationWords.includes(userWord))
    );
    const correctAnswerText = englishTranslations.join(', ');
    if (isCorrect) {
      feedbackAreaEl.innerHTML = "✅ Correct!";
      speak("Correct!", 'en-US');
      score++;
    } else {
      feedbackAreaEl.innerHTML = `❌ Incorrect. Correct answer(s): <span class="correct-answer">${correctAnswerText}</span>`;
      speak(`Incorrect. The correct answer was ${correctAnswerText}`, 'en-US');
    }
  } else { // english->mandinka
    const mandinkaWord = entry.mandinka_word;
    const mandinkaWords = tokenizeAndFilter(mandinkaWord);

    isCorrect = userWords.length > 0 && mandinkaWords.length > 0 &&
                userWords.some(userWord => mandinkaWords.includes(userWord));

    if (isCorrect) {
      feedbackAreaEl.innerHTML = "✅ Correct!";
      speak("Correct!", 'en-US');
      score++;
    } else {
      feedbackAreaEl.innerHTML = `❌ Incorrect. Correct answer: <span class="correct-answer">${mandinkaWord}</span>`;
      speak(`Incorrect. The correct answer was ${mandinkaWord}`, 'en-US');
    }
  }

  updateScoreDisplay();
  currentCardIndex++;

  if (currentCardIndex < currentQuizCards.length) {
    nextQuestionBtn.style.display = 'inline-block';
    submitAnswerBtn.style.display = 'none';
  } else {
    nextQuestionBtn.style.display = 'none'; // No more questions
    submitAnswerBtn.style.display = 'none';
    setTimeout(endQuiz, 2000); // Show final score after a delay
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


  startQuizBtn.style.display = 'inline-block'; // Allow restarting
  startQuizEngBtn.style.display = 'inline-block';
  document.getElementById('quiz-area').style.display = 'none';
}

// --- Event Listeners ---
startQuizBtn.addEventListener('click', () => startQuiz(5, "mandinka->english"));
startQuizEngBtn.addEventListener('click', () => startQuiz(5, "english->mandinka"));

submitAnswerBtn.addEventListener('click', checkAnswer);
answerInputEl.addEventListener('keypress', (event) => {
  if (event.key === 'Enter') {
    checkAnswer();
  }
});

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


// --- Initial Load ---
document.addEventListener('DOMContentLoaded', () => {
  // Disable start buttons until dictionary is loaded
  startQuizBtn.disabled = true;
  startQuizEngBtn.disabled = true;
  document.getElementById('quiz-area').style.display = 'none'; // Hide quiz area initially
  loadDictionary("dictionary_json/dictionary_mandinka.json"); // Adjust path if needed
});
// In your DOMContentLoaded event listener, call loadDictionary without arguments:
document.addEventListener('DOMContentLoaded', () => {
  // ... (get other elements)
  // Ensure DOM elements are found before trying to use them
  // e.g., const startQuizBtn = document.getElementById('start-quiz');
  // (you already have these definitions globally, just ensure they run after DOM is ready)

  // Disable start buttons until dictionary is loaded
  if (startQuizBtn) startQuizBtn.disabled = true;
  if (startQuizEngBtn) startQuizEngBtn.disabled = true;
  if (document.getElementById('quiz-area')) {
      document.getElementById('quiz-area').style.display = 'none';
  }

  loadDictionary(); // Call it without arguments
});