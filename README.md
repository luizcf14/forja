# Agent Forge

## How to Run

This project uses PHP's built-in web server.

### Prerequisites
- PHP 8.0 or higher
- Python 3.10+
- FFmpeg (Required for audio support)
    - **Linux**: `sudo apt-get install ffmpeg`
    - **Windows**: `winget install ffmpeg` (or ensure `ffmpeg` is in your PATH)
    - **Note**: Ensure `ffmpeg` is available in your system PATH or set the `FFMPEG_PATH` environment variable to the directory containing the `ffmpeg` binary.


### Setup
Before running the application for the first time, initialize the database:

1. Run the setup script:
   ```bash
   php setup_db.php
   ```
   This will create the database and a default admin user:
   - **Username:** `luizcf14`
   - **Password:** `qazx74123`

### Running the Application

1. Open a terminal in the project root.
2. Run the following command:
   ```bash
   php  -c php.ini -S localhost:8000 -t public
   ```
3. Open your browser and navigate to `http://localhost:8000`.

### Windows Helper
You can also simply double-click the `start.bat` file to run the server.

### Python Runner
uvicorn parente:app --host 0.0.0.0 --port 3000 --reload
python -u src/python/main.py

### NGROK Runner
ngrok http --url=joey-rational-escargot.ngrok-free.app 3000

---

## Funcionalidades Avançadas

### 📋 Conformidade LGPD
O sistema inclui um fluxo obrigatório de consentimento para a LGPD (Lei Geral de Proteção de Dados):
- **Interceptor de Mensagens**: Novos usuários recebem a política de privacidade antes de interagir com a IA.
- **Gestão de Aceite**: O status de consentimento é gravado no banco de dados (`accepted`, `rejected`, `pending`).
- **Reconsideração**: Usuários que recusaram podem aceitar a política enviando uma nova mensagem.

### ⏲️ Debounce de Mensagens (Message Splitting)
Para lidar com usuários que enviam várias mensagens curtas seguidas:
- **Agrupamento**: Mensagens enviadas em um intervalo curto são combinadas em um único prompt para a IA.
- **Janela Configurável**: O tempo de espera padrão é de 4 segundos, ajustável via `MESSAGE_DEBOUNCE_SECONDS` no `.env`.

### 🧠 Detecção de "Fim de Pensamento"
O sistema acelera o processamento (reduzindo o tempo de debounce para 0.8s) quando detecta sinais de finalização:
- **Sinais**: Pontos de interrogação (`?`), exclamação (`!`), ponto final (`.`) ou reticências (`…`).
- **Mídia**: O envio de imagens, áudios ou documentos dispara o processamento quase imediato.

### 🔤 Conversor Markdown para WhatsApp
As respostas da IA em Markdown são convertidas automaticamente para o formato rico do WhatsApp:
- **Negrito**: `**texto**` → `*texto*`
- **Itálico**: `*texto*` → `_texto_`
- **Listas**: Marcadores convertidos para pontos (`•`).
- **Links**: `[texto](url)` → `texto (url)`.
- **Blocos de Código**: Preservados usando o padrão do WhatsApp (```).

### 🗄️ Auto-Migrations de Banco de Dados
O sistema verifica e atualiza o schema do banco automaticamente a cada inicialização, sem necessidade de rodar scripts manuais.

- **Startup automático**: `run_migrations()` é chamado em `main.py` antes de qualquer outra inicialização.
- **Idempotente**: só aplica o que está faltando — nunca recria dados existentes.
- **Multi-ambiente**: funciona tanto em `database.sqlite` (dev) quanto em `database_prod.sqlite` (produção), detectado pelo `APP_ENV`.

**Para adicionar uma nova migration**, edite `src/python/core/migrations.py`:
```python
# Nova coluna em tabela existente:
COLUMN_MIGRATIONS = [
    ...
    ("conversations", "nova_coluna", "ALTER TABLE conversations ADD COLUMN nova_coluna TEXT"),
]

# Nova tabela:
TABLE_MIGRATIONS = [
    ...
    ("nova_tabela", "CREATE TABLE IF NOT EXISTS nova_tabela (id INTEGER PRIMARY KEY, ...)"),
]
```
Na próxima inicialização, o runner aplica automaticamente.

**Variáveis de ambiente relevantes (`.env`):**
| Variável | Padrão | Descrição |
|---|---|---|
| `APP_ENV` | `development` | `production` usa `database_prod.sqlite` |
| `DB_FILE` | auto | Override manual do arquivo de banco |
| `MESSAGE_DEBOUNCE_SECONDS` | `4` | Janela de agrupamento de mensagens (segundos) |
| `FAST_MODEL_PROVIDER` | `gemini` | Provedor do modelo rápido (`ollama` ou `gemini`) |
| `FAST_MODEL_NAME` | `gemini-2.5-flash` | ID do modelo rápido |
| `SLOW_MODEL_PROVIDER` | `gemini` | Provedor do modelo lento |
| `SLOW_MODEL_NAME` | `gemini-2.5-flash` | ID do modelo lento |

