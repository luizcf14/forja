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
                knowledge_base.add_content(path=str(kb_path), reader=PDFReader(chunk=True))
                return knowledge_base
            else:
                print("Warning: PDFReader not available.")
                return None
        elif kb_path.suffix.lower() in ['.txt', '.md','.html']:
            if TextReader:
                knowledge_base.add_content(path=str(kb_path), reader=TextReader(chunk=True))
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

def main():
    print(f"Searching for database at: {DB_PATH}")
    loaded_agents = load_agents()

team = Team(
    role="Seu nome é Parente, voce é responsavel por responder as perguntas dos usuarios, da forma mais simples e direta possivel, redirecionando as perguntas ou partes dela para os membros do time. Sempre tente sumarizar as respostas",
    members=loaded_agents,
    model=Gemini(id="gemini-2.5-flash"),
    respond_directly=False,
    markdown=True)
    print("\n--- Team Chat (type 'exit' to quit) ---")
    while True:
        try:
            user_input = input("User: ")
            if user_input.lower() in ['exit', 'quit']:
                break
            team.print_response(user_input, stream=True)
        except KeyboardInterrupt:
            break
        except Exception as e:
            print(f"Error: {e}")
if __name__ == "__main__":
    main()
