# Tecnologias e perfil de vagas — Unio Jurídico

Mapa do que o produto usa hoje e o que o mercado cobra em vagas de **legal tech**, **plataforma jurídica** e **IA aplicada ao Direito**. Serve para priorizar o roadmap e para descrever o stack em entrevistas/proposta comercial.

## O que o Unio Jurídico já entrega

| Camada | Stack em produção | Equivalente de vaga |
| --- | --- | --- |
| Plataforma | PHP 8.3, Symfony 7, Twig, Doctrine, MySQL | Backend sênior / full-stack jurídico |
| Front do escritório | HTML/CSS próprio, JS vanilla, layout Organismo | UI de produto B2B, design system |
| Assistente Sasha | Chat + tools confirmáveis + histórico pin/delete | Agent tooling, function calling |
| Motor de IA | JurisFlow (FastAPI, Azure OpenAI, RAG) | AI engineer / LangChain |
| Operação | HostGator, keepalive, cron, migrations | DevOps enxuto / SRE de produto |

## O que as vagas de legal tech pedem (2025–2026)

Pesquisa em descrições de vagas (legal ops, CLM, litigation, e-discovery, legal AI):

1. **Ingestão de documentos** — OCR, classificação, metadados (CNJ, tipo de peça, partes).
2. **Pipeline de publicações** — DJEN/DJE → triagem → prazo → alerta, com auditoria.
3. **Linha do tempo do processo** — eventos unificados (pub, prazo, doc, audiência).
4. **RAG persistente** — base do escritório que sobrevive a restart; citação da fonte.
5. **Jobs assíncronos** — análise longa, reindex, comparação de versões.
6. **Webhooks HMAC** — integração com ERP, BI e automações do cliente.
7. **Conflito de interesses e LGPD** — check de partes, redação de PII, trilha de incidente.
8. **Portal do cliente** — upload, aprovação de minuta, status do caso.
9. **Audiências** — pauta + roteiro gerado por IA.
10. **Templates de peça** — biblioteca + preenchimento assistido.
11. **Evals / guardrails** — não inventar jurisprudência; log de tool calls.
12. **Assinatura e precedente** — GED com ciclo de vida, não só upload.

## Como isso entra no código

- Symfony: pipeline DJEN, timeline, audiências, conflitos, compliance, webhooks, templates, ingestão GED.
- Sasha tools: `triar_publicacao`, `buscar_timeline_processo`, `buscar_publicacoes`, `indexar_documento` (além das tools já estáveis de prazo, minuta, DataJud, etc.).
- JurisFlow: `POST /v1/jobs`, `POST /v1/extract/process-metadata`, `POST /v1/compliance/redact`, RAG SQLite, chains `publication-triage` e `hearing-prep`.

## Como falar isso em uma vaga / proposta

- Não vender “chat genérico”. Vender **fluxo operacional**: intimação → prazo → alerta → peça.
- Toda escrita no escritório passa por **confirmação** (tool confirmável).
- IA é **camada**, não fonte da verdade: CNJ, prazos e carteira vêm do banco.
- Observabilidade: health do motor, jobs, status de pipeline, trilha de compliance.
