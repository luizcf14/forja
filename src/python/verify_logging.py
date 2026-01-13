import sys
import os
from pathlib import Path
import sqlite3

# Add src/python to sys.path
SCRIPT_DIR = Path(__file__).parent.absolute()
sys.path.append(str(SCRIPT_DIR))

try:
    from parente import LoggingTeam, loaded_agents, get_db_connection
    from agno.models.google import Gemini
    
    print("Imported LoggingTeam successfully.")
    
    # Create a dummy team instance (mocking members to avoid heavy load if needed)
    team = LoggingTeam(
        members=[], # Empty members for unit test
        model=Gemini(id="gemini-2.5-flash"), 
    )
    
    session_id = "5511999999999"
    user_msg = "Teste direto de log"
    agent_msg = "Resposta de teste"
    
    print(f"Logging message manually for {session_id}...")
    team.log_message(session_id, "user", user_msg)
    team.log_message(session_id, "agent", agent_msg)
    
    print("Messages logged.")
    
    # Verify directly via python sqlite
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM conversations WHERE user_id = ?", (session_id,))
    conv = cursor.fetchone()
    if conv:
        print(f"Conversation found: {dict(conv)}")
        conversation_id = conv['id']
        cursor.execute("SELECT * FROM messages WHERE conversation_id = ?", (conversation_id,))
        msgs = cursor.fetchall()
        for m in msgs:
            print(f"Message: {dict(m)}")
    else:
        print("Conversation NOT found.")
        
    conn.close()
    
except Exception as e:
    print(f"Error during verification: {e}")
    import traceback
    traceback.print_exc()
