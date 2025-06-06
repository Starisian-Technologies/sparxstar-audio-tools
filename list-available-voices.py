import os
from google.cloud import texttospeech

language_code = "en-NI"  # Change this to the language code you want to filter by

def list_voices():
    """Lists available voices from Google Cloud TTS matching the language_code."""
    client = texttospeech.TextToSpeechClient()
    response = client.list_voices()

    found = False
    for voice in response.voices:
        if language_code in voice.language_codes:
            found = True
            print(f"✅ Voice Name: {voice.name}")
            print(f"Supported Language Codes: {voice.language_codes}")
            print(f"SSML Voice Gender: {texttospeech.SsmlVoiceGender(voice.ssml_gender).name}")
            print(f"Natural Sample Rate Hertz: {voice.natural_sample_rate_hertz}")
            print("-" * 40)

    if not found:
        print(f"❌ No voices found for language code: {language_code}")

if __name__ == "__main__":
    list_voices()
