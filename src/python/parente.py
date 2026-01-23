import sqlite3
import os
import sys
from typing import List, Optional, Dict, Any
from pathlib import Path
from dotenv import load_dotenv
from agno.team import Team
load_dotenv()


SCRIPT_DIR = Path(__file__).parent.absolute()
PROJECT_ROOT = SCRIPT_DIR.parent.parent
DB_PATH = PROJECT_ROOT / "database.sqlite"

try:
    from agno.models.ollama import Ollama
    from agno.agent import Agent
    from agno.models.google import Gemini
    from agno.knowledge.embedder.google import GeminiEmbedder
    from agno.knowledge.knowledge import Knowledge
    from agno.vectordb.lancedb import LanceDb, SearchType
    from agno.db.sqlite import SqliteDb
    from agno.team import Team
    from agno.os import AgentOS
    from agno.team import Team
    from agno.os import AgentOS
    from agno.media import Audio
    from utils.whatsapp.whatsapp import Whatsapp
    from tools.audio_gen import AudioGenerator
    
    # Try importing readers, handle if they are missing or moved
    try:
        from agno.knowledge.reader.pdf_reader import PDFReader
    except ImportError:
        PDFReader = None
    try:
        from agno.knowledge.reader.text_reader import TextReader
    except ImportError:
        TextReader = None
        
except ImportError as e:
    import traceback
    traceback.print_exc()
    print(f"Error importing Agno or dependencies: {e}")
    print("Please install requirements: pip install -r requirements.txt")
    sys.exit(1)

def get_db_connection():
    """Establishes a connection to the SQLite database."""
    if not DB_PATH.exists():
        print(f"Error: Database not found at {DB_PATH}")
        return None
    
    try:
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        return conn
    except sqlite3.Error as e:
        print(f"Error connecting to database: {e}")
        return None

def load_knowledge_base(kb_path_str: str) -> Optional[Any]:
    """Loads a knowledge base from a file path."""
    if not kb_path_str:
        return None

    # Resolve path relative to project root if it's not absolute
    kb_path = Path(kb_path_str)
    if not kb_path.is_absolute():
        kb_path = PROJECT_ROOT / kb_path_str

    if not kb_path.exists():
        print(f"Warning: Knowledge base file not found at {kb_path}")
        return None

    try:
        # Create a vector db path in the project .gemini folder or similar to persist embeddings
        vector_db_path = PROJECT_ROOT / "lancedb_data"
        
        # Initialize VectorDB
        vector_db = LanceDb(
            table_name=f"kb_{kb_path.stem}",
            uri=str(vector_db_path),
            search_type=SearchType.hybrid,
        )
        
        knowledge_base = Knowledge(vector_db=vector_db)
        
        if kb_path.suffix.lower() == '.pdf':
            if PDFReader:
                knowledge_base.add_content(skip_if_exists=True,path=str(kb_path), reader=PDFReader(chunk=True))
                return knowledge_base
            else:
                print("Warning: PDFReader not available.")
                return None
        elif kb_path.suffix.lower() in ['.txt', '.md','.html']:
            if TextReader:
                knowledge_base.add_content(skip_if_exists=True,path=str(kb_path), reader=TextReader(chunk=True))
                return knowledge_base
            else:
                # Fallback if TextReader missing: just try adding without reader or skip
                print("Warning: TextReader not available.")
                return None
        else:
            print(f"Warning: Unsupported knowledge base format: {kb_path.suffix}")
            return None

    except Exception as e:
        print(f"Error loading knowledge base from {kb_path}: {e}")
        # traceback.print_exc()
        return None


def get_model(model_type: str):
    """Factory to get the appropriate model instance based on configuration."""
    model_type = model_type.lower() if model_type else 'slow'
    
    provider = os.getenv(f"{model_type.upper()}_MODEL_PROVIDER", "gemini").lower()
    model_id = os.getenv(f"{model_type.upper()}_MODEL_NAME", "gemini-2.5-flash")
    host = os.getenv(f"{model_type.upper()}_MODEL_HOST", "http://localhost:11434")
    
    print(f"DEBUG: Loading {model_type} model: {provider}/{model_id}")

    if provider == 'ollama':
        return Ollama(id=model_id, host=host)
    elif provider == 'gemini':
         return Gemini(id=model_id)
    else:
        print(f"Warning: Unknown provider '{provider}', defaulting to Gemini")
        return Gemini(id=model_id)

