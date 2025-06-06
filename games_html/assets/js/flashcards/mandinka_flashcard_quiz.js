
import fs from 'fs';
import readline from 'readline';

// English stopwords
const STOPWORDS = new Set([
  "a", "an", "the", "and", "or", "but", "of", "to", "from",
  "is", "in", "on", "at", "by", "for", "with", "that", "this",
  "it", "as", "be", "not", "were", "where", "which", "these", "there", "their", "here"
]);

// (Placeholder for Mandinka stopwords — to be customized)
const MANDINKA_STOPWORDS = new Set();

function tokenizeAndFilter(text, lang = "en") {
  const STOP = lang === "en" ? STOPWORDS : MANDINKA_STOPWORDS;
  const tokens = text.toLowerCase().replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g, "").split(/\s+/);
  return tokens.filter(word => word && !STOP.has(word));
}

function loadCards(jsonPath) {
  try {
    const data = fs.readFileSync(jsonPath, 'utf-8');
    return JSON.parse(data);
  } catch (error) {
    console.error(`Error loading or parsing cards from ${jsonPath}:`, error.message);
    return null;
  }
}

async function ask(question, rl) {
  return new Promise(resolve => rl.question(question, answer => resolve(answer.trim().toLowerCase())));
}

async function runFlashcardQuiz(jsonPath, direction = "mandinka->english", numCards = 10) {
  const data = loadCards(jsonPath);
  if (!data || numCards < 1) {
    console.log("No cards available or invalid number of cards.");
    return;
  }

  console.log("📘 Starting flashcard quiz. Type 'quit' at any time to exit.\n");

  const cards = data.sort(() => 0.5 - Math.random()).slice(0, Math.min(numCards, data.length));
  let correct = 0;

  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
  });

  for (let i = 0; i < cards.length; i++) {
    const entry = cards[i];
    const mandinka = entry.mandinka_word;
    const englishList = entry.english_translations.map(t => t.english_word);
    const english = englishList.join(', ');

    let guess;
    if (direction === "mandinka->english") {
      console.log(`\n${i + 1}. Mandinka: ${mandinka}`);
      guess = await ask("English: ", rl);
      if (guess === "quit") break;

      const userWords = tokenizeAndFilter(guess, "en");
      if (userWords.length === 0) {
        console.log("❌ Too vague or empty response. Try to be more specific.\n");
        continue;
      }

      const translationWordLists = englishList.map(e => tokenizeAndFilter(e, "en"));
      const matchFound = translationWordLists.some(translationWords =>
        userWords.some(userWord => translationWords.includes(userWord))
      );

      if (matchFound) {
        console.log("✅ Correct!\n");
        correct++;
      } else {
        console.log(`❌ Answer: ${english}\n`);
      }

    } else if (direction === "english->mandinka") {
      console.log(`\n${i + 1}. English: ${english}`);
      guess = await ask("Mandinka: ", rl);
      if (guess === "quit") break;

      const userWords = tokenizeAndFilter(guess, "en");
      if (userWords.length === 0) {
        console.log("❌ Too vague or empty response. Try to be more specific.\n");
        continue;
      }

      const mandinkaWords = tokenizeAndFilter(mandinka, "en");
      const matchFound = userWords.some(userWord => mandinkaWords.includes(userWord));

      if (matchFound) {
        console.log("✅ Correct!\n");
        correct++;
      } else {
        console.log(`❌ Answer: ${mandinka}\n`);
      }

    } else {
      console.log("Invalid direction. Use 'mandinka->english' or 'english->mandinka'.");
      break;
    }

    console.log(`Score so far: ${correct}/${i + 1}`);
  }

  rl.close();
  console.log(`\n🎓 Final score: ${correct}/${cards.length}`);
}

// Accept command-line args: path, direction, count
const [,, jsonPathArg, directionArg, countArg] = process.argv;
const jsonPath = jsonPathArg || "dictionary_json/dictionary_mandinka.json";
const direction = directionArg || "mandinka->english";
const numCards = parseInt(countArg) || 5;

runFlashcardQuiz(jsonPath, direction, numCards);
