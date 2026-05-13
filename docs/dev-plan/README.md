# Plano de desenvolvimento — Dr. Newsletter SaaS

Esta pasta concentra a documentação de evolução do software, **agora na
stack PHP vanilla + SQLite + JS/CSS vanilla**, alinhada às outras
aplicações de Felipe Pavão (Enteogenistas, Contracorrente).

A versão anterior do produto, em Node + React + Sequelize, vive como
referência no repositório `active-drnewsletter-saas` e está sendo
sucedida por este aqui.

---

## Por que migrar a stack

1. **Coerência de portfólio.** Felipe está padronizando todos os apps em
   PHP vanilla + SQLite. Manter um único stack reduz custo cognitivo de
   manutenção e libera operação de longa vida sem armadilhas de upgrade
   de framework JS.
2. **Operação simples em VPS.** PHP-FPM + nginx é boring tech: roda em
   qualquer Hetzner por anos sem mexer.
3. **Zero build do frontend.** Eliminar Vite/Tailwind/PostCSS remove
   inteiras classes de problemas (env vazando em bundle, dependências
   abandonadas, surface de ataque do `node_modules`).
4. **Banco como artefato único.** SQLite local + backup `VACUUM INTO`
   resolve persistência sem orquestração.

## Documentos

1. `00-visao-produto.md` — visão, público, planos comerciais, princípios.
2. `01-arquitetura-alvo.md` — arquitetura PHP, VPS, SQLite, IA, segurança.
3. `02-release-0-php-mvp.md` — primeira release funcional (reescrita).
4. `02b-release-0-deploy-hetzner.md` — deploy em produção.
5. `03-roadmap-releases.md` — sequência de releases 1–7.
6. `04-regras-de-desenvolvimento.md` — convenções e checklist de segurança.

## Decisões atuais

- **Stack:** PHP 8.1+ vanilla, SQLite, JS vanilla, CSS puro, Pest.
- **Hospedagem:** VPS própria (provavelmente Hetzner).
- **IA:** Claude via API direta (chave server-side, nunca exposta).
- **Auth:** magic link por email (Brevo SMTP).
- **Pricing:** três planos — R$1.997, R$3.997, R$6.997.
- **Meta:** 10 clientes em 2026, lifestyle game, não scale game.

## Regra de segurança

Esta documentação **nunca** deve conter secrets, API keys, dumps de
banco de produção ou dados privados de clientes. Pre-commit hook
(`bin/check-secrets.sh`) bloqueia padrões conhecidos.

Antes de qualquer commit:

```bash
git status --short
git diff --cached
```
