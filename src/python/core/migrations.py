"""
Auto-migration runner para o banco SQLite do projeto.

Ao ser chamado no startup do app, verifica quais colunas/tabelas estão
faltando e aplica apenas o necessário — sem recriar dados existentes.

Para adicionar uma nova migration, basta inserir um item na lista
COLUMN_MIGRATIONS ou TABLE_MIGRATIONS abaixo.
"""

import sqlite3
from typing import Optional
from .config import DB_PATH


# ── Migrations de COLUNAS ────────────────────────────────────────────────────
# Cada item: (tabela, coluna, SQL do ALTER TABLE)
# O runner verifica se a coluna já existe antes de aplicar.
COLUMN_MIGRATIONS: list[tuple[str, str, str]] = [
    # 001 — ai_status (controle de pausa da IA por usuário)
    (
        "conversations",
        "ai_status",
        "ALTER TABLE conversations ADD COLUMN ai_status TEXT DEFAULT 'active'",
    ),
    # 002 — campos de análise de conversa
    (
        "conversations",
        "sentiment",
        "ALTER TABLE conversations ADD COLUMN sentiment TEXT",
    ),
    (
        "conversations",
        "topic",
        "ALTER TABLE conversations ADD COLUMN topic TEXT",
    ),
    (
        "conversations",
        "last_analyzed_at",
        "ALTER TABLE conversations ADD COLUMN last_analyzed_at DATETIME",
    ),
    # 003 — campos de mídia nas mensagens
    (
        "messages",
        "media_type",
        "ALTER TABLE messages ADD COLUMN media_type TEXT",
    ),
    (
        "messages",
        "media_url",
        "ALTER TABLE messages ADD COLUMN media_url TEXT",
    ),
    # 004 — flag de leitura nas mensagens
    (
        "messages",
        "is_read",
        "ALTER TABLE messages ADD COLUMN is_read INTEGER DEFAULT 0",
    ),
    # 005 — consentimento LGPD
    (
        "conversations",
        "lgpd_consent_status",
        "ALTER TABLE conversations ADD COLUMN lgpd_consent_status TEXT DEFAULT 'pending'",
    ),
    (
        "conversations",
        "lgpd_consent_at",
        "ALTER TABLE conversations ADD COLUMN lgpd_consent_at DATETIME",
    ),
    (
        "conversations",
        "lgpd_awaiting_response",
        "ALTER TABLE conversations ADD COLUMN lgpd_awaiting_response INTEGER DEFAULT 0",
    ),
]


# ── Migrations de TABELAS ────────────────────────────────────────────────────
# Cada item: (nome_da_tabela, SQL CREATE TABLE IF NOT EXISTS)
TABLE_MIGRATIONS: list[tuple[str, str]] = [
    (
        "conversations",
        """
        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT UNIQUE NOT NULL,
            ai_status TEXT DEFAULT 'active',
            sentiment TEXT,
            topic TEXT,
            last_message_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_analyzed_at DATETIME,
            lgpd_consent_status TEXT DEFAULT 'pending',
            lgpd_consent_at DATETIME,
            lgpd_awaiting_response INTEGER DEFAULT 0
        )
        """,
    ),
    (
        "messages",
        """
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            sender TEXT NOT NULL,
            content TEXT,
            media_type TEXT,
            media_url TEXT,
            is_read INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        )
        """,
    ),
    (
        "system_settings",
        """
        CREATE TABLE IF NOT EXISTS system_settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        """,
    ),
    (
        "communication_evaluations",
        """
        CREATE TABLE IF NOT EXISTS communication_evaluations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_identifier TEXT NOT NULL,
            trigger_message TEXT NOT NULL,
            last_messages TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        """,
    ),
]


def _get_existing_columns(conn: sqlite3.Connection, table: str) -> set[str]:
    """Retorna o conjunto de colunas existentes em uma tabela."""
    try:
        cur = conn.cursor()
        cur.execute(f"PRAGMA table_info({table})")
        return {row[1] for row in cur.fetchall()}
    except sqlite3.Error:
        return set()


def _get_existing_tables(conn: sqlite3.Connection) -> set[str]:
    """Retorna o conjunto de tabelas existentes no banco."""
    try:
        cur = conn.cursor()
        cur.execute("SELECT name FROM sqlite_master WHERE type='table'")
        return {row[0] for row in cur.fetchall()}
    except sqlite3.Error:
        return set()


def run_migrations(db_path=None) -> None:
    """
    Executa todas as migrations pendentes no banco especificado.
    Se db_path não for fornecido, usa o DB_PATH do config.

    Deve ser chamado uma vez no startup do app.
    """
    target = db_path or DB_PATH

    if not target.exists():
        print(f"[migrations] Banco não encontrado em {target} — pulando migrations.")
        return

    try:
        conn = sqlite3.connect(str(target))
        conn.row_factory = sqlite3.Row
    except sqlite3.Error as e:
        print(f"[migrations] Erro ao conectar em {target}: {e}")
        return

    applied = 0
    errors = 0

    try:
        existing_tables = _get_existing_tables(conn)

        # 1. Garante que as tabelas principais existem
        for table_name, create_sql in TABLE_MIGRATIONS:
            if table_name not in existing_tables:
                try:
                    conn.execute(create_sql)
                    conn.commit()
                    print(f"[migrations] Tabela criada: {table_name}")
                    applied += 1
                except sqlite3.Error as e:
                    print(f"[migrations] Erro ao criar tabela {table_name}: {e}")
                    errors += 1

        # Recarrega tabelas existentes após possíveis criações
        existing_tables = _get_existing_tables(conn)

        # 2. Aplica colunas faltantes
        # Cache de colunas por tabela para evitar múltiplas queries
        cols_cache: dict[str, set[str]] = {}

        for table, column, sql in COLUMN_MIGRATIONS:
            if table not in existing_tables:
                continue  # Tabela não existe, pula

            if table not in cols_cache:
                cols_cache[table] = _get_existing_columns(conn, table)

            if column not in cols_cache[table]:
                try:
                    conn.execute(sql)
                    conn.commit()
                    cols_cache[table].add(column)  # Atualiza cache
                    print(f"[migrations] Coluna adicionada: {table}.{column}")
                    applied += 1
                except sqlite3.Error as e:
                    print(f"[migrations] Erro ao adicionar {table}.{column}: {e}")
                    errors += 1

    finally:
        conn.close()

    if applied == 0 and errors == 0:
        print(f"[migrations] Banco atualizado — nenhuma migration pendente.")
    else:
        print(f"[migrations] Concluído: {applied} aplicadas, {errors} erros.")
