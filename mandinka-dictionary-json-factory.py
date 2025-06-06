# (All the imports and constants from before remain the same)
import os
import argparse
import logging
import json
import csv
import re
from pathlib import Path
from typing import Callable, Dict, List, Any

# --- Configuration & Constants ---
MODE_MANDINKA = "mandinka"
MODE_ENGLISH = "english"
SUPPORTED_MODES = [MODE_MANDINKA, MODE_ENGLISH]
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')
logger = logging.getLogger(__name__)


# --- NEW: Intelligent English Gloss Extractor ---
def extract_english_glosses(raw_text: str) -> List[str]:
    """
    Parses a complex string from the 'translation and sentences' column
    to extract plausible English headwords.
    """
    if not raw_text or raw_text.strip().lower() == "#n/a":
        return []

    # 1. Split by comma for top-level entries
    top_level_chunks = raw_text.split(',')
    
    cleaned_glosses = []

    for chunk in top_level_chunks:
        processed_chunk = chunk.strip().lower()
        if not processed_chunk:
            continue

        # 2. Heuristic: If a chunk contains a period, assume the real gloss
        # is the text *after the last period*. This targets cases like:
        # "mandinka sentence. english translation"
        last_dot_index = processed_chunk.rfind('.')
        if last_dot_index != -1:
            # Take the part after the last dot as the most likely candidate
            gloss_candidate = processed_chunk[last_dot_index + 1:].strip()
        else:
            # If no dot, the whole chunk is the candidate
            gloss_candidate = processed_chunk

        # 3. Final cleanup: remove quotes and other noise.
        # This handles cases like '"findoo"' or '"waist tying"'
        gloss_candidate = gloss_candidate.replace('"', '').replace("'", "").strip()

        # 4. Add to list if it's a meaningful string
        if gloss_candidate:
            cleaned_glosses.append(gloss_candidate)
            
    # Return a list of unique, non-empty glosses
    return sorted(list(set(g for g in cleaned_glosses if g)))


# --- (IPA/Simplified generators and create_mandinka_json function remain the same as before) ---
def generate_ipa(word: str) -> str:
    if not word or not isinstance(word, str):
        return ""
    word = word.strip().lower()
    word = word.replace("aa", "aː").replace("ee", "eː").replace("ii", "iː").replace("oo", "oː").replace("uu", "uː")
    word = word.replace("nj", "ndʒ").replace("nk", "ŋk").replace("ng", "ŋg")
    word = word.replace("ny", "ɲ").replace("nm", "m").replace("nb", "mb").replace("np", "mp")
    word = word.replace("c", "tʃ").replace("j", "dʒ").replace("y", "j")
    if word.endswith("ŋg"):
        pass
    return f"/{word}/"

def generate_simplified(word: str) -> str:
    if not word or not isinstance(word, str):
        return ""
    word = word.strip().lower()
    ipa_to_simple = {
        "aa": "ahh", "ee": "ehh", "ii": "eee", "oo": "ooh", "uu": "ooh",
        "ng": "ng", "ny": "ny", "nj": "nj",
        "c": "ch", "j": "j",
        "a": "ah", "e": "eh", "i": "ee", "o": "oh", "u": "oo"
    }
    temp_word = word
    for ipa_char, simple_char in ipa_to_simple.items():
        temp_word = temp_word.replace(ipa_char, simple_char)
    syllables = re.split(r'(?<=[^aeiou])(?=[aeiou])', temp_word)
    return "-".join(s for s in syllables if s).strip('-')

