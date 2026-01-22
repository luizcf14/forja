import base64
import textwrap

from os import getenv
from typing import Optional

from fastapi import APIRouter, BackgroundTasks, HTTPException, Request
from fastapi.responses import PlainTextResponse

from agno.agent.agent import Agent
from agno.media import Audio, File, Image, Video
from agno.team.team import Team
from agno.tools.whatsapp import WhatsAppTools
from agno.utils.log import log_error, log_info, log_warning
from agno.utils.whatsapp import get_media_async, send_image_message_async, typing_indicator_async, upload_media_async

# Import sqlite3 for DB check
import sqlite3
from pathlib import Path

# Fix incorrect import if present, though validate_webhook_signature might need fixing too if it was 'app...'
# The user file had: from app.utils.whatsapp.security import validate_webhook_signature
# I should change that to relative too if it exists.
from .security import validate_webhook_signature


# Database Path (Assuming relative to project root similar to parente.py)
# router.py is in src/python/utils/whatsapp/
# PROJECT_ROOT is ../../../..
SCRIPT_DIR = Path(__file__).parent.absolute()
# src/python/utils/whatsapp -> src/python/utils -> src/python -> src -> root
PROJECT_ROOT = SCRIPT_DIR.parent.parent.parent.parent
DB_PATH = PROJECT_ROOT / "database.sqlite"

def get_ai_status(phone_number: str) -> str:
    """Check AI status for a given phone number (user_id)."""
    if not DB_PATH.exists():
        return 'active'
    
    try:
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        # Check conversations table
        # 1. Try with 'wa:' prefix first (new format)
        wa_id = f"wa:{phone_number}"
        cursor.execute("SELECT ai_status FROM conversations WHERE user_id = ?", (wa_id,))
        row = cursor.fetchone()
        
        if not row:
            # 2. Fallback to raw phone number (old format)
            cursor.execute("SELECT ai_status FROM conversations WHERE user_id = ?", (phone_number,))
            row = cursor.fetchone()
        
        conn.close()
        
        if row:
            return row['ai_status'] # 'active' or 'paused'
        return 'active'
    except Exception as e:
        log_error(f"Error checking AI status: {e}")
        return 'active'


from pydantic import BaseModel



class InternalSendMessage(BaseModel):
    to: str
    message: str

class InternalSendAudioMessage(BaseModel):
    to: str
    audio_path: str

