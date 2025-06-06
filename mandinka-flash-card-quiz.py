import json
import random
import string

# Basic English stopwords — you can customize this list
STOPWORDS = {"a", "an", "the", "and", "or", "but", "of", "to", "from", "is", "in", "on", "at", "by", "for", "with", "that", "this", "it", "as", "be"}

def tokenize_and_filter(text):
    # Lowercase, remove punctuation, split
    tokens = text.lower().translate(str.maketrans('', '', string.punctuation)).split()
    return [word for word in tokens if word not in STOPWORDS]

def load_cards(json_path):
    with open(json_path, 'r', encoding='utf-8') as f:
        return json.load(f)

def run_flashcard_quiz(json_path, direction="mandinka->english", num_cards=10):
    data = load_cards(json_path)
    if not data or num_cards < 1:
        print("No cards available or invalid number of cards.")
        return

    cards = random.sample(data, min(num_cards, len(data)))
    correct = 0
    total = 0

    for i, entry in enumerate(cards, 1):
        mandinka = entry["mandinka_word"]
        english_list = [t["english_word"] for t in entry["english_translations"]]
        english = ', '.join(english_list)

        if direction == "mandinka->english":
            print(f"{i}. Mandinka: {mandinka}")
            guess = input("English: ").strip().lower()
            if guess == "quit":
                break

            user_words = tokenize_and_filter(guess)
            translation_word_lists = [tokenize_and_filter(e) for e in english_list]

            match_found = any(
                any(user_word in translation_words for user_word in user_words)
                for translation_words in translation_word_lists
            )

            if match_found:
                print("Correct!\n")
                correct += 1
            else:
                print(f"Answer: {english}\n")

        elif direction == "english->mandinka":
            print(f"{i}. English: {english}")
            guess = input("Mandinka: ").strip().lower()
            if guess == "quit":
                break

            user_words = tokenize_and_filter(guess)
            mandinka_words = tokenize_and_filter(mandinka)

            match_found = any(user_word in mandinka_words for user_word in user_words)

            if match_found:
                print("Correct!\n")
                correct += 1
            else:
                print(f"Answer: {mandinka}\n")

        else:
            print("Invalid direction. Use 'mandinka->english' or 'english->mandinka'.")
            return

        total = i
        print(f"Your score so far: {correct}/{total}")

    print(f"Final score: {correct}/{total if total else num_cards}")

# Example
if __name__ == "__main__":
    run_flashcard_quiz("dictionary_json/dictionary_mandinka.json", direction="mandinka->english", num_cards=5)
