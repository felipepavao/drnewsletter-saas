# Release 0 — MVP PHP funcional (local)

> Objetivo: ter o produto rodando em `php -S localhost:8080` com paridade
> funcional do MVP anterior (Node/React), preparado para deploy.

Esta release **não inclui deploy**. Deploy é a Release 0-Deploy
(`02b-release-0-deploy-hetzner.md`), separada para reduzir surface por
PR.

---

## 1. Critério de aceite

Tudo abaixo precisa funcionar localmente, ponta a ponta:

1. Usuário se cadastra com email.
2. Recebe código por email (Brevo SMTP) e entra.
3. Cria um cliente (joalheria/restaurante/clínica/ótica/outro).
4. Faz upload do TXT de Voz da Marca → Claude parseia → estrutura
   aparece na tela e persiste versionada.
5. Faz upload de até 5 TXT de exemplos de email.
6. Gera planejamento mensal (Claude usa Voz + Arquivo como contexto).
7. Cada tema do planejamento tem: título, tipo, objetivo, gancho, CTA.
8. Gera tema avulso adicional.
9. Aprova planejamento e exporta TXT.
10. A partir de um tema, abre o Escritor de Emails e gera draft.
11. Conversa com a IA pra iterar o draft. Cada versão é persistida.
12. Aprova e exporta o email final.
13. Reinicia o servidor — todos os dados continuam.
14. `php bin/backup.php` gera `.sqlite.gz` consistente.

---

## 2. Não-objetivos

- Deploy em VPS (vai pra release seguinte).
- Billing / Stripe / Asaas.
- Plataforma de email externo (Mailchimp etc).
- Streaming de tokens da Claude.
- Editor visual.
- Analytics de envio.

---

## 3. Ordem de execução

### Bloco A — Fundação (já feito no commit inicial)

- [x] Estrutura de diretórios
- [x] `bootstrap.php`, `config.php`, autoloader
- [x] Core: Database, Router, Request, Response, Session, Csrf, Flash,
      Log, RateLimiter, View, Mailer, Image
- [x] `migrations/001_schema.sql`
- [x] `app/services/Claude.php` (com daily cap + audit log)
- [x] `bin/migrate.php`, `bin/backup.php`, `bin/check-secrets.sh`
- [x] Pre-commit hook instalado
- [x] CSP `connect-src 'self'` no bootstrap

### Bloco B — Autenticação (magic link)

- [ ] `AuthController::requestCode` — recebe email, cria/atualiza user,
      gera código de 6 dígitos, salva hash em `auth_codes`, envia email.
- [ ] `AuthController::showVerify` — tela com input de código.
- [ ] `AuthController::verifyCode` — valida hash + TTL + tries, abre
      sessão, redirect pra `/painel`.
- [ ] `AuthController::logout`
- [ ] `Auth::requireUser()` helper para controllers protegidos.
- [ ] Rate limit por email (`AUTH_CODE_PER_EMAIL`) e por IP
      (`AUTH_CODE_PER_IP`).
- [ ] Layout base + view de login + view de verify.

### Bloco C — Dashboard + Clientes

- [ ] `DashboardController::index` — lista resumida do que existe.
- [ ] `ClientsController` CRUD completo, com isolation por user_id.
- [ ] Views: lista, novo, editar, detalhe.
- [ ] CSRF em todos POSTs.

### Bloco D — Voz da Marca

- [ ] Upload TXT (validação MIME, tamanho).
- [ ] `BrandManualParser` (service) — chama Claude com prompt para
      extrair tom, público, valores, produtos, objetivos.
- [ ] Persiste `raw_content` + `parsed_json`, versionando.
- [ ] View mostra estrutura parseada com possibilidade de fazer upload
      novo (nova versão; antigas ficam arquivadas).

### Bloco E — Arquivo de Emails

- [ ] Upload TXT múltiplo, até 5 arquivos por cliente.
- [ ] Listagem, remoção.
- [ ] Conteúdo é só armazenado; não é parseado por IA — usado como
      contexto bruto nos prompts.

### Bloco F — Planejador Mensal

- [ ] Form: mês, ano, quantidade de emails, contexto adicional.
- [ ] `PlannerService::generate` — monta prompt com Voz + Arquivo +
      framework de 5 categorias, chama Claude, parseia JSON estruturado.
- [ ] Persiste `monthly_plans` com `themes_json`.
- [ ] View de detalhe com cada tema (título, tipo, objetivo, gancho, CTA).
- [ ] Aprovação e export TXT.

### Bloco G — Temas avulsos

- [ ] Endpoint de geração de N temas dado contexto.
- [ ] Adiciona temas ao plano existente (mescla em `themes_json`).

### Bloco H — Escritor de Emails (chat)

- [ ] A partir de um tema do plano, criar `email_draft` inicial.
- [ ] `email_writer_chats` + `email_writer_messages`.
- [ ] Conversa multi-turno; cada turno do assistant pode gerar nova
      versão do draft (incrementa `version`, registra em `feedback_history`).
- [ ] Aprovação e export TXT.

### Bloco I — Polimento

- [ ] Página de ajuda.
- [ ] Toasts / feedback de erro padronizado (Flash).
- [ ] Estados de loading no front (JS vanilla, sem framework).
- [ ] Validação de "configuração incompleta" do cliente (sem Voz não
      gera plano; sem cliente não abre planner).

### Bloco J — Testes

- [ ] Pest setup com banco SQLite em memória.
- [ ] Helpers: criar user, login fake, criar client.
- [ ] Smoke tests dos fluxos principais.
- [ ] Mock do `Claude::complete` em testes.

---

## 4. Riscos da release

| Risco | Mitigação |
|------|-----------|
| Chave Anthropic vazada | `.env` no `.gitignore`, pre-commit hook, CSP `connect-src 'self'`, spending cap |
| Custo de IA explodir em desenvolvimento | `CLAUDE_DAILY_USD_CAP=10` no `.env.example`, ajustar em prod |
| Upload de TXT malicioso | Validar MIME, limite de tamanho, salvar fora do webroot |
| Planejamento mensal demora > 60s | `CLAUDE_TIMEOUT=120`, UX com spinner; se virar problema, fila depois |
| Sessão "vaza" entre users | Cookie HttpOnly + SameSite=Lax + sessão em banco com user_id |
| SQLite corrompido | WAL + backup `VACUUM INTO` diário + retenção |

---

## 5. Definição de pronto da Release 0

A Release 0 está pronta quando **um humano não-Felipe** consegue,
seguindo apenas o `README.md`:

1. Clonar o repo.
2. Configurar `.env`.
3. Rodar migrations.
4. Subir `php -S`.
5. Cadastrar conta, criar cliente, gerar planejamento, gerar email.
6. Sem ler nenhum outro arquivo do código.
