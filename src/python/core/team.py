import re
from typing import Any
from pathlib import Path
from agno.team import Team
from agno.media import Audio
from core.repositories import ConversationRepository

class ParenteTeam(Team):
    def log_message(self, user_id: str, sender: str, content: str, media_type: str = None, media_url: str = None):
        ConversationRepository.log_message(user_id, sender, content, media_type, media_url)

    def get_conversation_history(self, user_id: str, limit: int = 10) -> str:
        """Fetches recent conversation history from the database."""
        history = ConversationRepository.get_history(user_id, limit)
        if not history:
             return ""

        history_entries = []
        for msg in history:
            role = "User" if msg['sender'] == 'user' else "Agent"
            history_entries.append(f"{role}: {msg['content']}")
        
        if history_entries:
            return "Previous Conversation History:\n" + "\n".join(history_entries) + "\n---\n"
        return ""

    def run(self, input: Any = None, *args, **kwargs) -> Any:
        session_id = kwargs.get("session_id")
        user_message = str(input)
        
        if session_id:
             self.log_message(session_id, "user", user_message, media_type=kwargs.get("media_type"), media_url=kwargs.get("media_url"))
             
             # INJECT HISTORY
             history_context = self.get_conversation_history(session_id)
             if history_context:
                 if isinstance(input, str):
                     input = history_context + "\n" + input
        
        response = super().run(input=input, *args, **kwargs)
        
        if session_id and response:
             agent_message = str(response.content)
             self.log_message(session_id, "agent", agent_message)
             
        return response

    async def arun(self, input: Any = None, *args, **kwargs) -> Any:
        session_id = kwargs.get("session_id")
        user_message = str(input)
        
        if session_id:
             self.log_message(session_id, "user", user_message, media_type=kwargs.get("media_type"), media_url=kwargs.get("media_url"))

             # INJECT HISTORY
             history_context = self.get_conversation_history(session_id)
             if history_context:
                 if isinstance(input, str):
                     clean_user_id = session_id.replace("wa:", "")
                     input = f"Current User ID: {clean_user_id}\n" + history_context + "\n" + input

        # Execute original run
        response = await super().arun(input=input, *args, **kwargs)
        
        media_type = None
        media_url = None
        
        # Audio handling logic
        if response and response.content:
            # Regex to find the path
            match = re.search(r"(?:[a-zA-Z]:)?[\\/].*?public[\\/]uploads[\\/]audio[\\/].*?speech_[\w-]+\.(?:wav|ogg|mp3)", str(response.content).replace("\\\\", "\\"))
            
            if match:
                file_path_str = match.group(0).strip()
                response.response_audio_url_internal = file_path_str
                
                # Construct relative URL for web playback
                try:
                     path_obj = Path(file_path_str)
                     parts = path_obj.parts
                     if "uploads" in parts:
                         idx = parts.index("uploads")
                         media_url = "/" + "/".join(parts[idx:])
                     else:
                         media_url = file_path_str 
                except:
                     media_url = file_path_str

                media_type = "audio" 
                
                try:
                    with open(file_path_str, "rb") as f:
                        audio_content = f.read()
                        
                        file_ext = Path(file_path_str).suffix.lower()
                        mime_type = "audio/wav"
                        if file_ext == ".ogg":
                            mime_type = "audio/ogg"
                        elif file_ext == ".mp3":
                            mime_type = "audio/mpeg"
                            
                        response.audio = Audio(content=audio_content, mime_type=mime_type)
                        
                        # Clear text content so it's not sent as text
                        response.content = ""
                except Exception as e:
                    print(f"Error reading generated audio file: {e}") 
       
        if session_id and response:
             agent_message = str(response.content)
             self.log_message(session_id, "agent", agent_message, media_type, media_url)
             
        return response
