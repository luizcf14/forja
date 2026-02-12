import sys
from typing import List, Optional, Any, Dict
from pathlib import Path

from agno.agent import Agent
from agno.knowledge.knowledge import Knowledge
from agno.vectordb.lancedb import LanceDb, SearchType
from agno.knowledge.embedder.google import GeminiEmbedder

# Import readers safely
try:
    from agno.knowledge.reader.pdf_reader import PDFReader
except ImportError:
    PDFReader = None
try:
    from agno.knowledge.reader.text_reader import TextReader
except ImportError:
    TextReader = None

from core.config import PROJECT_ROOT, VECTOR_DB_PATH
from core.models import get_model
from core.repositories import AgentRepository

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
        # Initialize VectorDB
        vector_db = LanceDb(
            table_name=f"kb_{kb_path.stem}",
            uri=str(VECTOR_DB_PATH),
            search_type=SearchType.hybrid,
        )
        
        knowledge_base = Knowledge(vector_db=vector_db)
        
        if kb_path.suffix.lower() == '.pdf':
            if PDFReader:
                knowledge_base.add_content(skip_if_exists=True, path=str(kb_path), reader=PDFReader(chunk=True))
                return knowledge_base
            else:
                print("Warning: PDFReader not available.")
                return None
        elif kb_path.suffix.lower() in ['.txt', '.md','.html']:
            if TextReader:
                knowledge_base.add_content(skip_if_exists=True, path=str(kb_path), reader=TextReader(chunk=True))
                return knowledge_base
            else:
                print("Warning: TextReader not available.")
                return None
        else:
            print(f"Warning: Unsupported knowledge base format: {kb_path.suffix}")
            return None

    except Exception as e:
        print(f"Error loading knowledge base from {kb_path}: {e}")
        return None

def load_agents() -> List[Agent]:
    """Loads agents from the database and initializes them."""
    agents = []
    
    agent_rows = AgentRepository.get_production_agents()
    
    for agent_data in agent_rows:
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
        kb_files.extend(AgentRepository.get_agent_documents(agent_data['id']))

        # 2. Check legacy column for backward compatibility
        if agent_data.get('knowledge_base') and agent_data['knowledge_base'] not in kb_files:
             kb_files.append(agent_data['knowledge_base'])

        if kb_files:
            # Create one KnowledgeBase per agent if files exist
            vector_db = LanceDb(
                table_name=f"kb_agent_{agent_data['id']}",
                uri=str(VECTOR_DB_PATH),
                search_type=SearchType.hybrid,
                embedder=GeminiEmbedder(),
            )
            knowledge_base = Knowledge(vector_db=vector_db, max_results=3)
            
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
            agent_type = agent_data.get('type', 'fast').lower()
            agentModel = get_model(agent_type)
            
            agent = Agent(
                model=agentModel,
                description=f"Agent for {agent_data['subject']}",
                role=role,
                instructions=instructions,
                knowledge=knowledge_base,
                markdown=True,
            )
            agents.append(agent)
        except Exception as e:
            print(f"  - Error initializing agent object: {e}")

    return agents