def load_agents() -> List[Agent]:
    """Loads agents from the database and initializes them."""
    conn = get_db_connection()
    if not conn:
        return []

    agents = []
    try:
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM agents WHERE status='production'")
        rows = cursor.fetchall()
        agents = []
        for row in rows:
            agent_data = dict(row)
            print(f"Loading agent: {agent_data['subject']} ({agent_data['type']})")

            instructions = [
                f"You are a agent specializing in '{agent_data['subject']}'.",
                f"Your behavior should be: '{agent_data['behaviour']}'.",
            ]

            role = "Useful Assistant"
            if agent_data.get('behaviour'):
                role = agent_data['behaviour']
            if agent_data.get('details'):
                instructions.append(f"Additional details: {agent_data['details']}")

            # Load Knowledge Base
            knowledge_base = None
            kb_files = []
            
            # 1. Fetch from agent_documents table
            try:
                cursor.execute("SELECT filename FROM agent_documents WHERE agent_id = ?", (agent_data['id'],))
                doc_rows = cursor.fetchall()
                for doc in doc_rows:
                     kb_files.append(doc['filename'])
            except sqlite3.Error as e:
                print(f"  - Error fetching documents: {e}")

            # 2. Check legacy column for backward compatibility
            if agent_data.get('knowledge_base') and agent_data['knowledge_base'] not in kb_files:
                 kb_files.append(agent_data['knowledge_base'])

            if kb_files:
                # Create one KnowledgeBase per agent if files exist
                vector_db_path = PROJECT_ROOT / "lancedb_data"
                vector_db = LanceDb(
                    table_name=f"kb_agent_{agent_data['id']}",
                    uri=str(vector_db_path),
                    search_type=SearchType.hybrid,
                    embedder=GeminiEmbedder(),
                )
                knowledge_base = Knowledge(vector_db=vector_db)
                
                for kb_file in kb_files:
                    kb_path = PROJECT_ROOT / "public" / "uploads" / kb_file
                    # Fallback to older path if not in uploads/
                    if not kb_path.exists():
                         kb_path = PROJECT_ROOT / kb_file
                    
                    if kb_path.exists():
                        print(f"  - Adding document: {kb_file}")
                        if kb_path.suffix.lower() == '.pdf':
                            if PDFReader:
                                knowledge_base.add_content(path=str(kb_path), reader=PDFReader(chunk=True), skip_if_exists=True)
                        elif kb_path.suffix.lower() in ['.txt', '.md', '.html']:
                             if TextReader:
                                knowledge_base.add_content(path=str(kb_path), reader=TextReader(chunk=True), skip_if_exists=True)
                    else:
                        print(f"  - Warning: Document not found: {kb_file}")

            # Initialize Agno Agent
            try:
                # Use helper to get model based on agent type ('Fast' or 'Slow')
                agent_type = agent_data.get('type', 'slow').lower()
                agentModel = get_model(agent_type)
                
                agent = Agent(
                    model=agentModel,
                    description=f"Agent for {agent_data['subject']}",
                    role=role,
                    instructions=instructions,
                    knowledge=knowledge_base,
                    markdown=True
                )
                agents.append(agent)
            except Exception as e:
                print(f"  - Error initializing agent object: {e}")

    except sqlite3.Error as e:
        print(f"Database query error: {e}")
    finally:
        conn.close()

    return agents


    return agents


