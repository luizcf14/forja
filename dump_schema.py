import sqlite3
import os

db_path = "database.sqlite"
if not os.path.exists(db_path):
    print("Database not found")
else:
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    cursor.execute("SELECT name, sql FROM sqlite_master WHERE type='table'")
    tables = cursor.fetchall()
    for name, sql in tables:
        print(f"--- Table: {name} ---")
        print(sql)
        print("-----------------------")
    conn.close()