def create_mandinka_json(csv_file_path: Path, output_json_path: Path) -> bool:
    logger.info(f"Creating Mandinka-centric JSON from {csv_file_path.name} to {output_json_path.name}")
    try:
        mandinka_entries: List[Dict[str, Any]] = []
        with open(csv_file_path, 'r', encoding='utf-8-sig') as csvfile:
            reader = csv.DictReader(csvfile)
            if not reader.fieldnames:
                logger.error(f"CSV file {csv_file_path.name} is empty or has no header row.")
                return False
            reader.fieldnames = [field.strip().lower() for field in reader.fieldnames]

            expected_fields = ['headerword', 'translation and sentences']
            for field in expected_fields:
                if field not in reader.fieldnames:
                    logger.error(f"Missing required column '{field}' in {csv_file_path.name}. Available columns: {reader.fieldnames}")
                    return False

            for row_num, raw_row in enumerate(reader):
                row = {k.strip().lower(): v for k,v in raw_row.items()}
                mandinka_word = row.get('headerword', '').strip()
                if not mandinka_word:
                    logger.warning(f"Skipping row {row_num + 2} in {csv_file_path.name}: Missing Headerword.")
                    continue

                ipa = row.get('ipa prnounciation', '').strip()
                simplified = row.get('phonetic pronunciation', '').strip()
                if not ipa: ipa = generate_ipa(mandinka_word)
                if not simplified: simplified = generate_simplified(mandinka_word)

                mandinka_entry: Dict[str, Any] = {
                    "mandinka_word": mandinka_word.lower(),
                    "pronunciation": {"ipa": ipa, "simplified": simplified},
                    "part_of_speech": row.get('part of speech', 'UNKNOWN').strip(),
                    "english_translations": [],
                    "source": row.get('source', '').strip(),
                    "country": row.get('country', '').strip()
                }

                english_values_raw = row.get('translation and sentences', '').strip()
                # Use the new intelligent extractor here too for consistency
                english_glosses = extract_english_glosses(english_values_raw)

                # If no clean glosses found, maybe use the raw value as one entry? Or skip?
                # Let's be strict and use only what was cleanly extracted.
                if not english_glosses and english_values_raw and english_values_raw.lower() != '#n/a':
                     logger.warning(f"Row {row_num + 2}: Could not extract a clean English gloss from '{english_values_raw}'.")


                for translation in english_glosses:
                    example_sentences = []
                    sentence = row.get('mandinka sentence', '').strip()
                    if sentence:
                        example_sentences.append({
                            "sentence": sentence,
                            "sentence_ipa": row.get('sentence ipa pronouncation', '') or generate_ipa(sentence),
                            "sentence_simplified": row.get('sentence phonetic pronounciation', '') or generate_simplified(sentence)
                        })

                    english_translation_obj = {
                        "english_word": translation, # Already lowercased by the extractor
                        "relation": "multiple",
                        "example_sentences": example_sentences
                    }
                    mandinka_entry["english_translations"].append(english_translation_obj)
                mandinka_entries.append(mandinka_entry)

        with open(output_json_path, 'w', encoding='utf-8') as jsonfile:
            json.dump(mandinka_entries, jsonfile, indent=2, ensure_ascii=False)
        logger.info(f"Mandinka JSON created with {len(mandinka_entries)} entries: {output_json_path.name}")
        return True
    except FileNotFoundError:
        logger.error(f"CSV file not found: {csv_file_path}")
        return False
    except Exception as e:
        logger.error(f"Error creating Mandinka JSON from {csv_file_path.name}: {e}", exc_info=True)
        return False