class LoggingTeam(Team):
    def log_message(self, user_id: str, sender: str, content: str, media_type: str = None, media_url: str = None):
        conn = get_db_connection()
        if not conn:
            return

        try:
            cursor = conn.cursor()
            
            # 1. Get or Create Conversation
            cursor.execute("SELECT id FROM conversations WHERE user_id = ?", (user_id,))
            row = cursor.fetchone()
            
            if row:
                conversation_id = row['id']
                # Update timestamp
                cursor.execute("UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?", (conversation_id,))
            else:
                cursor.execute("INSERT INTO conversations (user_id) VALUES (?)", (user_id,))
                conversation_id = cursor.lastrowid
            
            # 2. Insert Message
            cursor.execute(
                "INSERT INTO messages (conversation_id, sender, content, media_type, media_url) VALUES (?, ?, ?, ?, ?)",
                (conversation_id, sender, content, media_type, media_url)
            )
            
            conn.commit()
        except sqlite3.Error as e:
            print(f"Error logging message: {e}")

        finally:
            conn.close()

    def get_conversation_history(self, user_id: str, limit: int = 10) -> str:
        """Fetches recent conversation history from the database."""
        conn = get_db_connection()
        if not conn:
            return ""
            
        history_text = ""
        try:
            cursor = conn.cursor()
            # Fetch conversation ID
            cursor.execute("SELECT id FROM conversations WHERE user_id = ?", (user_id,))
            row = cursor.fetchone()
            
            if row:
                conversation_id = row['id']
                # Fetch recent messages
                cursor.execute("""
                    SELECT sender, content 
                    FROM messages 
                    WHERE conversation_id = ? 
                    ORDER BY id DESC LIMIT ?
                """, (conversation_id, limit))
                
                rows = cursor.fetchall()
                # Reverse to chronological order
                rows = reversed(rows)
                
                history_entries = []
                for msg in rows:
                    role = "User" if msg['sender'] == 'user' else "Agent"
                    history_entries.append(f"{role}: {msg['content']}")
                
                if history_entries:
                    history_text = "Previous Conversation History:\n" + "\n".join(history_entries) + "\n---\n"
                    
        except sqlite3.Error as e:
            print(f"Error fetching history: {e}")
        finally:
            conn.close()
            
        return history_text


 

    def run(self, input: Any = None, *args, **kwargs) -> Any:
        # Extract Session ID (User Number)
        # In Agno/WebApp, session_id is often passed or we need to check kwargs/input structure
        # Input for Whatsapp might be a dict or message object depending on implementation details identified.
        # Based on typical usage, input might contain the message, and session_id is in kwargs or extractable.
        
        session_id = kwargs.get("session_id")
        
        # If input is a Message object or similar, try to convert to string for logging
        user_message = str(input)
        
        if session_id:
             self.log_message(session_id, "user", user_message, media_type=kwargs.get("media_type"), media_url=kwargs.get("media_url"))
             
             # INJECT HISTORY
             history_context = self.get_conversation_history(session_id)
             if history_context:
                 if isinstance(input, str):
                     # If input is string, prepend history
                     input = history_context + "\n" + input
                 # Note: If input is not a string (e.g. list of messages), this simple prepend might need adjustment.
                 # But for basic usage here, string input is expected.
        
        # Execute original run
        response = super().run(input=input, *args, **kwargs)
        
        # Extract and log response
        # Response typically is a RunResponse object or iterator. 
        # For serve(), it might be different. We need to be careful with streams.
        # super().run returns a RunResponse which has content.
        
        if session_id and response:
             agent_message = str(response.content)
             self.log_message(session_id, "agent", agent_message)
             
        return response

    async def arun(self, input: Any = None, *args, **kwargs) -> Any:
        # Async version
        # Async version
        session_id = kwargs.get("session_id")
        user_message = str(input)
        
        if session_id:
             self.log_message(session_id, "user", user_message, media_type=kwargs.get("media_type"), media_url=kwargs.get("media_url"))


             # INJECT HISTORY
             history_context = self.get_conversation_history(session_id)
             if history_context:
                 if isinstance(input, str):
                     # Inject User ID info explicitly so the agent knows it for tool calls
                     # Assuming session_id matches the format "wa:PHONE" or just "PHONE"
                     # The prompt uses "user_id" as the argument name.
                     # We extract the pure phone number if it has "wa:" prefix
                     clean_user_id = session_id.replace("wa:", "")
                     input = f"Current User ID: {clean_user_id}\n" + history_context + "\n" + input


        # Execute original run
        response = await super().arun(input=input, *args, **kwargs)
        
        media_type = None
        media_url = None
        
        # Check if the tool was used and returned a file path
        # Check for path pattern in the content
        if response and response.content:
            import re
            # Regex to find the path, handling both forward and back slashes, looking for public/uploads/audio/...
            # Updated to support wav, ogg, mp3 AND subfolders (e.g. public/uploads/audio/userid/speech_...)
            match = re.search(r"(?:[a-zA-Z]:)?[\\/].*?public[\\/]uploads[\\/]audio[\\/].*?speech_[\w-]+\.(?:wav|ogg|mp3)", str(response.content).replace("\\\\", "\\"))
            
            if match:
                file_path_str = match.group(0).strip()
                response.response_audio_url_internal = file_path_str
                
                # For logging - Construct relative URL for web playback
                # file_path_str is absolute. We need relative to project root / public
                # We know the structure is .../public/uploads/audio/...
                # So we want /uploads/audio/...
                try:
                     # Find index of 'uploads' and slice from there
                     path_obj = Path(file_path_str)
                     parts = path_obj.parts
                     if "uploads" in parts:
                         idx = parts.index("uploads")
                         # Join from uploads/ onwards, prepending /
                         media_url = "/" + "/".join(parts[idx:])
                     else:
                         media_url = file_path_str # Fallback
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

