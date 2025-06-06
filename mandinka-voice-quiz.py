import json
import random
import pyttsx3
import time
import string

# ---------------------------
# STOPWORDS for smarter matching
STOPWORDS = {
    "a", "an", "the", "and", "or", "but", "of", "to", "from", "is", "in",
    "on", "at", "by", "for", "with", "that", "this", "it", "as", "be",
    "not", "were", "where", "which", "these", "there", "their", "here"
}

def tokenize_and_filter(text):
    tokens = text.lower().translate(str.maketrans('', '', string.punctuation)).split()
    return [word for word in tokens if word not in STOPWORDS]

# ---------------------------
# Load dictionary
def load_mandinka_dict(json_path):
    with open(json_path, 'r', encoding='utf-8') as f:
        return json.load(f)

# ---------------------------
# Speak text
def speak(text, engine=None):
    if engine is None:
        engine = pyttsx3.init()
        engine.setProperty('rate', 140)
    engine.say(text)

# ---------------------------
# Voice-based flashcard quiz
def run_voice_quiz(dictionary_path, num_questions=5):
    try:
        data = load_mandinka_dict(dictionary_path)
    except Exception as e:
        print(f"Error loading dictionary: {e}")
        return

    quiz_items = random.sample(data, min(num_questions, len(data)))
    score = 0
    engine = pyttsx3.init()
    engine.setProperty('rate', 140)

    for entry in quiz_items:
        word = entry.get("mandinka_word", "")
        english_list = [t.get("english_word", "") for t in entry.get("english_translations", [])]
        english_combined = ', '.join(english_list)

        print(f"\nMandinka word: {word}")
        speak(f"Mandinka word: {word}", engine)
        engine.runAndWait()

        print(">> Awaiting your answer...")
        answer = input("What is the English meaning? (or type 'quit' to exit) ").strip().lower()
        if answer == "quit":
            break

        # Tokenized comparison
        user_words = tokenize_and_filter(answer)
        translation_word_lists = [tokenize_and_filter(e) for e in english_list]

        match_found = any(
            any(user_word in trans for user_word in user_words)
            for trans in translation_word_lists
        )

        if match_found:
            print("Correct!\n")
            speak("Correct!", engine)
            score += 1
        else:
            print(f"Incorrect. Correct answer: {english_combined}\n")
            speak(f"Incorrect. The correct answer was {english_combined}", engine)

        engine.runAndWait()
        time.sleep(1)

    print(f"\nFinal Score: {score}/{len(quiz_items)}")
    speak(f"You got {score} out of {len(quiz_items)} right", engine)
    engine.runAndWait()

# ---------------------------
# Entry point
def main():
    dictionary_path = "dictionary_json/dictionary_mandinka.json"
    num_questions = 5
    run_voice_quiz(dictionary_path, num_questions)

if __name__ == "__main__":
    main()
