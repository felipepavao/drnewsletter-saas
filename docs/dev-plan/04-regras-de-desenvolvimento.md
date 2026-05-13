# Regras de desenvolvimento — Dr. Newsletter SaaS

## Stack obrigatória

- PHP 8.1+ vanilla (zero framework).
- PDO + SQLite. Sem ORM.
- JS vanilla. Zero build do frontend.
- CSS puro. Zero Tailwind, zero PostCSS, zero Sass.
- Pest para testes.

Se aparecer pressão para "puxar uma lib pra agilizar", a pergunta é:
isso vai estar funcional sem dor de upgrade em 3 anos? Se não, não entra.

## Banco

- Migrations versionadas em `migrations/NNN_*.sql`, idempotentes.
- Nunca alterar uma migration já aplicada em produção. Sempre nova.
- Toda FK tem índice.
- `journal_mode = WAL`, `foreign_keys = ON`, `busy_timeout = 5000`
  ligados centralmente em `Database::getInstance`.
- `INSERT`/`UPDATE`/`DELETE` sempre via `Database::execute` com prepared
  statements. Concatenar SQL com input do user é crime.

## Segurança

### Chave Anthropic (regra dura)

Ver `README.md` raiz. Resumo:

1. Só existe em `.env`.
2. Pre-commit hook bloqueia padrões.
3. Toda chamada IA passa por `app/services/Claude.php`.
4. CSP `connect-src 'self'`.
5. Spending cap diário no `.env` + no console Anthropic.
6. Chave dedicada por projeto. Rotação documentada.

### Toda controller protegida

```php
public function index() {
    Auth::requireUser();
    // ...
}
```

### Antes de operar registro

```php
// Errado:
$plan = Database::fetch('SELECT * FROM monthly_plans WHERE id = ?', [$id]);

// Certo:
$plan = Database::fetch(
    'SELECT * FROM monthly_plans WHERE id = ? AND user_id = ?',
    [$id, Auth::userId()]
);
if (!$plan) { Response::abort(404); }
```

### CSRF em todo POST

Token via `Csrf::token()` nas views, verificação automática no Router
(ou explicit `Csrf::check()` quando lib não faz auto).

### Uploads

- só MIME esperado
- limite de tamanho aplicado no PHP (não confiar só no nginx)
- nome saneado
- armazenado fora do webroot em produção

## Convenções de código

- snake_case em SQL (tabelas e colunas).
- camelCase em PHP (métodos, variáveis).
- PascalCase em classes (uma por arquivo, mesmo nome).
- Controllers terminam em `Controller`. Services em namespace `app/services`.
- Views em `app/views/<area>/<nome>.php`.
- 1 controller por área de feature, métodos pequenos.

## Prompts de IA

- Cada prompt vive em `app/services/prompts/<nome>.php` retornando string
  ou em método estático da service correspondente.
- Sem string literal de prompt espalhado pelos controllers.
- Mudanças de prompt são commit separado e auditável.

## Testes

- Pest com SQLite em memória.
- Mock de `Claude::complete` em todo teste (nunca chamar API em CI).
- Fluxos críticos cobertos: auth, criação de cliente, geração de plano,
  geração de email.

## Logs

- Erros PHP → `data/logs/app.log` (autoload).
- Chamadas Claude → tabela `claude_calls` (não em arquivo; queryável).
- Acessos → log do nginx.
- Nunca logar conteúdo de prompt completo (PII de cliente).

## Status operacional

- `php bin/migrate.php` é idempotente — pode rodar em todo deploy.
- `php bin/backup.php` é idempotente — pode rodar diário no cron.
- Deploy é `git pull && php bin/migrate.php && systemctl reload php-fpm`.
- Rollback é `git reset --hard <sha> && systemctl reload php-fpm`.

## Padrão de release

Toda release nova:

1. Branch `release/N-nome-curto`.
2. Migrations versionadas em `migrations/NNN_*.sql`.
3. Documento em `docs/dev-plan/NN-release-N-*.md` com critério de aceite.
4. PR para `main` com checklist preenchido.
5. CI verde (quando CI existir; por ora, `composer test` local).
6. Tag `v0.N.0` após merge.

## Anti-padrões proibidos

- ❌ Magic globals em controllers (`$_POST`/`$_GET` direto — usar `Request::input()`)
- ❌ Echo HTML em controller (sempre `View::render`)
- ❌ Query montada com `"... WHERE id = $id"`
- ❌ `eval()`, `unserialize()` em input de user, `extract()` em request
- ❌ `error_reporting(0)` ou `@` em código de produção (silenciamento mascara bug)
- ❌ `composer require` de lib que faz "auto-magia" (annotations, attributes parsing complexo)
- ❌ JS framework no frontend (React/Vue/Svelte). Vanilla only.
- ❌ Build step no frontend. Servir os arquivos como estão.
