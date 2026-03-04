
import os
import asyncio
from dotenv import load_dotenv
from google import genai
from google.genai import types

load_dotenv()

def test_mp3():
    print("Testing Gemini MP3 generation...")
    try:
        client = genai.Client(api_key=os.getenv("GOOGLE_API_KEY"))
        
        # Try requesting audio/mp3
        config = types.GenerateContentConfig(
            response_modalities=['AUDIO'],
            response_mime_type="audio/mp3"
        )
        
        response = client.models.generate_content(
            model='gemini-2.5-flash-preview-tts',
            contents="Hello, this is a test for MP3 generation.",
            config=config
        )
        
        if response.candidates and response.candidates[0].content.parts:
            part = response.candidates[0].content.parts[0]
            print(f"Mime Type detected: {part.inline_data.mime_type}")
            
            data = part.inline_data.data
            print(f"Data length: {len(data)}")
            
            # Check for ID3 header or MP3 sync frames (FF F3/F2)
            # Simple check
            header = data[:4] if isinstance(data, bytes) else b''
            print(f"Header hex: {header.hex()}")
            
            if "mp3" in part.inline_data.mime_type:
                print("SUCCESS: Model returned MP3 mime type.")
                return True
            else:
                print("WARNING: Model did not return MP3 mime type.")
                
    except Exception as e:
        print(f"ERROR: {e}")
        return False

if __name__ == "__main__":
    test_mp3()
