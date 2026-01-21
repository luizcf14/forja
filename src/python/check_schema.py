import sqlite3
import os

db_path = "c:\\Users\\Solved-Blerys-Win\\Documents\\Povos\\Forja\\forja\\database.sqlite"

if not os.path.exists(db_path):
    print("Database not found!")
else:
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        cursor.execute("PRAGMA table_info(messages)")
        rows = cursor.fetchall()
        print("Columns in messages table:")
        for row in rows:
            print(f" - {row[1]} ({row[2]})")
        conn.close()
    except Exception as e:
        print(f"Error reading DB: {e}")
