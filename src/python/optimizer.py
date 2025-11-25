import sys
import json
import os

try:
    from agno.agent import Agent
    from agno.models.google import Gemini
except ImportError:
    # Fallback or error if agno is not installed
    print(json.dumps({"error": "Agno framework or Google provider not installed. Please run 'pip install agno google-genai'."}))
    sys.exit(1)

def optimize_agent_config(input_text):
    # Initialize the agent
    # Ensure GOOGLE_API_KEY is set in your environment
    # api_key = os.getenv("GOOGLE_API_KEY")
    GOOGLE_API_KEY="AIzaSyCO6D8dGvyVw-J_B7HdnJw9u9BsfH_tDUk"
    # if not api_key:
    #     return "Error: GOOGLE_API_KEY environment variable not set."

    agent = Agent(
        model=Gemini(id="gemini-2.5-flash", api_key=GOOGLE_API_KEY),
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
