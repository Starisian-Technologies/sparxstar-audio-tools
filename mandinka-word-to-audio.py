import json
import os
import re
from gtts import gTTS

# Define paths
json_path = "dictionary_json/dictionary_mandinka.json"
output_dir = "mandinka-dictionary-audio"

# Ensure the output directory exists
os.makedirs(output_dir, exist_ok=True)

# Load dictionary data
with open(json_path, "r", encoding="utf-8") as f:
    data = json.load(f)

# Generate audio using 'en' voice for 5 entries
created_files = []
for entry in data[:5]:  # Change to data if you want all
    word = entry.get("mandinka_word")
    simplified = entry.get("pronunciation", {}).get("simplified")
    print(f"Processing: {word} | Pronunciation: {simplified}")  # Debug

    if word and simplified and isinstance(simplified, str) and simplified.strip():
        # Sanitize filename
        safe_word = re.sub(r'[\\/*?:"<>|]', "", word).replace(" ", "_")
        if not safe_word:
            continue

        filename = os.path.join(output_dir, f"{safe_word}.mp3")

        try:
            tts = gTTS(text=simplified, lang='en')  # Use 'en' for compatibility
            tts.save(filename)
            print(f"Created: {filename}")
            created_files.append(filename)
        except Exception as e:
            print(f"Error for {word}: {e}")

print("Done. Created files:", created_files)
