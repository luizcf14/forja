import sys
import json
import os
import logging
from dotenv import load_dotenv
load_dotenv()



# Configure logging to suppress debug output
logging.basicConfig(level=logging.ERROR)
logging.getLogger("agno").setLevel(logging.ERROR)

from agno.agent import Agent
from agno.knowledge.embedder.google import GeminiEmbedder
from agno.models.google import Gemini
from agno.knowledge.knowledge import Knowledge
# from agno.db.sqlite import SqliteDb  # or PostgresDb, etc.
from agno.db.in_memory import InMemoryDb

# from agno.knowledge.pdf import PDFUrlKnowledgeBase, PDFKnowledgeBase
from agno.vectordb.lancedb import LanceDb, SearchType

import sys
import json
import os   
import logging


import io

# Configure logging to capture debug output
# Configure logging to capture debug output
log_capture_string = io.StringIO()
ch = logging.StreamHandler(log_capture_string)
ch.setLevel(logging.DEBUG)
formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
ch.setFormatter(formatter)

root_logger = logging.getLogger()
root_logger.setLevel(logging.DEBUG)
# Remove existing handlers to avoid duplicates or conflicts
if root_logger.hasHandlers():
    root_logger.handlers.clear()
root_logger.addHandler(ch)

logging.getLogger("agno").setLevel(logging.DEBUG)

from agno.agent import Agent
from agno.models.openai import OpenAIChat
from agno.models.google import Gemini
# from agno.knowledge.pdf import PDFUrlKnowledgeBase, PDFKnowledgeBase
# from agno.vectordb.lancedb import LanceDb, SearchType
outString = ""
def main():
    logging.debug("Agent starting...")
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Missing input file argument"}))
        return

    input_file = sys.argv[1]
    output_file = sys.argv[2] if len(sys.argv) > 2 else None
    
    logging.debug(f"DEBUG: Input file: {input_file}")
    try:
        with open(input_file, 'r') as f:
            data = json.load(f)
    except Exception as e:
        print(f"CRITICAL ERROR: {e}")
        return

    
    agent_config = data.get('agent', {})
    message = data.get('message', '')
    session_id = data.get('session_id', '')
    history = data.get('history', [])
    kb_files = data.get('knowledge_base_files', [])
    

    if not message:
        err = json.dumps({"error": "No message provided"})
        if output_file:
            with open(output_file, 'w') as f:
                f.write(err)
        else:
            print(err)
        return

    # Determine Model based on Type
    model_id = "gemini-2.5-flash"
    if agent_config.get('type') == 'Slow':
        model_id = "gemini-2.5-pro"

    # Initialize Knowledge Base if exists
    knowledge_base = None
    outString = ""
    logging.debug(f"Received KB files: {kb_files}")
    if kb_files:
        try:
            
            from agno.knowledge.reader.pdf_reader import PDFReader
            
            # Drop table to ensure correct dimensions
            import lancedb
            import shutil
            
            # lancedb path
            lancedb_path = os.path.abspath("tmp/lancedb_agent_forge")

            vector_db = LanceDb(
                table_name="agent_docs",
                uri=lancedb_path,
                embedder=GeminiEmbedder(id="models/text-embedding-004", dimensions=768),
            )
            
            knowledge_base = Knowledge(
                vector_db=vector_db,
            )
            
            # Add content to Knowledge Base
            for kb_file in kb_files:
                try:
                    kb_path = os.path.abspath(kb_file)
                    if os.path.exists(kb_path):
                        logging.debug(f"Adding to KB: {kb_path}")
                        knowledge_base.add_content(path=kb_path, reader=PDFReader(chunk=True), skip_if_exists=True)
                    else:
                        logging.warning(f"KB file not found: {kb_path}")
                except Exception as e:
                    logging.error(f"Error adding KB file {kb_file}: {e}")

        except Exception as e:
            # Log error but continue without KB if it fails
            logging.error(f"KB Error: {e}")
            pass

    system_prompt = agent_config.get('behaviour', 'You are a helpful AI assistant.')
    if agent_config.get('details'):
        system_prompt += f"\n\nAdditional Details:\n{agent_config.get('details')}"

    try:
        # Create Agent
        # content_list = knowledge_base.get_content_lists()
        # for content in content_list:
             # status, msg = knowledge_base.get_content_status(content.id)
             # outString += f"{content}\n"
        agent = Agent(
            model=Gemini(id=model_id),
            description=system_prompt,
            instructions=[system_prompt],
            knowledge=knowledge_base,
            search_knowledge=True,
            debug_mode=True,          
            markdown=True,
            session_id=session_id,
        )

        # Format history for Agno if needed, or just append to messages
        # Agno Agent.run() typically handles state if storage is provided.
        # Without storage, we can pass previous messages.
        # Converting simple history list to Agno message format if required, 
        # but for now we'll rely on the fact that we are stateless and just running the new message.
        # To truly support memory without persistent storage, we'd need to reconstruct the conversation.
        
        # For this implementation, we will pass the history as context in the prompt if we can't load it into the agent.
        # Or better, we iterate and add messages.
        
        # Simple approach: Prepend history to the current message (not ideal but works for simple context)
        context_str = ""
        for msg in history:
           role = "User" if msg['role'] == 'user' else "Model"
           context_str += f"{role}: {msg['content']}\n"
        
        if context_str:
           message = f"History:\n{context_str}\n\nCurrent Message:\n{message}"

        # Run Agent
        response = agent.run(message)
        
        # Convert metrics to dict if needed, or just extract values
        metrics_data = {}
        if response.metrics:
            try:
                metrics_data = response.metrics.model_dump()
            except:
                metrics_data = str(response.metrics)

        # Log KB files for debugging
        if kb_files:
            logging.debug(f"Received KB files: {kb_files}")
            if outString:
                logging.debug(f"KB Status:\n{outString}")

        result = json.dumps({
            "response": response.content,
            "metrics": metrics_data,
            "debug_logs": log_capture_string.getvalue(),
            "session_id": session_id
        })
        
        if output_file:
            with open(output_file, 'w') as f:
                f.write(result)
        else:
            print(result)

    except Exception as e:
        err = json.dumps({"error": f"Agent execution failed: {str(e)}"})
        if output_file:
            with open(output_file, 'w') as f:
                f.write(err)
        else:
            print(err)

if __name__ == "__main__":
    main()
