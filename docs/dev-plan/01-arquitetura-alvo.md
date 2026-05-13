# Arquitetura alvo — Dr. Newsletter SaaS

## Visão de alto nível

```
                      ┌─────────────────────────────────┐
   browser ──HTTPS──▶ │  nginx  ──fastcgi──▶  php-fpm   │
                      │                         │       │
                      │                         ▼       │
                      │                    app PHP      │
                      │                    (vanilla)    │
                      │                         │       │
                      │             ┌───────────┼──────┐│
                      │             ▼           ▼      ▼│
                      │       SQLite (WAL)  uploads/  logs/
                      └─────────────────────────────────┘
                                    │
                                    │ HTTPS (cURL server-side)
                                    ▼
                              api.anthropic.com
```

**Princípios:**

- single tenant por VPS, mas multi-cliente por aplicação (1 user → N clients).
- frontend é HTML+CSS+JS vanilla servido pelo próprio PHP.
- nenhuma chamada de IA sai do browser; só do servidor.

## Componentes

### nginx

- terminação TLS (Let's Encrypt via Certbot ou Caddy se preferir simplicidade)
- serve `public/` como webroot
- `try_files $uri $uri/ /index.php?$query_string`
- estáticos: cache-control longo em `/assets/*` com fingerprint na URL
- limites: client_max_body_size 12M (uploads de TXT)

### php-fpm

- pool dedicado pro projeto (usuário próprio, ex: `drnl`)
- `pm = dynamic`, start 2, max 6 (escala pequena, 10 clientes)
- `request_terminate_timeout = 150s` (chamadas Claude longas)
- `php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen`
- `expose_php = Off`

### SQLite

- arquivo em `/data/drnewsletter/database.sqlite`
- `journal_mode = WAL`
- `foreign_keys = ON`
- `busy_timeout = 5000`
- backup via `VACUUM INTO` (consistente, não bloqueia escrita)
- retenção 14 dias local + 30 dias offsite (S3 compatible / rsync)

### Camada de IA

- service único: `app/services/Claude.php`
- cURL puro, sem SDK
- chave em `.env`, nunca lida fora desse arquivo
- toda chamada loga em `claude_calls`: usuário, cliente, propósito, tokens, custo
- rate limit por usuário/dia (`RATE_LIMIT_AI_USER_DAY`)
- spending cap diário (`CLAUDE_DAILY_USD_CAP`) — segunda linha de defesa
- timeout 120s (planejamento mensal pode ser longo)

### Email

- magic link via Brevo SMTP (mesmo padrão de Enteogenistas)
- código de 6 dígitos, TTL 15min, hash em banco
- rate limit por email (3 / 15min) e por IP (10 / 15min)
- sessão de 30 dias após autenticação

## Estrutura de diretórios em produção

```
/opt/drnewsletter/app/             # código deployado (git checkout)
  ├── bootstrap.php
  ├── config.php
  ├── app/
  ├── public/                       # webroot
  ├── migrations/
  └── bin/
/data/drnewsletter/
  ├── database.sqlite               # WAL
  ├── database.sqlite-wal
  ├── database.sqlite-shm
  ├── uploads/                      # TXT enviados pelos usuários
  ├── logs/
  │   ├── app.log
  │   ├── claude.log
  │   └── nginx-access.log
  └── backups/
      └── database_YYYY-MM-DD_HHMMSS.sqlite.gz
/opt/drnewsletter/.env              # chmod 600, root:www-data
```

**Regra de ouro:** código em `/opt`, dados em `/data`. Deploy nunca toca
em `/data`. Backup nunca toca em `/opt/.env` (mesmo se estiver fora de
`/data`, é regra explícita do script).

## Segurança

### Headers (em `bootstrap.php`)

- `Strict-Transport-Security` (prod + HTTPS)
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` zerada
- `Content-Security-Policy`: tudo `'self'`, **`connect-src 'self'`** —
  impede o browser de falar com `api.anthropic.com` diretamente.

### Autorização

- toda controller que toca cliente/plano/email chama
  `Auth::requireUser()` no início.
- antes de operar sobre um registro, verificar `user_id` no WHERE.
- nunca confiar em `client_id` vindo da URL sem checar dono.

### Chave Anthropic

Ver `README.md` raiz, seção "Segurança da chave Anthropic". As 8 regras
estão lá.

### Uploads

- só `text/plain` aceito
- limite de tamanho `UPLOAD_MAX_BYTES` (10MB)
- nome saneado (`Image::safeFilename` ou equivalente)
- armazenado fora do webroot em produção (`/data/.../uploads/`),
  servido por endpoint PHP autenticado se precisar baixar

## Observabilidade mínima

- `app.log` — erros PHP
- `claude_calls` (tabela) — auditoria de IA com custo
- `nginx-access.log` — requests
- cron diário soma `claude_calls.cost_usd` da última 24h e envia
  resumo por email para o admin se passar de threshold.

## O que NÃO está nesta arquitetura (não objetivos da Release 0)

- multi-instância / load balancing
- Postgres / banco gerenciado
- fila de jobs (Redis, RabbitMQ)
- streaming SSE
- websocket
- CDN
- analytics avançado
- mobile app
