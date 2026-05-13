# Roadmap de releases — Dr. Newsletter SaaS

> Sequência de evolução a partir da Release 0.
> Meta de ano: 10 clientes em 2026, lifestyle game.

---

## Release 0 — MVP PHP local (`02-release-0-php-mvp.md`)

Reescrita completa do MVP em PHP vanilla, ponta a ponta funcional.

## Release 0-Deploy — Hetzner (`02b-release-0-deploy-hetzner.md`)

Produção controlada, 1–3 pilotos.

---

## Release 1 — Self-service vendável (plano R$1.997)

**Objetivo:** cliente do plano básico opera sozinho sem precisar de
intervenção da equipe Dr. Newsletter.

**Entregas:**

- Onboarding guiado (4 passos: criar cliente → voz → arquivo → primeiro plano).
- Checklist visual de configuração do cliente.
- Estados "configuração incompleta" no UI.
- Melhorias de UX do planejador e do writer.
- Exportação melhorada (subject + preview + body em formato copiável).
- Status `enviado / não enviado` (manual).
- Limites do plano básico aplicados em código.
- Documentação pública (Help) revisada.

**Critério:** cliente sem ajuda da equipe consegue configurar, planejar
e escrever emails do mês.

---

## Release 2 — Revisão humana (plano R$3.997)

**Objetivo:** Dr. Newsletter revisa emails do cliente antes de envio.

**Entregas:**

- Papéis de usuário: `client` e `reviewer` (equipe Dr. Newsletter).
- Fila de emails aguardando revisão.
- Status: `aguardando revisão` → `revisado` → `aguardando aprovação` → `aprovado`.
- Comentários internos e comentários para o cliente.
- Histórico de revisões.
- Painel interno da equipe (filtrar por cliente, prioridade).
- Notificações simples (email + badge na app).

**Critério:** ciclo completo de review humana operável dentro do app.

---

## Release 3 — Operação agência / cocriação (plano R$6.997)

**Objetivo:** equipe Dr. Newsletter opera junto ou pelo cliente.

**Entregas:**

- Atribuição de responsável por cliente.
- Calendário editorial visual.
- Status por email e por mês.
- Tarefas internas (não vistas pelo cliente).
- Notas privadas.
- Aprovação final pelo cliente com 1 clique.
- Registro de envio.
- Visualização "como está esse cliente" em 1 tela.

**Critério:** equipe opera múltiplos clientes sem planilha externa.

---

## Release 4 — Billing e planos

**Objetivo:** assinatura controlada por software.

**Entregas:**

- Cadastro de plano no usuário.
- Status de assinatura (active / past_due / canceled).
- Integração com Asaas (BR) e/ou Stripe.
- Cobrança recorrente.
- Bloqueios suaves para inadimplência (3 dias de carência, depois banner).
- Área de conta/faturamento.

**Critério:** receita cobrada automaticamente, sem planilha financeira.

---

## Release 5 — Métricas e feedback loop

**Objetivo:** o planejamento do mês seguinte usa dados do anterior.

**Entregas:**

- Registro manual de envio (e métricas: enviados, abertos, cliques, replies).
- Receita atribuída manualmente por email (presencial: cliente conta).
- Histórico por cliente.
- Insights: "temas que puxaram mais retorno".
- Recomendações para o próximo planejamento.

**Critério:** ao gerar o plano de N+1, o prompt inclui dados de N.

---

## Release 6 — Integrações de envio

**Objetivo:** reduzir copy/paste manual.

**Entregas (uma de cada vez, conforme demanda):**

- Mailchimp.
- RD Station.
- ActiveCampaign.
- Webhook genérico.

---

## Release 7 — Escala técnica (só se precisar)

**Objetivo:** preparar para crescimento além de 10 clientes.

**Possíveis entregas:**

- Migração para Postgres se SQLite virar gargalo (improvável até 50 clientes).
- Fila de jobs (cron + tabela `jobs` simples, sem Redis).
- Storage externo de uploads (S3 compatible).
- Observabilidade (Sentry, métricas).
- Multi-instância (improvável; mais provável é vertical scale).

---

## Priorização atual

```
1. Release 0           ← em execução
2. Release 0-Deploy
3. Release 1 — self-service (pré-requisito da campanha de junho/2026)
4. Release 2 — revisão humana
5. Release 3 — operação agência
6. Release 4 — billing
7. Releases 5–7 conforme necessidade
```

---

## Métricas de sucesso 2026

| Métrica | Meta |
|--------|------|
| Clientes ativos | 10 |
| Receita recorrente mensal | R$ 25k – 50k |
| Tempo médio de planejamento (humano) | < 30 min |
| Custo de IA / cliente / mês | < R$ 30 |
| Churn anual | < 20% |
| NPS | ≥ 50 |
