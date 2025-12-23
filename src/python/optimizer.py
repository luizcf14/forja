import sys
import json
import os
from dotenv import load_dotenv
load_dotenv()

try:
    from agno.agent import Agent
    from agno.models.google import Gemini
except ImportError:
    # Fallback or error if agno is not installed
    print(json.dumps({"error": "Agno framework or Google provider not installed. Please run 'pip install agno google-genai'."}))
    sys.exit(1)

def optimize_agent_config(input_text):
    agent = Agent(
        model=Gemini(id="gemini-2.5-flash"),
        description="You are an expert AI Agent Architect.",
        instructions=[
            "Your goal is to optimize the configuration of other AI agents.",
            "You will receive a description of an agent's subject, type, and behavior.",
            "You must improve the behavior description to be more effective, precise, and professional.",
            "Return ONLY the optimized behavior text, nothing else."
        ],
        markdown=False
    )

    try:
        response = agent.run(input_text)
        return response.content
    except Exception as e:
        return f"Error during optimization: {str(e)}"

if __name__ == "__main__":
    # Read input from stdin
    try:
        input_data = sys.stdin.read()
        if not input_data:
            print(json.dumps({"error": "No input provided"}))
            sys.exit(1)
            
        # Parse JSON input if possible, otherwise treat as raw text
        try:
            data = json.loads(input_data)
            text_to_optimize = data.get("text", "")
        except json.JSONDecodeError:
            text_to_optimize = input_data

        if not text_to_optimize:
             print(json.dumps({"error": "Empty input text"}))
             sys.exit(1)

        optimized_text = optimize_agent_config(text_to_optimize)
        
        # Output JSON
        print(json.dumps({"optimized_text": optimized_text}))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)