def convert_csv_to_json_english_centric(csv_file_path: Path, output_json_path: Path) -> bool:
    """
    Convert CSV to JSON with English-centric format.
    Uses the new 'extract_english_glosses' function for clean keys.
    """
    logger.info(f"Creating English-centric JSON from {csv_file_path.name} to {output_json_path.name}")
    try:
        english_centric_dict: Dict[str, List[Dict[str, Any]]] = {}
        with open(csv_file_path, 'r', encoding='utf-8-sig') as csvfile:
            reader = csv.DictReader(csvfile)
            if not reader.fieldnames: return False # Guard clause
            reader.fieldnames = [field.strip().lower() for field in reader.fieldnames]
            
            # ... (omitting the rest of the file reading part for brevity, it's the same) ...
            for row_num, raw_row in enumerate(reader):
                row = {k.strip().lower(): v for k,v in raw_row.items()}
                mandinka_word_raw = row.get('headerword', '').strip()
                if not mandinka_word_raw: continue

                mandinka_word = mandinka_word_raw.lower()
                ipa = row.get('ipa prnounciation', '') or generate_ipa(mandinka_word)
                simplified = row.get('phonetic pronunciation', '') or generate_simplified(mandinka_word)

                mandinka_details: Dict[str, Any] = {
                    "mandinka_word": mandinka_word,
                    "pronunciation": {"ipa": ipa, "simplified": simplified},
                    "part_of_speech": row.get('part of speech', 'UNKNOWN').strip(),
                    "source": row.get('source', '').strip(),
                    "country": row.get('country', '').strip(),
                    "example_sentences": []
                }
                
                sentence = row.get('mandinka sentence', '').strip()
                if sentence and sentence.lower() != '#n/a':
                    mandinka_details["example_sentences"].append({
                        "sentence": sentence,
                        "sentence_ipa": row.get('sentence ipa pronouncation', '') or generate_ipa(sentence),
                        "sentence_simplified": row.get('sentence phonetic pronounciation', '') or generate_simplified(sentence)
                    })
                
                # --- THIS IS THE KEY CHANGE ---
                # Use the new intelligent parsing function here.
                english_values_raw = row.get('translation and sentences', '')
                english_glosses = extract_english_glosses(english_values_raw)
                # -----------------------------

                if not english_glosses and english_values_raw and english_values_raw.lower() != '#n/a':
                    logger.warning(f"Row {row_num + 2} in {csv_file_path.name}: Could not extract a clean English headword from '{english_values_raw}'. Skipping for English-centric JSON.")
                    continue

                for eng_word in english_glosses:
                    if eng_word not in english_centric_dict:
                        english_centric_dict[eng_word] = []
                    
                    # Prevent adding the exact same Mandinka word details under the same English key
                    if not any(d['mandinka_word'] == mandinka_details['mandinka_word'] for d in english_centric_dict[eng_word]):
                        english_centric_dict[eng_word].append(mandinka_details)

        sorted_english_centric_dict = dict(sorted(english_centric_dict.items()))
        with open(output_json_path, 'w', encoding='utf-8') as jsonfile:
            json.dump(sorted_english_centric_dict, jsonfile, indent=2, ensure_ascii=False)
        logger.info(f"English-centric JSON created with {len(sorted_english_centric_dict)} English headwords: {output_json_path.name}")
        return True
    except FileNotFoundError:
        logger.error(f"CSV file not found: {csv_file_path}")
        return False
    except Exception as e:
        logger.error(f"Error creating English-centric JSON from {csv_file_path.name}: {e}", exc_info=True)
        return False

