import sqlite3
from typing import List, Dict, Optional, Any
from .database import get_db_connection

class AgentRepository:
    @staticmethod
    def get_production_agents() -> List[Dict[str, Any]]:
        """Fetches all agents with 'production' status."""
        conn = get_db_connection()
        if not conn:
            return []
        
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT * FROM agents WHERE status='production'")
            rows = cursor.fetchall()
            return [dict(row) for row in rows]
        except sqlite3.Error as e:
            print(f"Error fetching agents: {e}")
            return []
        finally:
            conn.close()

    @staticmethod
    def get_agent_documents(agent_id: int) -> List[str]:
        """Fetches documents associated with an agent."""
        conn = get_db_connection()
        if not conn:
            return []
            
        doc_files = []
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT filename FROM agent_documents WHERE agent_id = ?", (agent_id,))
            rows = cursor.fetchall()
            doc_files = [row['filename'] for row in rows]
        except sqlite3.Error as e:
            print(f"Error fetching agent documents: {e}")
        finally:
            conn.close()
            
        return doc_files

class ConversationRepository:
    @staticmethod
    def get_conversation_id(user_id: str) -> Optional[int]:
        """Gets existing conversation ID for a user."""
        conn = get_db_connection()
        if not conn:
            return None
        
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT id FROM conversations WHERE user_id = ?", (user_id,))
            row = cursor.fetchone()
            if row:
                return row['id']
            return None
        except sqlite3.Error:
            return None
        finally:
            conn.close()

    @staticmethod
    def ensure_conversation(user_id: str) -> Optional[int]:
        """Gets existing conversation ID or creates a new one."""
        conn = get_db_connection()
        if not conn:
            return None
        
        try:
            cursor = conn.cursor()
            cursor.execute("SELECT id FROM conversations WHERE user_id = ?", (user_id,))
            row = cursor.fetchone()
            
            if row:
                conversation_id = row['id']
                cursor.execute("UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?", (conversation_id,))
                conn.commit()
                return conversation_id
            else:
                cursor.execute("INSERT INTO conversations (user_id) VALUES (?)", (user_id,))
                conn.commit()
                return cursor.lastrowid
        except sqlite3.Error as e:
            print(f"Error ensuring conversation: {e}")
            return None
        finally:
            conn.close()

    @staticmethod
    def log_message(user_id: str, sender: str, content: str, media_type: str = None, media_url: str = None):
        """Logs a message to the database, ensuring conversation exists."""
        conversation_id = ConversationRepository.ensure_conversation(user_id)
        if not conversation_id:
            return

        conn = get_db_connection()
        if not conn:
            return

        try:
            cursor = conn.cursor()
            cursor.execute(
                "INSERT INTO messages (conversation_id, sender, content, media_type, media_url) VALUES (?, ?, ?, ?, ?)",
                (conversation_id, sender, content, media_type, media_url)
            )
            conn.commit()
        except sqlite3.Error as e:
            print(f"Error logging message: {e}")
        finally:
            conn.close()

    @staticmethod
    def get_history(user_id: str, limit: int = 10) -> List[Dict[str, Any]]:
        """Fetches recent message history for a user."""
        conn = get_db_connection()
        if not conn:
            return []

        try:
            cursor = conn.cursor()
            # We need conversation ID first
            cursor.execute("SELECT id FROM conversations WHERE user_id = ?", (user_id,))
            row = cursor.fetchone()
            
            if not row:
                return []
                
            conversation_id = row['id']
            
            cursor.execute("""
                SELECT sender, content 
                FROM messages 
                WHERE conversation_id = ? 
                ORDER BY id DESC LIMIT ?
            """, (conversation_id, limit))
            
            rows = cursor.fetchall()
            return [dict(row) for row in rows] # Returned in reverse chrono order (newest first)
        except sqlite3.Error as e:
            print(f"Error fetching history: {e}")
            return []
        finally:
            conn.close()

    @staticmethod
    def get_ai_status(phone_number: str) -> str:
        """Check AI status for a given phone number (user_id). handles wa: prefix logic."""
        conn = get_db_connection()
        if not conn:
            return 'active'
        
        try:
            cursor = conn.cursor()
            
            # 1. Try with 'wa:' prefix first
            wa_id = f"wa:{phone_number}"
            cursor.execute("SELECT ai_status FROM conversations WHERE user_id = ?", (wa_id,))
            row = cursor.fetchone()
            
            if not row:
                # 2. Fallback to raw phone number
                cursor.execute("SELECT ai_status FROM conversations WHERE user_id = ?", (phone_number,))
                row = cursor.fetchone()
            
            if row:
                return row['ai_status']
            return 'active'
        except Exception as e:
            print(f"Error checking AI status: {e}")
            return 'active'
        finally:
            conn.close()
