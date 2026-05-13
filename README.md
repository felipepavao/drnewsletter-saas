# Dr. Newsletter SaaS

Plataforma de planejamento e escrita de newsletters para negócios presenciais
premium — joalherias, restaurantes, clínicas, óticas. Service-led, não
tool-led: a equipe da Dr. Newsletter opera junto com o cliente.

## Stack

- **PHP 8.1+ vanilla** (sem framework; router próprio, autoloader manual)
- **SQLite** com WAL e foreign_keys ON
- **JS vanilla** (zero build)
- **CSS puro**
- **Pest** para testes
- **nginx + php-fpm** em produção (VPS Hetzner)
- **Anthropic Claude** como motor de IA (server-side, chave nunca exposta)

## Setup local

```bash
# 1. dependências dev (opcional, só pra testes)
composer install

# 2. configurar ambiente
cp .env.example .env
# editar .env e preencher ANTHROPIC_API_KEY, SMTP_*

# 3. instalar pre-commit hook de segredos
ln -sf ../../bin/check-secrets.sh .git/hooks/pre-commit

# 4. migrations
php bin/migrate.php

# 5. servir
php -S localhost:8080 -t public public/router.php
```

Abrir http://localhost:8080.

## Estrutura

```
app/
  core/         # Database, Router, Request, Response, Session, Csrf, ...
  controllers/  # Um por área de feature
  models/       # Repositórios sobre PDO (puros, sem ORM)
  services/     # Claude.php, prompts, lógica de IA
  views/        # Templates PHP
  lib/          # helpers.php
public/         # Webroot — index.php, router.php, assets/, uploads/
data/           # database.sqlite, logs/, backups/ (gitignored)
migrations/     # SQL versionado
bin/            # migrate.php, backup.php, check-secrets.sh
ops/            # nginx config, cron, scripts de deploy
docs/dev-plan/  # Releases planejadas
tests/          # Pest
```

## Segurança da chave Anthropic

A `ANTHROPIC_API_KEY` é o ativo mais sensível do projeto. As regras:

1. **Só existe no `.env` do servidor**, nunca no repo, nunca no frontend.
2. **`.gitignore` cobre `.env`** e o `bin/check-secrets.sh` bloqueia
   qualquer commit que contenha padrão `sk-ant-...`.
3. **Toda chamada Claude é server-side** via `app/services/Claude.php`.
   O frontend só fala com nossos endpoints PHP.
4. **CSP `connect-src 'self'`** impede o browser de chamar `api.anthropic.com`
   mesmo se um JS comprometido tentasse.
5. **Spending cap diário** em `CLAUDE_DAILY_USD_CAP`: se passar do teto,
   recusa novas chamadas até virar o dia.
6. **Rate limit por usuário** em `RATE_LIMIT_AI_USER_DAY`.
7. **Spending cap no console Anthropic** ativado em produção (segunda
   linha de defesa, independente da app).
8. **Chave dedicada por projeto** — não compartilhar com outros apps.

Em produção: `chmod 600 .env`, owner `root:www-data`.

## Documentação técnica

Ver `docs/dev-plan/` para roadmap, arquitetura, e plano da Release 0.

## Licença

Proprietário. Felipe Pavão / Dr. Newsletter.