# --- (The rest of the script: split_json_by_letter, get_conversion_function, process_single_file, run_cli remains the same) ---
# --- I am omitting them here for brevity, but they should be included in your final .py file. ---
def split_json_by_letter(json_file_path: Path, output_split_dir: Path, current_mode: str) -> bool:
    logger.info(f"Splitting {json_file_path.name} (mode: {current_mode}) into files in {output_split_dir} based on first letter.")
    output_split_dir.mkdir(parents=True, exist_ok=True)
    try:
        with open(json_file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
        if current_mode == MODE_MANDINKA:
            if not isinstance(data, list):
                logger.error(f"Mandinka mode split error: Expected a JSON list in {json_file_path.name}, got {type(data)}.")
                return False
            grouped_entries: Dict[str, List[Dict[str, Any]]] = {}
            for entry in data:
                if not isinstance(entry, dict): continue
                headword = entry.get("mandinka_word", "").strip()
                if not headword: continue
                first_letter = headword[0].upper()
                if not ('A' <= first_letter <= 'Z'): first_letter = "misc"
                if first_letter not in grouped_entries: grouped_entries[first_letter] = []
                grouped_entries[first_letter].append(entry)
            for letter, entries_for_letter in grouped_entries.items():
                (output_split_dir / f"{letter}.json").write_text(json.dumps(entries_for_letter, indent=2, ensure_ascii=False))
        elif current_mode == MODE_ENGLISH:
            if not isinstance(data, dict):
                logger.error(f"English mode split error: Expected a JSON object (dictionary) in {json_file_path.name}, got {type(data)}.")
                return False
            grouped_entries: Dict[str, Dict[str, Any]] = {}
            for headword, entry_data in data.items():
                if not headword: continue
                first_letter = headword[0].upper()
                if not ('A' <= first_letter <= 'Z'): first_letter = "misc"
                if first_letter not in grouped_entries: grouped_entries[first_letter] = {}
                grouped_entries[first_letter][headword] = entry_data
            for letter, entries_for_letter_dict in grouped_entries.items():
                (output_split_dir / f"{letter}.json").write_text(json.dumps(entries_for_letter_dict, indent=2, ensure_ascii=False))
        else:
            return False
        logger.info(f"Successfully finished splitting {json_file_path.name}")
        return True
    except Exception as e:
        logger.error(f"Error splitting {json_file_path.name}: {e}", exc_info=True)
        return False

def get_conversion_function(mode: str) -> Callable[[Path, Path], bool]:
    conversion_functions: Dict[str, Callable[[Path, Path], bool]] = {
        MODE_MANDINKA: create_mandinka_json,
        MODE_ENGLISH: convert_csv_to_json_english_centric,
    }
    return conversion_functions[mode]

def process_single_file(csv_file_path: Path, output_dir: Path, mode: str, should_split: bool) -> None:
    base_name = csv_file_path.stem
    output_json_path = output_dir / f"{base_name}_{mode}.json"
    try:
        conversion_func = get_conversion_function(mode)
        if conversion_func(csv_file_path, output_json_path):
            logger.info(f"Successfully converted {csv_file_path.name} to {output_json_path.name}")
            if should_split:
                split_dir_name = f"{base_name}_{mode}_split"
                output_split_dir = output_dir / split_dir_name
                if split_json_by_letter(output_json_path, output_split_dir, mode):
                    logger.info(f"Successfully split {output_json_path.name} into {output_split_dir}")
                else:
                    logger.error(f"Failed to split {output_json_path.name}")
        else:
            logger.error(f"Conversion failed for {csv_file_path.name}")
    except Exception as e:
        logger.error(f"An unexpected error occurred while processing {csv_file_path.name}: {e}", exc_info=True)

def run_cli() -> None:
    parser = argparse.ArgumentParser(description="Mandinka Dictionary CLI", epilog="Example: python script.py --input data.csv --output results --mode english --split")
    parser.add_argument('--input', type=Path, required=True, help="Input CSV file or folder.")
    parser.add_argument('--output', type=Path, required=True, help="Output folder.")
    parser.add_argument('--mode', choices=SUPPORTED_MODES, default=MODE_MANDINKA, help="Conversion mode.")
    parser.add_argument('--split', action='store_true', help="Split output into A-Z JSON files.")
    parser.add_argument('--verbose', '-v', action='store_true', help="Enable verbose logging.")
    args = parser.parse_args()
    if args.verbose: logging.getLogger().setLevel(logging.DEBUG)
    try:
        args.output.mkdir(parents=True, exist_ok=True)
    except OSError as e:
        logger.error(f"Could not create output directory {args.output}: {e}")
        return
    if not args.input.exists():
        logger.error(f"Input path does not exist: {args.input}")
        return
    if args.input.is_dir():
        csv_files = [item for item in args.input.iterdir() if item.is_file() and item.suffix.lower() == '.csv']
        if not csv_files:
            logger.warning(f"No CSV files found in directory: {args.input}")
            return
        for csv_file in csv_files:
            process_single_file(csv_file, args.output, args.mode, args.split)
    elif args.input.is_file() and args.input.suffix.lower() == '.csv':
        process_single_file(args.input, args.output, args.mode, args.split)
    else:
        logger.error(f"Input path is not a valid CSV file or directory: {args.input}")
        return
    logger.info("Batch processing complete.")

if __name__ == "__main__":
    run_cli()
# This script is designed to be run from the command line.