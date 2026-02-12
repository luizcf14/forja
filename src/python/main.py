import sys
from fastapi import Request
from agno.os import AgentOS
from agno.db.sqlite import SqliteDb
from agno.models.google import Gemini

# Import custom components
from core.config import DB_PATH, get_model_config
from core.database import get_db_connection
from core.models import get_model
from core.team import ParenteTeam
from agents.factory import load_agents

# Tools and Services
from utils.whatsapp.whatsapp import Whatsapp
from tools.audio_gen import AudioGenerator
from services.analyzer import ConversationAnalyzer

print(f"Searching for database at: {DB_PATH}")

# 1. Load Agents
loaded_agents = load_agents()

# 2. Initialize Memory
db = SqliteDb(db_file="teamMemory.db")

# 3. Initialize Team (Parente)
# Parente Team Configuration
parente_role = """Seu nome é Parente, voce foi criado pela Solved, e voce é responsavel por responder as perguntas 
    dos usuarios, da forma mais simples e direta possivel, coorden as perguntas ou partes dela para os 
    membros do time, cada membro é especialista em um assunto então voce pode perguntar a varios deles. 
    Sempre tente sumarizar as respostas. Seja muito Claro e Direto.
    
    Quando perguntado explique que voce é um assistente virtual multi-agente criado pela Solved para o Projeto Conexão Povos da Floresta. Seja sempre amigavel.
    A sua versão atual é a 0.0.1-RC. 
    Quando for perguntado sobre quais temas voce pode ajudar, os temas são os mesmos dos membros do seu time, explique ao usuario quais os temas que seu time pode ajudar.
    
    - Jamais responda a perguntas que voce nao possa responder, ou seja, perguntas que voce nao tenha conhecimento.
    - Jamais responda perguntas politicas, religiosas ou filosoficas.
    - Responda apenas perguntas que estejam no dominio do time que voce coordena e sobre o projeto conexão povos da floresta.
    - Se o usuário pedir para responder em áudio ou enviar uma mensagem de voz, você DEVE usar a ferramenta `generate_speech` para gerar o áudio.
    - IMPORTANTE: Ao usar a ferramenta `generate_speech`, você DEVE passar o `user_id` (que é o número de telefone do usuário) como argumento.
    - IMPORTANTE: `generate_speech` retorna o caminho do arquivo. Você DEVE incluir esse caminho na sua resposta final TEXTUAL.
    - IMPORTANTE: somente chame a ferramenta `AudioGenerator` se voce já terminou a comunicação interna e sumarizou as respostas.
    - SEAMLESS: Nunca diga "De acordo com o agente X" ou "O especialista Y disse". Sintetize a informação como se fosse conhecimento seu (Do Parente). Você é uma entidade única para o usuário.
    - Não cite nomes de membros do time. A resposta deve ser fluida e direta.
    - Por último, use a tool com o resultado sintetizado.
    """

team = ParenteTeam(
    add_memories_to_context=True, 
    db=db,
    role=parente_role,
    members=loaded_agents,
    delegate_to_all_members=True,
    tools=[AudioGenerator()],
    model=Gemini(id="gemini-2.5-flash"), # Parente's main model, maybe move to config too
    respond_directly=False,
    markdown=True
)

# 4. Initialize AgentOS App
agent_os = AgentOS(
    teams=[team],
    interfaces=[Whatsapp(team=team)],
)
app = agent_os.get_app()

# 5. Add Custom Endpoints (Analyzer)
@app.post("/analyze_conversation")
async def analyze_conversation(request: Request):
    """Endpoint to analyze conversation sentiment and topic."""
    data = await request.json()
    conversation_id = data.get("id")
    force = data.get("force", False)
    
    if not conversation_id:
        return {"error": "Missing conversation ID"}
    
    conn = get_db_connection()
    if not conn:
        return {"error": "Database connection failed"}
        
    try:
        # Using slow model (Gemini) to avoid errors
        model = get_model('slow')
        analyzer = ConversationAnalyzer(model)
        
        result = analyzer.analyze(conversation_id, conn, force=force)
        
        conn.close()
        return result
        
    except Exception as e:
        if conn: conn.close()
        print(f"Analysis endpoint error: {e}")
        return {"error": str(e)}

if __name__ == "__main__":
    agent_os.serve(app="main:app", host="0.0.0.0", port=3000, reload=True)
