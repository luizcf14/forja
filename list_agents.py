import sqlite3
import os

db_path = 'database.sqlite'

if not os.path.exists(db_path):
    print(f"Database file not found at {db_path}")
else:
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    cursor.execute("SELECT id, subject, knowledge_base FROM agents")
    agents = cursor.fetchall()
    print("Agents:")
    for agent in agents:
        print(f"ID: {agent[0]}, Subject: {agent[1]}, KB: {agent[2]}")
    conn.close()
