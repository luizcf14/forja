import sqlite3
from agno.tools import Toolkit
from core.config import DB_PATH

class FeatureRequestTool(Toolkit):
    def __init__(self):
        super().__init__(name="feature_request_tool")
        self.register(self.log_feature_request)

    def log_feature_request(self, user_identifier: str, request_description: str, importance: str = "normal") -> str:
        """
        Logs a feature request or reported missing capability from a user into the database.
        Use this tool when a user explicitly asks for a feature we don't have, or mentions something is important but missing.

        :param user_identifier: The identifier of the user (e.g., phone number or username).
        :param request_description: A concise description of what the user requested.
        :param importance: The importance level indicated by the user. Can be 'high', 'normal', or 'low'. Defaults to 'normal'.
        :return: A confirmation message.
        """
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            
            cursor.execute(
                "INSERT INTO user_requests (user_identifier, request_text, importance, created_at) VALUES (?, ?, ?, datetime('now'))",
                (user_identifier, request_description, importance)
            )
            
            conn.commit()
            conn.close()
            return f"Feature request logged successfully for user {user_identifier}."
        except Exception as e:
            return f"Error logging feature request: {str(e)}"