def attach_routes(router: APIRouter, agent: Optional[Agent] = None, team: Optional[Team] = None) -> APIRouter:
    if agent is None and team is None:
        raise ValueError("Either agent or team must be provided.")

    whatsapp_tools = WhatsAppTools(async_mode=True)

    @router.post("/internal_send")
    async def internal_send(body: InternalSendMessage):
        """Handle internal manual message sending"""
        log_info(f"INTERNAL SEND: {body}")
        try:
             # Log the manual agent message to DB
            if team and hasattr(team, 'log_message'):
                try:
                    # Ensure wa: prefix is present but not duplicated
                    log_to = body.to
                    if not log_to.startswith("wa:"):
                        log_to = f"wa:{log_to}"
                        
                    team.log_message(log_to, "agent", body.message)
                    log_info(f"Logged manual agent message to {log_to}")
                except Exception as log_err:
                    log_error(f"Failed to log manual message: {log_err}")

            # Send via WhatsApp
            await _send_whatsapp_message(body.to, body.message)
            return {"status": "sent"}
        except Exception as e:
            log_error(f"Error sending manual message: {str(e)}")
            raise HTTPException(status_code=500, detail=str(e))

    @router.post("/internal_send_audio")
    async def internal_send_audio(body: InternalSendAudioMessage):
        """Handle internal manual audio sending"""
        log_info(f"INTERNAL SEND AUDIO: {body}")
        try:
            # 1. Read Audio File
            file_path = Path(body.audio_path)
            if not file_path.exists():
                raise FileNotFoundError(f"Audio file not found: {body.audio_path}")
                
            # 4. Send Audio Message
            # Use body.to directly to match text message behavior (which includes 'wa:' and works)
            recipient_number = body.to
            
            log_info(f"Uploading manual audio... {file_path}")
            
            # ... upload_media_async call ...
            
            # Re-implementing upload to ensure we capture the ID correctly
            if not file_path.exists():
                 raise FileNotFoundError(f"Audio file not found: {body.audio_path}")
            
            # Determine Mime Type and Convert if needed
            mime_type = "audio/ogg" 
            
            # Helper to convert to ogg
            def convert_to_ogg(input_path: Path) -> Path:
                try:
                    import os
                    from pydub import AudioSegment
                    
                    # Ensure FFMPEG is available (using path from audio_gen.py logic or env)
                    ffmpeg_env_path = os.getenv("FFMPEG_PATH")
                    if os.name == 'nt' and not ffmpeg_env_path:
                         default_win_path = r"C:\Users\Solved-Blerys-Win\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-8.0.1-full_build\bin"
                         if os.path.exists(default_win_path):
                             os.environ["PATH"] += os.pathsep + default_win_path
                    
                    audio = AudioSegment.from_file(str(input_path))
                    output_path = input_path.with_suffix(".ogg")
                    audio.export(str(output_path), format="ogg", codec="libopus")
                    log_info(f"Audio converted successfully to: {output_path}")
                    return output_path
                except Exception as e:
                    log_error(f"Audio conversion failed: {e}")
                    raise HTTPException(status_code=500, detail=f"Audio conversion failed: {e}")

            # WhatsApp doesn't support WAV. Convert WAV to OGG.
            if file_path.suffix.lower() == ".wav":
                 log_info("Converting WAV to OGG for WhatsApp compatibility...")
                 file_path = convert_to_ogg(file_path)
                 mime_type = "audio/ogg"
            
            elif file_path.suffix.lower() == ".mp3":
                 mime_type = "audio/mpeg"
            
            with open(file_path, "rb") as f:
                audio_data = f.read()
            async def _custom_upload_media(media_data: bytes, mime_type: str, filename: str):
                from agno.utils.whatsapp import get_access_token, get_phone_number_id
                import httpx
                from io import BytesIO
                
                phone_number_id = get_phone_number_id()
                url = f"https://graph.facebook.com/v22.0/{phone_number_id}/media"
                access_token = get_access_token()
                headers = {"Authorization": f"Bearer {access_token}"}
                
                data = {"messaging_product": "whatsapp", "type": mime_type}
                
                file_data = BytesIO(media_data)
                files = {"file": (filename, file_data, mime_type)}
                
                try:
                    async with httpx.AsyncClient() as client:
                        response = await client.post(url, headers=headers, data=data, files=files)
                        if response.status_code >= 400:
                            log_error(f"Upload failed. Status: {response.status_code}, Body: {response.text}")
                            return {"error": f"Status {response.status_code}: {response.text}"}
                            
                        return response.json().get("id")
                except Exception as e:
                    return {"error": str(e)}

            with open(file_path, "rb") as f:
                audio_data = f.read()
                
            media_id = await _custom_upload_media(media_data=audio_data, mime_type=mime_type, filename=file_path.name)
            log_info(f"Manual Audio Uploaded. Media ID: {media_id}")

            if not media_id or (isinstance(media_id, dict) and "error" in media_id):
                 log_error(f"WhatsApp Upload Failed: {media_id}")
                 raise Exception(f"WhatsApp Upload Failed: {media_id}")

            log_info(f"Sending Audio to {recipient_number} with Media ID {media_id}")
            await send_audio_message_async(media_id=media_id, recipient=recipient_number)
            log_info("Audio sent successfully via WhatsApp API")

            # 5. Log to DB
            if team and hasattr(team, 'log_message'):
                try:
                    log_to = body.to
                    if not log_to.startswith("wa:"):
                        log_to = f"wa:{log_to}"
                    
                    # Convert absolute path to relative if needed for DB consistency
                    media_url = str(file_path)
                    if "public" in media_url: # Try to make it web relative
                        try:
                           parts = Path(media_url).parts
                           idx = parts.index("public")
                           media_url = "/" + "/".join(parts[idx:]) # Intentionally keeping /public maybe? No, usually public is root.
                           # Start from after public
                           # If public/uploads/ -> /uploads/
                           # Let's adjust:
                           if "uploads" in parts:
                                idx_up = parts.index("uploads")
                                media_url = "/" + "/".join(parts[idx_up:])
                        except:
                            pass
                            
                    team.log_message(log_to, "agent", "Manual Audio Message", media_type="audio", media_url=media_url)
                except Exception as log_err:
                    log_error(f"Failed to log manual audio message: {log_err}")

            return {"status": "sent", "media_id": media_id}
        except Exception as e:
            log_error(f"Error sending manual audio message: {str(e)}")
            raise HTTPException(status_code=500, detail=str(e))


    @router.get("/status")
    async def status():
        return {"status": "available"}

    @router.get("/webhook")
    async def verify_webhook(request: Request):
        """Handle WhatsApp webhook verification"""
        mode = request.query_params.get("hub.mode")
        token = request.query_params.get("hub.verify_token")
        challenge = request.query_params.get("hub.challenge")

        verify_token = getenv("WHATSAPP_VERIFY_TOKEN")
        if not verify_token:
            raise HTTPException(status_code=500, detail="WHATSAPP_VERIFY_TOKEN is not set")

        if mode == "subscribe" and token == verify_token:
            if not challenge:
                raise HTTPException(status_code=400, detail="No challenge received")
            return PlainTextResponse(content=challenge)

        raise HTTPException(status_code=403, detail="Invalid verify token or mode")

    @router.post("/webhook")
    async def webhook(request: Request, background_tasks: BackgroundTasks):
        """Handle incoming WhatsApp messages"""
        try:
            # Get raw payload for signature validation
            payload = await request.body()
            signature = request.headers.get("X-Hub-Signature-256")

            # Validate webhook signature
            if not validate_webhook_signature(payload, signature):
                log_warning("Invalid webhook signature")
                raise HTTPException(status_code=403, detail="Invalid signature")

            body = await request.json()

            # Validate webhook data
            if body.get("object") != "whatsapp_business_account":
                log_warning(f"Received non-WhatsApp webhook object: {body.get('object')}")
                return {"status": "ignored"}

            # Process messages in background
            for entry in body.get("entry", []):
                for change in entry.get("changes", []):
                    messages = change.get("value", {}).get("messages", [])

                    if not messages:
                        print("DEBUG: Webhook hit but no messages found.")
                        continue

                    message = messages[0]
                    print(f"DEBUG: Webhook received message: {message}")
                    background_tasks.add_task(process_message, message, agent, team)

            return {"status": "processing"}

        except Exception as e:
            log_error(f"Error processing webhook: {str(e)}")
            raise HTTPException(status_code=500, detail=str(e))

    async def process_message(message: dict, agent: Optional[Agent], team: Optional[Team]):
        """Process a single WhatsApp message in the background"""
        log_info(message)

        try:
            message_text = ""
            message_image = None
            message_video = None
            message_audio = None
            message_doc = None
            
            saved_media_type = None
            saved_media_url = None
            
            message_id = message.get("id")
            await typing_indicator_async(message_id)

            match message.get("type"):
                case "text":
                    message_text = message["text"]["body"]
                case "image":
                    try:
                        message_text = message["image"]["caption"]
                    except Exception:
                        message_text = "Describe the image"
                    finally:
                        message_image = message["image"]["id"]
                case "video":
                    try:
                        message_text = message["video"]["caption"]
                    except Exception:
                        message_text = "Describe the video"
                    finally:
                        message_video = message["video"]["id"]
                case "audio":
                    message_text = "Audio message received"
                    message_audio = message["audio"]["id"]
                    
                    try:
                        # Download audio
                        audio_content = await get_media_async(message_audio)
                        if audio_content:
                             # Ensure upload dir exists
                             upload_dir = PROJECT_ROOT / "public" / "uploads" / "audio"
                             upload_dir.mkdir(parents=True, exist_ok=True)
                             
                             filename = f"{message_audio}.ogg" # WhatsApp usually uses OGG/Opus for voice notes
                             file_path = upload_dir / filename
                             
                             with open(file_path, "wb") as f:
                                 f.write(audio_content)
                                 
                             log_info(f"Saved audio to {file_path}")
                             
                             # Set variables for logging
                             saved_media_type = "audio"
                             saved_media_url = f"/uploads/audio/{filename}"
                             
                    except Exception as e:
                         log_error(f"Failed to download audio: {e}")
                         message_text = f"[Audio Download Failed: {e}]"
                case "document":
                    message_text = "Process the document"
                    message_doc = message["document"]["id"]
                case "location":
                    # TODO: Alterar prompt para um mais adequado com armazenamento.
                    message_text = f"""Peça ao Zé da Caderneta que guarde as seguintes coordenadas Lat: {message['location']['latitude']} Long: {message['location']['longitude']}. Em seguida, peça ao Pedrão Agrônomo que gere uma visualização da minha propriedade rural."""
                case _:
                    return

            phone_number = message["from"]
            log_info(f"Processing message from {phone_number}: {message_text}")
            
            # --- AI PAUSE LOGIC ---
            with open("debug_log.txt", "a") as f:
                f.write(f"DEBUG: Checking AI status for {phone_number} using DB at {DB_PATH}\n")
            
            status = get_ai_status(phone_number)
            
            with open("debug_log.txt", "a") as f:
                f.write(f"DEBUG: Status for {phone_number} is '{status}'\n")
            
            if status == 'paused':
                log_info(f"AI is PAUSED for user {phone_number}. Skipping response.")
                with open("debug_log.txt", "a") as f:
                    f.write(f"DEBUG: PAUSED detected. Log message to DB and return.\n")
                
                # Try to log the user message to DB so it appears in history
                # We assume 'team' is LoggingTeam instance
                if team and hasattr(team, 'log_message'):
                     try:
                         # log_message(user_id, sender, content, media_type, media_url)
                         
                         # Check if we have saved media from the case above
                         # Note: The logic inside 'case "audio"' needs to set saved_media_type/url
                         # I need to update the replacement above to set these variables
                         
                         media_type = saved_media_type
                         media_url = saved_media_url
                         
                         # Re-infer from message_audio if needed? No, better to set in case.
                         if message_audio and not media_url: 
                             # If we failed to download or logic above didn't set it (due to scope issues if I mess up)
                             pass

                         team.log_message(f"wa:{phone_number}", "user", message_text, media_type, media_url)
                         log_info("Logged user message to DB (AI Paused)")
                     except Exception as log_err:
                         log_error(f"Failed to log message during pause: {log_err}")
                
                return # Stop processing
            # ----------------------

            # TODO: Só temos Team, não precisa do agent.
            # Generate and send response
            if agent:
                response = await agent.arun(
                    message_text,
                    user_id=phone_number,
                    session_id=f"wa:{phone_number}",
                    images=[Image(content=await get_media_async(message_image))] if message_image else None,
                    files=[File(content=await get_media_async(message_doc))] if message_doc else None,
                    videos=[Video(content=await get_media_async(message_video))] if message_video else None,
                    audio=[Audio(content=await get_media_async(message_audio))] if message_audio else None,
                )
            elif team:
                response = await team.arun(
                    message_text,
                    user_id=phone_number,
                    session_id=f"wa:{phone_number}",
                    files=[File(content=await get_media_async(message_doc))] if message_doc else None,
                    images=[Image(content=await get_media_async(message_image))] if message_image else None,
                    videos=[Video(content=await get_media_async(message_video))] if message_video else None,
                    audio=[Audio(content=await get_media_async(message_audio))] if message_audio else None,
                    media_type=saved_media_type,
                    media_url=saved_media_url,
                )

            if response.reasoning_content:
                await _send_whatsapp_message(phone_number, f"Reasoning: \n{response.reasoning_content}", italics=True)

            # Check for audio response injected by parente.py or native
            if hasattr(response, "response_audio_url_internal") and response.response_audio_url_internal:
                try:
                    audio_path = Path(response.response_audio_url_internal)
                    with open(audio_path, "rb") as audio_file:
                        audio_data = audio_file.read()
                        
                    # Upload to WhatsApp
                    # WhatsApp supports mp3, ogg, wav, etc.
                    # Determine mime type
                    mime_type = "audio/ogg"
                    if audio_path.suffix == ".wav":
                        mime_type = "audio/wav"
                    elif audio_path.suffix == ".mp3":
                        mime_type = "audio/mpeg"
                    
                    media_id = await upload_media_async(media_data=audio_data, mime_type=mime_type, filename=f"audio{audio_path.suffix}")
                    
                    if not isinstance(media_id, str) and "error" in media_id:
                         log_error(f"Failed to upload audio to WhatsApp: {media_id}")
                         await _send_whatsapp_message(phone_number, response.content)
                    else:
                        await send_audio_message_async(media_id=media_id, recipient=phone_number)
                        
                except Exception as e:
                    log_error(f"Failed to send audio response: {e}")
                    await _send_whatsapp_message(phone_number, response.content)

            elif hasattr(response, "audio") and response.audio:
                # Handle standard Agno/Gemini audio output (or our injected one)
                try:
                    # response.audio could be a list or single object
                    audio_obj = response.audio[0] if isinstance(response.audio, list) else response.audio
                    
                    audio_content = audio_obj.content
                    mime_type = audio_obj.mime_type or "audio/wav"
                    
                    # Ensure bytes
                    if isinstance(audio_content, str):
                        try:
                            audio_content = base64.b64decode(audio_content)
                        except:
                            pass # Assume raw string matching? No, likely base64 if string.
                    
                    filename = "audio.wav"
                    if "mp3" in mime_type: filename = "audio.mp3"
                    elif "ogg" in mime_type: filename = "audio.ogg"
                    
                    media_id = await upload_media_async(media_data=audio_content, mime_type=mime_type, filename=filename)
                    await send_audio_message_async(media_id=media_id, recipient=phone_number)

                except Exception as e:
                    log_error(f"Failed to send audio response (obj): {e}")
                    if response.content:
                        await _send_whatsapp_message(phone_number, response.content)
            
            if response.images:
                number_of_images = len(response.images)
                log_info(f"images generated: f{number_of_images}")

                for i in range(number_of_images):
                    image_bytes = None
                    image_content = response.images[i].content
                    
                    if isinstance(image_content, bytes):
                        try:
                            decoded_string = image_content.decode("utf-8")

                            image_bytes = base64.b64decode(decoded_string)
                        except UnicodeDecodeError:
                            image_bytes = image_content
                    elif isinstance(image_content, str):
                        image_bytes = base64.b64decode(image_content)
                    else:
                        log_error(f"Unexpected image content type: {type(image_content)} for user {phone_number}")

                    if image_bytes:
                        media_id = await upload_media_async(media_data=image_bytes, mime_type="image/png", filename="image.png")
                        await send_image_message_async(media_id=media_id, recipient=phone_number, text=response.content)
                    else:
                        log_warning(f"Could not process image content for user {phone_number}. Type: {type(image_content)}")
                        await _send_whatsapp_message(phone_number, response.content)  # type: ignore
            else:
                await _send_whatsapp_message(phone_number, response.content)  # type: ignore

        except Exception as e:
            log_error(f"Error processing message: {str(e)}")

            try:
                await _send_whatsapp_message(phone_number, "Desculpe, ocorreu um erro ao processar sua mensagem. Por favor, tente novamente mais tarde.")
            except Exception as send_error:
                log_error(f"Error sending error message: {str(send_error)}")

    async def _send_whatsapp_message(recipient: str, message: str, italics: bool = False):
        message_batches = textwrap.wrap(message, width=4000, replace_whitespace=False, drop_whitespace=False)

        for i, batch in enumerate(message_batches, 1):
            if len(message_batches) > 1:
                batch_message = f"[{i}/{len(message_batches)}] {batch}"
            else:
                batch_message = f"{batch}"


            if italics:
                # TODO: É possível que, caso o texto possua "\n\n", a menssagem gere um "__" literal.
                formatted_batch = "\n".join([f"_{line}_" for line in batch_message.split("\n")])
                await whatsapp_tools.send_text_message_async(recipient=recipient, text=formatted_batch)
            else:
                await whatsapp_tools.send_text_message_async(recipient=recipient, text=batch_message)

    async def send_audio_message_async(media_id: str, recipient: str):
        """Send an audio message to a WhatsApp user."""
        log_info(f"Sending WhatsApp audio to {recipient}")
        
        # Best approach: Use the access token from whatsapp_tools and make raw call.
        
        url = f"{whatsapp_tools.base_url}/{whatsapp_tools.version}/{whatsapp_tools.phone_number_id}/messages"
        headers = whatsapp_tools._get_headers()
        
        data = {
            "messaging_product": "whatsapp",
            "recipient_type": "individual",
            "to": recipient,
            "type": "audio",
            "audio": {"id": media_id}
        }
        
        import httpx
        async with httpx.AsyncClient() as client:
            resp = await client.post(url, headers=headers, json=data)
            resp.raise_for_status()

    return router