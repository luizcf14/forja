import sqlite3
import json
from agno.agent import Agent
from typing import Optional, Dict, Any

class ConversationAnalyzer:
    def __init__(self, model):
        self.model = model

    def analyze(self, conversation_id: int, conn, force: bool = False) -> Dict[str, Any]:
        """
        Analyzes the conversation history to determine sentiment and topic.
        
        Args:
            conversation_id: The ID of the conversation.
            conn: Active SQLite connection object.
            force: If True, bypasses the check for existing analysis.
            
        Returns:
            Dict containing status, success, sentiment, and topic.
        """
        try:
            cursor = conn.cursor()
            
            # Check if analysis is needed
            if not force:
                cursor.execute("SELECT last_message_at, last_analyzed_at, sentiment, topic FROM conversations WHERE id = ?", (conversation_id,))
                row = cursor.fetchone()
                if row:
                    last_msg = row['last_message_at']
                    last_analyzed = row['last_analyzed_at']
                    
                    # If already analyzed, skip unless forced
                    if last_analyzed:
                        return {
                            "success": True, 
                            "status": "skipped", 
                            "reason": "manual_reval_only",
                            "sentiment": row['sentiment'],
                            "topic": row['topic']
                        }

            # Fetch History
            cursor.execute("SELECT sender, content FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 100", (conversation_id,))
            rows = cursor.fetchall()
            messages = []
            for r in rows:
                sender = r['sender']
                content = r['content']
                messages.append(f"{sender}: {content}")
            messages.reverse()
            history_text = "\n".join(messages)
            
            if not history_text:
                return {"status": "skipped", "reason": "No history"}

            # Setup Agent
            analyzer = Agent(
                model=self.model,
                instructions="You are an expert conversation analyzer. Return ONLY JSON.",
                markdown=False
            )
            
            prompt = f"""
            Analyze the following conversation history between a 'user' and an 'agent'.
            Categorize the USER's sentiment and the MAIN topic of the conversation.
            
            Allowed Sentiments: Neutro, Contente, Feliz, Raiva, Frustração.
            Allowed Topics: Suporte, Dúvidas Gerais, Dúvidas sobre Políticas Públicas, Sugestão, Outros.
            
            Return ONLY valid JSON format:
            {{
                "sentiment": "...",
                "topic": "..."
            }}
            
            History:
            {history_text}
            """
            
            response = analyzer.run(prompt)
            content = response.content
            
            # Clean markdown code blocks if present
            if "```json" in content:
                content = content.replace("```json", "").replace("```", "")
            
            result = json.loads(content)
            sentiment = result.get("sentiment", "Neutro")
            topic = result.get("topic", "Outros")
            
            # Update Database
            cursor.execute("UPDATE conversations SET sentiment = ?, topic = ?, last_analyzed_at = CURRENT_TIMESTAMP WHERE id = ?", (sentiment, topic, conversation_id))
            conn.commit()
            
            return {"success": True, "sentiment": sentiment, "topic": topic, "status": "analyzed"}
            
        except Exception as e:
            print(f"Analysis error: {e}")
            return {"error": str(e)}
