import json
try:
    from agno.agent import Agent
    print("Imports successful")
except Exception as e:
    print(f"Import failed: {e}")
