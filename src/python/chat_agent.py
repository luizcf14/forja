import sys
import json
import os
import logging

# Disable Agno debug logs
os.environ["AGNO_DEBUG"] = "false"

# Configure logging to suppress debug output
logging.basicConfig(level=logging.ERROR)
logging.getLogger("agno").setLevel(logging.ERROR)

from agno.agent import Agent
from agno.models.openai import OpenAIChat
from agno.models.google import Gemini
# from agno.knowledge.pdf import PDFUrlKnowledgeBase, PDFKnowledgeBase
# from agno.vectordb.lancedb import LanceDb, SearchType

import sys
import json
import os
import logging


# Configure logging to suppress debug output
logging.basicConfig(level=logging.ERROR)
logging.getLogger("agno").setLevel(logging.ERROR)

from agno.agent import Agent
from agno.models.openai import OpenAIChat
from agno.models.google import Gemini
# from agno.knowledge.pdf import PDFUrlKnowledgeBase, PDFKnowledgeBase
# from agno.vectordb.lancedb import LanceDb, SearchType

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Missing input file argument"}))
        return

    input_file = sys.argv[1]
    output_file = sys.argv[2] if len(sys.argv) > 2 else None
    
    try:
        with open(input_file, 'r') as f:
            data = json.load(f)
    except Exception as e:
        err = json.dumps({"error": f"Failed to read input file: {str(e)}"})
        if output_file:
            with open(output_file, 'w') as f:
                f.write(err)
        else:
            print(err)
        return

    agent_config = data.get('agent', {})
    message = data.get('message', '')
    session_id = data.get('session_id', '')
    history = data.get('history', [])
    uploads_dir = data.get('uploads_dir', '')

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
    kb_file = agent_config.get('knowledge_base')
    if kb_file:
        kb_path = os.path.join(uploads_dir, kb_file)
        if os.path.exists(kb_path):
            try:
                knowledge_base = PDFKnowledgeBase(
                    path=kb_path,   
                    vector_db=LanceDb(
                        table_name="agent_docs",
                        uri="tmp/lancedb_agent_forge",
                        search_type=SearchType.hybrid,
                    ),
                )
                knowledge_base.load(recreate=False) 
            except Exception as e:
                pass

    # System Prompt
    system_prompt = agent_config.get('behaviour', 'You are a helpful AI assistant.')
    if agent_config.get('details'):
        system_prompt += f"\n\nAdditional Details:\n{agent_config.get('details')}"

    try:
        # Create Agent
        GOOGLE_API_KEY="AIzaSyCO6D8dGvyVw-J_B7HdnJw9u9BsfH_tDUk"
        agent = Agent(
            model=Gemini(id=model_id, api_key=GOOGLE_API_KEY),
            description=system_prompt,
            instructions=[system_prompt],
            knowledge=knowledge_base,
            debug_mode=True,
            markdown=True,
            # session_id=session_id, # Agno Agent might not take session_id directly in init without storage
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

        result = json.dumps({
            "response": response.content,
            "metrics": metrics_data,
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
