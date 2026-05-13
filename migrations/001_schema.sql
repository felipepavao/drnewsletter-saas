-- ============================================================
--  Dr. Newsletter SaaS — Schema inicial (Release 0)
-- ============================================================
--  Convenções:
--   - snake_case em tabelas e colunas
--   - timestamps em UTC, formato SQLite datetime
--   - ON DELETE CASCADE para FKs de "dono lógico" (user → client → ...)
--   - índices em toda FK
-- ============================================================

-- ----- Users (operadores da plataforma) -----
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email           TEXT NOT NULL UNIQUE COLLATE NOCASE,
    name            TEXT,
    is_admin        INTEGER NOT NULL DEFAULT 0,
    status          TEXT NOT NULL DEFAULT 'active',
                    -- 'active' | 'suspended' | 'deleted'
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login_at   DATETIME
);
CREATE INDEX IF NOT EXISTS idx_users_email  ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);

-- ----- Códigos de acesso (magic link) -----
CREATE TABLE IF NOT EXISTS auth_codes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash   TEXT NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME,
    tries       INTEGER NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address  TEXT
);
CREATE INDEX IF NOT EXISTS idx_auth_codes_user ON auth_codes(user_id, expires_at);

-- ----- Sessões (cookie opaco, 30 dias) -----
CREATE TABLE IF NOT EXISTS sessions (
    id           TEXT PRIMARY KEY,
    user_id      INTEGER REFERENCES users(id) ON DELETE CASCADE,
    data         TEXT NOT NULL DEFAULT '{}',
    expires_at   DATETIME NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME,
    user_agent   TEXT,
    ip_address   TEXT
);
CREATE INDEX IF NOT EXISTS idx_sessions_user    ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at);

-- ----- Rate limiting (genérico) -----
CREATE TABLE IF NOT EXISTS rate_limits (
    key_hash     TEXT NOT NULL,
    hits         INTEGER NOT NULL DEFAULT 1,
    window_start INTEGER NOT NULL,
    PRIMARY KEY (key_hash, window_start)
);

-- ============================================================
--  Domínio do produto
-- ============================================================

-- ----- Clientes gerenciados por cada usuário -----
CREATE TABLE IF NOT EXISTS clients (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    email       TEXT,
    segment     TEXT,
                -- 'joalheria' | 'restaurante' | 'clinica' | 'otica' | 'outro'
    notes       TEXT,
    status      TEXT NOT NULL DEFAULT 'active',
                -- 'active' | 'archived'
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_clients_user ON clients(user_id);
CREATE INDEX IF NOT EXISTS idx_clients_status ON clients(status);

-- ----- Voz da Marca (com versionamento) -----
CREATE TABLE IF NOT EXISTS brand_manuals (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id       INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    version         INTEGER NOT NULL DEFAULT 1,
    is_active       INTEGER NOT NULL DEFAULT 1,
    source_filename TEXT,
    raw_content     TEXT NOT NULL,
                    -- TXT bruto enviado pelo usuário
    parsed_json     TEXT,
                    -- estrutura extraída pelo Claude (tom, público, valores...)
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_brand_manuals_client ON brand_manuals(client_id, is_active);

-- ----- Arquivos de Emails (até 5 por cliente, exemplos de referência) -----
CREATE TABLE IF NOT EXISTS email_archives (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id   INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    filename    TEXT NOT NULL,
    content     TEXT NOT NULL,
    byte_size   INTEGER NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_email_archives_client ON email_archives(client_id);

-- ----- Planejamentos mensais (gerados por IA) -----
CREATE TABLE IF NOT EXISTS monthly_plans (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id       INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    year            INTEGER NOT NULL,
    month           INTEGER NOT NULL,
    email_count     INTEGER NOT NULL,
    context_notes   TEXT,
    themes_json     TEXT NOT NULL,
                    -- array de temas: título, tipo, objetivo, gancho, CTA
    status          TEXT NOT NULL DEFAULT 'draft',
                    -- 'draft' | 'approved' | 'archived'
    tokens_in       INTEGER,
    tokens_out      INTEGER,
    cost_usd        REAL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_monthly_plans_client ON monthly_plans(client_id, year, month);
CREATE INDEX IF NOT EXISTS idx_monthly_plans_user   ON monthly_plans(user_id);

-- ----- Drafts de emails (geração / escrita) -----
CREATE TABLE IF NOT EXISTS email_drafts (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id        INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    monthly_plan_id  INTEGER REFERENCES monthly_plans(id) ON DELETE SET NULL,
    theme_index      INTEGER,
                     -- índice do tema dentro de themes_json (quando aplicável)
    subject          TEXT,
    preview_text     TEXT,
    body_text        TEXT,
    body_html        TEXT,
    version          INTEGER NOT NULL DEFAULT 1,
    status           TEXT NOT NULL DEFAULT 'draft',
                     -- 'draft' | 'approved' | 'sent' | 'archived'
    tokens_in        INTEGER,
    tokens_out       INTEGER,
    cost_usd         REAL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_email_drafts_client ON email_drafts(client_id);
CREATE INDEX IF NOT EXISTS idx_email_drafts_plan   ON email_drafts(monthly_plan_id);

-- ----- Chat de escrita iterativa (writer) -----
CREATE TABLE IF NOT EXISTS email_writer_chats (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email_draft_id  INTEGER NOT NULL REFERENCES email_drafts(id) ON DELETE CASCADE,
    title           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_writer_chats_draft ON email_writer_chats(email_draft_id);

CREATE TABLE IF NOT EXISTS email_writer_messages (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_id     INTEGER NOT NULL REFERENCES email_writer_chats(id) ON DELETE CASCADE,
    role        TEXT NOT NULL,
                -- 'user' | 'assistant' | 'system'
    content     TEXT NOT NULL,
    tokens_in   INTEGER,
    tokens_out  INTEGER,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_writer_messages_chat ON email_writer_messages(chat_id, created_at);

-- ----- Histórico de feedback (para tracking de iteração) -----
CREATE TABLE IF NOT EXISTS feedback_history (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email_draft_id  INTEGER NOT NULL REFERENCES email_drafts(id) ON DELETE CASCADE,
    feedback_text   TEXT NOT NULL,
    version_before  INTEGER,
    version_after   INTEGER,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_feedback_history_draft ON feedback_history(email_draft_id);

-- ----- Log de chamadas Claude (auditoria + custo) -----
CREATE TABLE IF NOT EXISTS claude_calls (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    client_id   INTEGER REFERENCES clients(id) ON DELETE SET NULL,
    purpose     TEXT NOT NULL,
                -- 'parse_brand_manual' | 'monthly_plan' | 'theme' | 'email' | 'writer'
    model       TEXT NOT NULL,
    tokens_in   INTEGER NOT NULL DEFAULT 0,
    tokens_out  INTEGER NOT NULL DEFAULT 0,
    cost_usd    REAL NOT NULL DEFAULT 0,
    success     INTEGER NOT NULL DEFAULT 1,
    error_code  TEXT,
    duration_ms INTEGER,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_claude_calls_user ON claude_calls(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_claude_calls_day  ON claude_calls(created_at);
