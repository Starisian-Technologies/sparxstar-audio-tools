import csv
import json
import re

# --- IPA Generator ---
def generate_ipa(word):
    if not word or not isinstance(word, str):
        return ""

    word = word.strip().lower()

    word = word.replace("aa", "aː").replace("ee", "eː").replace("ii", "iː").replace("oo", "oː").replace("uu", "uː")
    word = word.replace("nj", "ndʒ").replace("nk", "ŋk").replace("ng", "ŋ").replace("ny", "ɲ")
    word = word.replace("np", "mp").replace("nm", "m").replace("nb", "b")
    word = word.replace("c", "tʃ").replace("j", "dʒ").replace("y", "j")

    return f"/{word}/"

# --- Simplified Pronunciation Generator ---
def generate_simplified(word):
    if not word or not isinstance(word, str):
        return ""

    word = word.strip().lower()

    ipa_to_simple = {
        "aa": "ahh", "ee": "ehh", "ii": "eee", "oo": "ooh", "uu": "ooh",
        "a": "ah", "e": "eh", "i": "ee", "o": "oh", "u": "oo",
        "ng": "ng", "ny": "ny", "nj": "nj", "c": "ch", "j": "j"
    }

    for ipa, simple in ipa_to_simple.items():
        word = word.replace(ipa, simple)

    syllables = re.split(r"(?=[aeiou])", word)
    return "-".join(s for s in syllables if s)

# --- Main Converter ---
def create_mandinka_json(csv_filepath, json_filepath):
    try:
        mandinka_entries = []

        with open(csv_filepath, 'r', encoding='utf-8') as csvfile:
            reader = csv.DictReader(csvfile)

            for row in reader:
                mandinka_word = row['Headerword'].strip().lower()

                # Generate if missing
                ipa = row.get('IPA Prnounciation', '').strip()
                simplified = row.get('Phonetic Pronunciation', '').strip()

                if not ipa:
                    ipa = generate_ipa(mandinka_word)

                if not simplified:
                    simplified = generate_simplified(mandinka_word)

                mandinka_entry = {
                    "mandinka_word": mandinka_word,
                    "pronunciation": {
                        "ipa": ipa,
                        "simplified": simplified
                    },
                    "part_of_speech": row.get('Part of Speech', 'UNKNOWN').strip(),
                    "english_translations": [],
                    "source": row.get('Source', '').strip(),
                    "country": row.get('Country', '').strip()
                }

                english_values = row.get('English Translation', '').strip()
                if not english_values:
                    print(f"Warning: No English Translation for '{mandinka_word}'")
                    continue

                english_glosses = [v.strip().rstrip(".") for v in english_values.split(",") if v.strip()]

                for translation in english_glosses:
                    example_sentences = []

                    sentence = row.get('Mandinka Sentence', '').strip()
                    if sentence:
                        example_sentences.append({
                            "sentence": sentence,
                            "sentence_ipa": row.get('Sentence IPA Pronouncation', 'Loading...').strip() or "Loading...",
                            "sentence_simplified": row.get('Sentence Phonetic Pronounciation', 'Loading...').strip() or "Loading..."
                        })

                    english_translation = {
                        "english_word": translation,
                        "relation": "multiple",
                        "example_sentences": example_sentences
                    }

                    mandinka_entry["english_translations"].append(english_translation)

                mandinka_entries.append(mandinka_entry)

        with open(json_filepath, 'w', encoding='utf-8') as jsonfile:
            json.dump(mandinka_entries, jsonfile, indent=4, ensure_ascii=False)

        print(f"JSON created with {len(mandinka_entries)} entries: {json_filepath}")
        return True

    except Exception as e:
        print(f"Error: {e}")
        return False

# Example usage
if __name__ == "__main__":
    csv_file = 'dictionary.csv'
    json_file = 'dictionary_json/mandinka/mandinka_dictionary.json'
    success = create_mandinka_json(csv_file, json_file)
    print("Done:", success)