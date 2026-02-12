import sqlite3
from typing import Optional
from .config import DB_PATH

def get_db_connection() -> Optional[sqlite3.Connection]:
    """Establishes a connection to the SQLite database."""
    if not DB_PATH.exists():
        print(f"Error: Database not found at {DB_PATH}")
        return None
    
    try:
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        return conn
    except sqlite3.Error as e:
        print(f"Error connecting to database: {e}")
        return None
