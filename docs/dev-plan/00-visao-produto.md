# Visão de produto — Dr. Newsletter SaaS

## O que é

Plataforma service-led de planejamento e escrita de newsletters para
**negócios presenciais premium**: joalherias, restaurantes, clínicas,
óticas, e similares — operações que vendem **relacionamento e
recompra**, não checkout.

## Quem usa

- **Felipe Pavão / equipe Dr. Newsletter** — operadores que gerenciam
  N clientes através da plataforma.
- **Clientes** dos planos R$3.997 e R$6.997 — recebem emails revisados
  ou produzidos junto com a equipe.
- **Clientes self-service** do plano R$1.997 — usam sozinhos.

Não é ferramenta de mass-market. Não é ferramenta de SaaS auto-serviço
gigante. É plataforma para uma operação cuidadosa de até 10 clientes
no primeiro ano.

## O que entrega

1. **Voz da Marca capturada** — upload de TXT, IA estrutura, fica
   reutilizável e versionada.
2. **Planejamento mensal** com framework de 5 categorias (educação,
   histórias, bastidores, estilo de vida, promocional).
3. **Geração assistida de emails** com iteração via chat até ficar
   "no tom" — Dr. Newsletter quality.
4. **Aprovação e exportação** para envio em qualquer plataforma de
   email marketing.
5. **(Releases futuras)** revisão humana, cocriação, métricas, billing.

## O que NÃO entrega (escopo recusado)

- **Atribuição de receita por email** para negócio presencial. Não dá
  pra medir limpo. Tentar é teatro. Vendemos presença e recompra.
- **Editor visual drag-and-drop**. Foco no conteúdo, não no design.
- **Envio nativo de emails**. Cliente usa a plataforma dele
  (Mailchimp/RD/AC) — integramos depois.
- **Multi-linguagem**. PT-BR only.
- **Mobile app**.

## Planos comerciais

| Plano | Preço | O que inclui |
|------|-------|--------------|
| **Self-service** | R$ 1.997 / mês | Plataforma + IA, cliente opera sozinho |
| **Revisão humana** | R$ 3.997 / mês | Acima + revisão da equipe Dr. Newsletter |
| **Cocriação / Agência** | R$ 6.997 / mês | Operação compartilhada ou feita pela equipe |

**Founding Circle:** 5 primeiros clientes pagam preço cheio + têm acesso
extra (identidade visual, suporte direto, voz no roadmap). Sem desconto.
Mais acesso, menos preço. (Priestley way.)

## Princípios de decisão

1. **Lifestyle game, não scale game.** Meta de 10 clientes em 2026,
   não 100. Margem alta, operação enxuta.
2. **Service-led, não tool-led.** Software amplifica humano, não
   substitui.
3. **Boring tech.** PHP + SQLite + nginx. Vai rodar por anos sem
   manutenção.
4. **Segurança da chave Anthropic é requisito não-funcional duro.**
   Já houve trauma. Não acontece de novo.
5. **Decisões reversíveis primeiro.** Migrations versionadas, sem
   ORM que esconda magia, código fácil de ler e reescrever.

## Métrica norte

Cliente do plano de R$3.997 ou R$6.997 abre a plataforma uma vez por
semana, leva ~30 min para aprovar/comentar emails, e tem newsletter
saindo no tom dele sem precisar pensar nisso.

Quando isso for verdade para 10 clientes simultaneamente, a Release
0–3 está cumprida.
