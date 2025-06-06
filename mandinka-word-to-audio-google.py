import json
import os
import re
from google.cloud import texttospeech

# This script uses Google Cloud Text-to-Speech to generate audio files
# for Mandinka words and their example sentences using IPA.

# --- Configuration ---
# Make sure you have the Google Cloud SDK installed and authenticated.
# Set this environment variable once in your shell before running:
# export GOOGLE_APPLICATION_CREDENTIALS="path/to/your-service-account.json"
# to run this script:
# export GOOGLE_APPLICATION_CREDENTIALS="path/to/your-service-account.json"
# You can also set it in your Python script using:
# $env:GOOGLE_APPLICATION_CREDENTIALS="D:\Google OAUTH\AiWA\aiwa-460700-d30754230348.json"

# & '..\Python\python.exe' mandinka-word-to-audio-google.py

json_path = "dictionary_json/dictionary_mandinka.json"  # Path to your main JSON file
output_dir_words = "mandinka-dictionary-google-tts/words"
output_dir_sentences = "mandinka-dictionary-google-tts/sentences"
voice_model = "en-US-Studio-Q"  # Example voice model, you can change this
word_count = 100  # Number of words to process, set to 0 for all
offset = 20  # Offset for processing, set to 0 for starting from the beginning
# Ensure output directories exist
os.makedirs(output_dir_words, exist_ok=True)
os.makedirs(output_dir_sentences, exist_ok=True)

# --- Google Cloud TTS Client Initialization ---
try:
    client = texttospeech.TextToSpeechClient()
except Exception as e:
    print(f"Failed to initialize Google Cloud TextToSpeechClient: {e}")
    print("Ensure GOOGLE_APPLICATION_CREDENTIALS is set correctly and you have authenticated.")
    exit()

voice = texttospeech.VoiceSelectionParams(
    #language_code="en-US",  # Using en-US as base, IPA will override pronunciation
                            # You can experiment with other English voices (e.g., en-KE, en-GB)
                            # to see if one handles the IPA phonemes better.
    #name="en-US-Wavenet-D"  # Example WaveNet voice
    # If you used "en-KE-Wavenet-B" before and liked it, feel free to use that:
    language_code="en-US",
    name= voice_model,
)

audio_config = texttospeech.AudioConfig(
    audio_encoding=texttospeech.AudioEncoding.MP3
)

# --- Helper Functions ---
def sanitize_filename(name_part):
    file = f"{name_part}_{voice_model}"
    """Removes invalid characters for filenames and replaces spaces."""
    s = re.sub(r'[\\/*?:"<>|]', "", file)
    s = s.replace(" ", "_")
    # Truncate if too long to prevent OS errors on filename length
    return s[:100] # Max 100 chars for this part of the filename

def clean_ipa(ipa_string):
    """Removes leading/trailing slashes from IPA strings."""
    if ipa_string and ipa_string.startswith('/') and ipa_string.endswith('/'):
        return ipa_string[1:-1]
    return ipa_string

def generate_audio(text_to_speak, ipa_transcription, output_filename, is_sentence=False):
    """Generates SSML and synthesizes audio."""
    cleaned_ipa = clean_ipa(ipa_transcription)
    if not cleaned_ipa:
        print(f"Skipping '{text_to_speak[:50]}...' due to missing cleaned IPA.")
        return False

    # The text within <phoneme> is the orthographic text the IPA applies to.
    ssml = f"""<speak><phoneme alphabet="ipa" ph="{cleaned_ipa}">{text_to_speak}</phoneme></speak>"""
    input_text = texttospeech.SynthesisInput(ssml=ssml)

    try:
        response = client.synthesize_speech(
            input=input_text, voice=voice, audio_config=audio_config
        )
        with open(output_filename, "wb") as out:
            out.write(response.audio_content)
        print(f"Saved: {output_filename}")
        return True
    except Exception as e:
        print(f"Error for '{text_to_speak[:50]}...': {e}")
        return False

# --- Main Processing Logic ---
print(f"Loading JSON data from: {json_path}")
try:
    with open(json_path, "r", encoding="utf-8") as f:
        data = json.load(f)
    print(f"Successfully loaded {len(data)} entries.")
except FileNotFoundError:
    print(f"JSON file not found at {json_path}")
    exit()
except json.JSONDecodeError:
    print(f"Error decoding JSON from {json_path}")
    exit()


# Process a slice of the data for testing (e.g., first 2 entries)
# Remove or adjust `[:2]` to process the whole file
if word_count == 0:
    entries_to_process = data[offset:]  # Process all entries from the offset
else:
    if offset > 0:
        print(f"Warning: Offset is set to {offset}, but word_count is not 0. Only the first {word_count} entries will be processed.")
    # Process a limited number of entries
    # Adjust the slice to include the offset
    if offset < len(data):
        entries_to_process = data[offset:offset + word_count]
    else:
        print(f"Offset {offset} exceeds the number of entries. Processing from the beginning.")
        entries_to_process = data[:word_count]
        # Process from the beginning if offset is out of range
        
print(f"Processing {len(entries_to_process)} entries starting from offset {offset}.")
for entry_index, entry in enumerate(entries_to_process):
    print(f"\n--- Processing Entry {entry_index + 1}/{len(entries_to_process)} ---")
    mandinka_word = entry.get("mandinka_word")
    word_pronunciation_info = entry.get("pronunciation", {})
    word_ipa = word_pronunciation_info.get("ipa")

    if not mandinka_word:
        print("Skipping entry due to missing 'mandinka_word'.")
        continue

    # 1. Generate audio for the Mandinka word itself
    if word_ipa:
        safe_word_filename_part = sanitize_filename(mandinka_word)
        word_audio_filename = os.path.join(output_dir_words, f"{safe_word_filename_part}.mp3")
        print(f"Attempting to generate audio for word: '{mandinka_word}' (IPA: {word_ipa})")
        generate_audio(mandinka_word, word_ipa, word_audio_filename)
    else:
        print(f"No IPA found for word: '{mandinka_word}'.")

    # 2. Generate audio for example sentences
    english_translations = entry.get("english_translations", [])
    sentence_counter_for_entry = 0
    processed_sentences_in_entry = set() # To avoid duplicate sentence processing within the same entry

    for trans_index, translation in enumerate(english_translations):
        example_sentences = translation.get("example_sentences", [])
        for sent_index, ex_sentence_info in enumerate(example_sentences):
            sentence_text = ex_sentence_info.get("sentence")
            sentence_ipa = ex_sentence_info.get("sentence_ipa")

            if sentence_text and sentence_ipa:
                # Create a unique key for the sentence to avoid reprocessing if it's identical
                sentence_key = (sentence_text, sentence_ipa)
                if sentence_key in processed_sentences_in_entry:
                    # print(f"  Skipping already processed sentence: '{sentence_text[:30]}...'")
                    continue
                
                processed_sentences_in_entry.add(sentence_key)
                sentence_counter_for_entry += 1

                # Create a unique filename for the sentence
                # Using word + sentence index to ensure uniqueness
                base_fn = sanitize_filename(mandinka_word)
                sentence_filename = os.path.join(
                    output_dir_sentences,
                    f"{base_fn}_sentence_{sentence_counter_for_entry}.mp3"
                )
                print(f"  Attempting to generate audio for sentence: '{sentence_text[:50]}...' (IPA: {sentence_ipa})")
                generate_audio(sentence_text, sentence_ipa, sentence_filename, is_sentence=True)
            elif sentence_text:
                print(f"  No IPA found for sentence: '{sentence_text[:50]}...'.")

print("\n--- Processing Complete ---")