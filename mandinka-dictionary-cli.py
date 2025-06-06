# Python command line (CLI) script to batch process dictionary.
# USE COMMAND:
# python mandinka_dict_cli.py --input your_csv_folder_or_file --output output_folder --mode mandinka
# "--mode english" will filp it to English focused format JSON
# "--split" will split JSON output into files based on first letter of header word
import os
import argparse
from generate_mandinka_dictionary_json import create_mandinka_json
#from generate_mandinka_dictionary_json import convert_csv_to_json_english_centric

split_json_by_letter = None  # Placeholder for the split function
def convert_csv_to_json_english_centric(csv_file, out_path):
    """Convert CSV to JSON with English-centric format."""
    # Placeholder for the actual conversion logic
    # This should be replaced with the actual implementation
    print(f"Converting {csv_file} to {out_path} in English-centric format.")
    return True  # Simulate success
def split_json_by_letter(json_file, output_dir):
    """Split JSON file into multiple files based on the first letter of the header word."""
    # Placeholder for the actual splitting logic
    # This should be replaced with the actual implementation
    print(f"Splitting {json_file} into files in {output_dir} based on first letter.")
    return True  # Simulate success
def create_mandinka_json(csv_file, out_path):
    """Convert CSV to JSON with Mandinka-centric format."""
    # Placeholder for the actual conversion logic
    # This should be replaced with the actual implementation
    print(f"Converting {csv_file} to {out_path} in Mandinka-centric format.")
    return True  # Simulate success
# Placeholder for the actual conversion logic
# This should be replaced with the actual implementation

def run_cli():
    """Function that runs and sets the instructions for the command line"""
    parser = argparse.ArgumentParser(description="Mandinka Dictionary CLI")
    parser.add_argument('--input', required=True, help="CSV file or folder of CSVs")
    parser.add_argument('--output', required=True, help="Output folder")
    parser.add_argument('--mode', choices=['mandinka', 'english'], default='mandinka', help="Conversion mode")
    parser.add_argument('--split', action='store_true', help="Split output into A–Z JSON files")

    args = parser.parse_args()

    os.makedirs(args.output, exist_ok=True)

    def process_file(csv_file):
        base = os.path.splitext(os.path.basename(csv_file))[0]
        out_path = os.path.join(args.output, f"{base}_{args.mode}.json")
        success = False  # Initialize success status

        if args.mode == "mandinka":
            success = create_mandinka_json(csv_file, out_path)
            if success and args.split and args.mode == "mandinka":
                split_dir = os.path.join(args.output, f"{base}_split")
                os.makedirs(split_dir, exist_ok=True)
                split_json_by_letter(out_path, split_dir)
        else:
            success = convert_csv_to_json_english_centric(csv_file, out_path)
            if success and args.split and args.mode == "english":
                split_dir = os.path.join(args.output, f"{base}_split")
                os.makedirs(split_dir, exist_ok=True)
                split_json_by_letter(out_path, split_dir)
        if success:
            print(f"File {os.path.basename(csv_file)} converted successfully to {out_path}")
        else:
            print(f"Conversion failed for {os.path.basename(csv_file)}")

    # Process files
    if os.path.isdir(args.input):
        for f in os.listdir(args.input):
            full_path = os.path.join(args.input, f)
            if os.path.isfile(full_path) and f.endswith('.csv'):
                process_file(full_path)
    else:
        process_file(args.input)

    print("Batch conversion complete.")

if __name__ == "__main__":
    run_cli()
