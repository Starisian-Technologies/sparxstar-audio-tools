from collections import defaultdict
import json

def reverse_mandinka_dictionary(input_json_path, output_json_path):
    with open(input_json_path, 'r', encoding='utf-8') as infile:
        mandinka_entries = json.load(infile)

    english_map = defaultdict(list)

    for entry in mandinka_entries:
        mandinka_word = entry["mandinka_word"]
        ipa = entry["pronunciation"]["ipa"]
        simplified = entry["pronunciation"]["simplified"]

        for translation in entry.get("english_translations", []):
            raw_gloss = translation.get("english_word", "").strip()
            gloss_key = raw_gloss.strip(' "\'“”‘’.').lower()

            # ✅ Group by whatever gloss exists — even junk — but don’t combine unrelated words
            if not gloss_key:
                continue

            # Only take this gloss’s sentences
            example_sentences = translation.get("example_sentences", [])

            english_map[gloss_key].append({
                "mandinka_word": mandinka_word,
                "ipa": ipa,
                "simplified": simplified,
                "example_sentences": example_sentences
            })

    with open(output_json_path, 'w', encoding='utf-8') as outfile:
        json.dump([
            {"english_word": k, "mandinka_words": v}
            for k, v in sorted(english_map.items())
        ], outfile, indent=4, ensure_ascii=False)

    print(f"✅ Reverse dictionary created with {len(english_map)} entries → {output_json_path}")
if __name__ == "__main__":
    input_json_path = 'dictionary_json/mandinka/mandinka_dictionary.json'
    output_json_path = 'dictionary_json/english/english_to_mandinka.json'
    
    reverse_mandinka_dictionary(input_json_path, output_json_path)