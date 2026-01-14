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
    from agno.agent import Agent
    from agno.models.google import Gemini
    from agno.knowledge.embedder.google import GeminiEmbedder
    from agno.knowledge.knowledge import Knowledge
    from agno.vectordb.lancedb import LanceDb, SearchType
    from agno.team import Team
    from agno.os import AgentOS
    from agno.os.interfaces.whatsapp import Whatsapp
    
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
                # Initialize VectorDB shared for this agent? Or create kb per file? 
                # Agno Knowledge can usually take multiple sources.
                # But here we are using load_knowledge_base helper which returns a KB object.
                # We should probably refactor to create one Knowledge object and add multiple sources.
                
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
                                knowledge_base.add_content(path=str(kb_path), reader=PDFReader(chunk=True))
                        elif kb_path.suffix.lower() in ['.txt', '.md', '.html']:
                             if TextReader:
                                knowledge_base.add_content(path=str(kb_path), reader=TextReader(chunk=True))
                    else:
                        print(f"  - Warning: Document not found: {kb_file}")

            # Initialize Agno Agent
            # Using Gemini as default model as seen in optimizer.py, default to gemini-2.5-flash
            try:
                agent = Agent(
                    model=Gemini(id="gemini-2.5-flash"), # Using a standard available model ID or the one from optimizer
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
    def log_message(self, user_id: str, sender: str, content: str):
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
                "INSERT INTO messages (conversation_id, sender, content) VALUES (?, ?, ?)",
                (conversation_id, sender, content)
            )
            
            conn.commit()
        except sqlite3.Error as e:
            print(f"Error logging message: {e}")
        finally:
            conn.close()

    def get_ai_status(self, conversation_id: int) -> str:
        conn = get_db_connection()
        if not conn:
            return 'active'
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT ai_status FROM conversations WHERE id = ?", (conversation_id,))
            row = cursor.fetchone()
            if row:
                return row['ai_status']
        except sqlite3.Error as e:
            print(f"Error getting AI status: {e}")
        finally:
            conn.close()
        return 'active'

    def get_conversation_id(self, user_id: str) -> Optional[int]:
         conn = get_db_connection()
         if not conn:
             return None
         try:
             cursor = conn.cursor()
             cursor.execute("SELECT id FROM conversations WHERE user_id = ?", (user_id,))
             row = cursor.fetchone()
             if row:
                 return row['id']
         except sqlite3.Error:
             pass
         finally:
             conn.close()
         return None


    def run(self, input: Any = None, *args, **kwargs) -> Any:
        # Extract Session ID (User Number)
        # In Agno/WebApp, session_id is often passed or we need to check kwargs/input structure
        # Input for Whatsapp might be a dict or message object depending on implementation details identified.
        # Based on typical usage, input might contain the message, and session_id is in kwargs or extractable.
        
        session_id = kwargs.get("session_id")
        
        # If input is a Message object or similar, try to convert to string for logging
        user_message = str(input)
        
        if session_id:
             self.log_message(session_id, "user", user_message)
             
             # Check AI Status
             conversation_id = self.get_conversation_id(session_id)
             if conversation_id:
                 status = self.get_ai_status(conversation_id)
                 if status == 'paused':
                     print(f"AI is paused for conversation {conversation_id}. Skipping response.")
                     # Return a dummy response or similar to satisfy type hints if needed, 
                     # but typically Agno might expect something. 
                     # Check if we can return None or empty iterator.
                     # Returning None might cause issues if caller expects RunResponse.
                     # Let's try to return a dummy RunResponse or just None if safe.
                     # Safest is to not call super().run() and return a dummy.
                     # However, generating a dummy RunResponse might be complex due to imports.
                     # Let's see... we can just return None and hope Agno handles it or simple string.
                     return "AI Paused" 
        
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
             self.log_message(session_id, "user", user_message)

             # Check AI Status
             conversation_id = self.get_conversation_id(session_id)
             if conversation_id:
                 status = self.get_ai_status(conversation_id)
                 if status == 'paused':
                     print(f"AI is paused for conversation {conversation_id}. Skipping response.")
                     # For async, return a dummy awaitable or just value? arun returns coroutine?
                     # No, it's async def, so it returns value.
                     return "AI Paused"

        response = await super().arun(input=input, *args, **kwargs)
        
        if session_id and response:
             agent_message = str(response.content)
             self.log_message(session_id, "agent", agent_message)
             
        return response

print(f"Searching for database at: {DB_PATH}")
loaded_agents = load_agents()

team = LoggingTeam(
    add_history_to_context=True,
    role="""Seu nome é Parente, voce foi criado pela Solved, e voce é responsavel por responder as perguntas 
    dos usuarios, da forma mais simples e direta possivel, coorden as perguntas ou partes dela para os 
    membros do time, cada membro é especialista em um assunto então voce pode perguntar a varios deles. 
    Sempre tente sumarizar as respostas. Seja muito Claro e Direto.
    
    Quando perguntado explique que voce é um assistente virtual multi-agente criado pela Solved para o Projeto Conexão Povos da Floresta. Seja sempre amigavel.
    A sua versão atual é a 0.0.1-Alpha-Release Candidate. 
    Quando for perguntado sobre quais temas voce pode ajudar, os temas são os mesmos dos membros do seu time, explique ao usuario quais os temas que seu time pode ajudar.
    
    - Jamais responda a perguntas que voce nao possa responder, ou seja, perguntas que voce nao tenha conhecimento.
    - Jamais responda perguntas politicas, religiosas ou filosoficas.
    - Responda apenas perguntas que estejam no dominio do time que voce coordena e sobre o projeto conexão povos da floresta.
    """,
    members=loaded_agents,
    model=Gemini(id="gemini-2.5-flash"),
    respond_directly=False,
    markdown=True)

agent_os = AgentOS(
    teams=[team],
    interfaces=[Whatsapp(team=team)],
)
app = agent_os.get_app()

if __name__ == "__main__":
    agent_os.serve(app="parente:app", host="0.0.0.0", port=3000, reload=True)