print(f"Searching for database at: {DB_PATH}")
loaded_agents = load_agents()
db = SqliteDb(db_file="teamMemory.db")
team = LoggingTeam(
    add_memories_to_context=True, 
    db=db,
    # add_history_to_context=True, # Disabled to use custom injection
    # db=db, # Disabled to use custom injection
    role="""Seu nome é Parente, voce foi criado pela Solved, e voce é responsavel por responder as perguntas 
    dos usuarios, da forma mais simples e direta possivel, coorden as perguntas ou partes dela para os 
    membros do time, cada membro é especialista em um assunto então voce pode perguntar a varios deles. 
    Sempre tente sumarizar as respostas. Seja muito Claro e Direto.
    
    Quando perguntado explique que voce é um assistente virtual multi-agente criado pela Solved para o Projeto Conexão Povos da Floresta. Seja sempre amigavel.
    A sua versão atual é a 0.0.1-RC. 
    Quando for perguntado sobre quais temas voce pode ajudar, os temas são os mesmos dos membros do seu time, explique ao usuario quais os temas que seu time pode ajudar.
    
    - Jamais responda a perguntas que voce nao possa responder, ou seja, perguntas que voce nao tenha conhecimento.
    - Jamais responda perguntas politicas, religiosas ou filosoficas.
    - Responda apenas perguntas que estejam no dominio do time que voce coordena e sobre o projeto conexão povos da floresta.
    - Se o usuário pedir para responder em áudio ou enviar uma mensagem de voz, você DEVE usar a ferramenta `generate_speech` para gerar o áudio.
    - IMPORTANTE: Ao usar a ferramenta `generate_speech`, você DEVE passar o `user_id` (que é o número de telefone do usuário) como argumento.
    - IMPORTANTE: `generate_speech` retorna o caminho do arquivo. Você DEVE incluir esse caminho na sua resposta final TEXTUAL.
    - IMPORTANTE: somente chame a ferramenta `AudioGenerator` se voce já terminou a comunicação interna e sumarizou as respostas.
    - SEAMLESS: Nunca diga "De acordo com o agente X" ou "O especialista Y disse". Sintetize a informação como se fosse conhecimento seu (Do Parente). Você é uma entidade única para o usuário.
    - Não cite nomes de membros do time. A resposta deve ser fluida e direta.
    - Por último, use a tool com o resultado sintetizado.
    """,
    members=loaded_agents,
    delegate_to_all_members=True,
    tools=[AudioGenerator()],
    model=get_model('slow'),
    respond_directly=False,
    markdown=True)

agent_os = AgentOS(
    teams=[team],
    interfaces=[Whatsapp(team=team)],
)
app = agent_os.get_app()

if __name__ == "__main__":
    agent_os.serve(app="parente:app", host="0.0.0.0", port=3000, reload=True)
