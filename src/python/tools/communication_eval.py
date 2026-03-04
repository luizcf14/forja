import sqlite3
import json
from agno.tools import Toolkit
from core.config import DB_PATH

class CommunicationEvalTool(Toolkit):
    def __init__(self):
        super().__init__(name="communication_eval_tool")
        self.register(self.log_communication_failure)

    def log_communication_failure(self, user_identifier: str, trigger_message: str) -> str:
        """
        Logs a communication failure report whenever the user claims or implies that the AI did not understand them.
        This captures the user's identifier and the exact message where they complained, saving the previous context so developers can evaluate the AI's performance.

        :param user_identifier: The identifier of the user (e.g., phone number or username).
        :param trigger_message: The exact message sent by the user stating they were not understood.
        :return: A confirmation message.
        """
        try:
            conn = sqlite3.connect(DB_PATH)
            conn.row_factory = sqlite3.Row
            cursor = conn.cursor()
            
            # Fetch the most recent 5 messages for this user
            # We first find the user's conversation 
            cursor.execute("SELECT id FROM conversations WHERE user_id = ?", (user_identifier,))
            conv_row = cursor.fetchone()
            
            last_messages_list = []
            
            if conv_row:
                conv_id = conv_row['id']
                cursor.execute(
                    "SELECT sender, content, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 5",
                    (conv_id,)
                )
                messages = cursor.fetchall()
                # Reverse to have them in chronological order
                for msg in reversed(messages):
                    last_messages_list.append({
                        "sender": msg["sender"],
                        "content": dict(msg).get("content", ""),
                        "created_at": msg["created_at"]
                    })
            
            messages_json = json.dumps(last_messages_list, ensure_ascii=False)
            
            cursor.execute(
                "INSERT INTO communication_evaluations (user_identifier, trigger_message, last_messages, created_at) VALUES (?, ?, ?, datetime('now'))",
                (user_identifier, trigger_message, messages_json)
            )
            
            conn.commit()
            conn.close()
            return f"Communication failure logged successfully for user {user_identifier}. Context saved."
        except Exception as e:
            return f"Error logging communication failure: {str(e)}"
