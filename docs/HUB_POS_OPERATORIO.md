# Hub Pós-Operatório — Proposta Técnica Unio

> **Documentação completa de integração:** [HUB_POS_OPERATORIO_INTEGRACAO.md](./HUB_POS_OPERATORIO_INTEGRACAO.md)  
> **PDF para reunião/apresentação:** [HUB_POS_OPERATORIO_INTEGRACAO.pdf](./HUB_POS_OPERATORIO_INTEGRACAO.pdf)  
> (arquitetura, telas, fluxos, permissões, API, LGPD e roadmap detalhado)

Documento resumido para apresentação comercial e alinhamento técnico. Descreve como um **sistema de acompanhamento pós-operatório** se encaixa na arquitetura da plataforma Unio.

---

## 1. Resumo executivo

A Unio é uma plataforma **multi-empresa (workspace)** com **hubs modulares**. Um hub de pós-operatório entraria como **Núcleo Pós-Operatório** (`hub_pos_operatorio`), reutilizando infraestrutura já existente:

- autenticação e permissões granulares
- notificações e alertas (padrão Hub TI)
- bate-papo integrado
- Vitória IA (copiloto contextual)
- integrações via API/webhooks
- conformidade LGPD (workspace isolado + auditoria)

**Estimativa MVP:** 6–10 semanas · **Versão completa:** 3–4 meses

---

## 2. Arquitetura da plataforma Unio

### Stack

| Camada | Tecnologia |
|--------|------------|
| Backend | PHP 8.2 + Symfony 7.4 |
| Persistência | Doctrine ORM + MySQL |
| UI | Twig + CSS Unio + Stimulus |
| Tempo real | Mercure (notificações, chat) |
| Cache (prod) | Redis |

### Modelo em camadas

```
Plataforma Unio
├── Tenant (operador global)
├── Workspace (Empresa) — clínica, hospital ou unidade
│   ├── Hub Pós-Operatório
│   │   ├── Produto: Pacientes
│   │   ├── Produto: Protocolos
│   │   ├── Produto: Questionários
│   │   ├── Produto: Alertas
│   │   ├── Produto: Painel médico
│   │   └── Produto: Portal do paciente
│   └── Serviços compartilhados (Chat, Vitória, Notificações, API)
└── Permissões: escopo × produto × perfil (MEMBRO → GESTOR)
```

### Padrão de código (já usado em RH e TI)

```
src/Controller/Module/PosOperatorio/   ← rotas HTTP
src/Service/PosOperatorio/               ← regras de negócio
src/Entity/PosOperatorio*.php            ← domínio clínico
templates/modules/pos-operatorio/        ← telas
```

Referência: `docs/ESTRUTURA.md`, `docs/QUALIDADE_PERFORMANCE_E_HUBS.md`

---

## 3. O que o sistema faria

### Personas

| Perfil | O que faz |
|--------|-----------|
| **Médico** | Cadastra paciente pós-cirurgia, define protocolo, vê painel e alertas |
| **Enfermagem** | Responde alertas, acompanha questionários, comunica via chat |
| **Paciente** | Responde questionário diário, vê orientações, fala com equipe |
| **Admin clínica** | Configura protocolos, permissões, white-label do workspace |

### Fluxo principal

1. **Alta cirúrgica** → cadastro do paciente no hub (manual ou via integração com prontuário)
2. **Protocolo vinculado** → checklist por tipo de procedimento (D+1, D+3, D+7…)
3. **Questionário diário** → paciente responde no celular (dor, febre, sangramento, mobilidade)
4. **Motor de alertas** → respostas críticas disparam notificação (equivalente a chamado P1 no Hub TI)
5. **Equipe responde** → chat + registro na linha do tempo
6. **Vitória IA** → orienta dúvidas frequentes e escala para humano quando necessário
7. **Encerramento** → alta do acompanhamento + relatório exportável

---

## 4. Mapeamento com módulos existentes

| Necessidade clínica | Módulo Unio existente | Ação |
|---------------------|----------------------|------|
| Multi-clínica | Workspace (`Empresa`) | Reutilizar |
| Alertas críticos | Hub TI — chamados P1 + SLA | Adaptar |
| Sala de crise | War Room (TI) | Adaptar |
| Workflow com checklist | RH — admissão/offboarding | Adaptar |
| Chat paciente ↔ equipe | Bate-papo | Reutilizar |
| Orientações automáticas | Vitória IA | Reutilizar + contexto clínico |
| Dashboard KPIs | Hub TI / RH | Reutilizar layout |
| Integração prontuário | Hub Integrações | Reutilizar |
| LGPD | Auditoria + workspace isolado | Reutilizar + reforço saúde |
| Cadastro paciente | — | **Criar** |
| Protocolo por cirurgia | — | **Criar** |
| Questionário clínico | — | **Criar** |

---

## 5. Modelo de dados (MVP)

### Entidades principais

**PosOperatorioPaciente**
- empresa (FK), codigo, nome, contato
- procedimento, data_cirurgia, medico_responsavel
- protocolo (FK), status (ativo, alerta, encerrado)
- user (FK opcional — portal do paciente)

**PosOperatorioProtocolo**
- empresa (FK), nome, tipo_procedimento
- duracao_dias, checklist JSON, perguntas JSON

**PosOperatorioQuestionarioResposta**
- paciente (FK), data, respostas JSON, score_risco
- alerta_gerado (bool), respondido_em

**PosOperatorioAlerta**
- paciente (FK), prioridade (P1–P4), motivo, status
- responsavel (FK User), sla_limite, resolvido_em

**PosOperatorioEvento** (linha do tempo)
- paciente (FK), tipo, descricao, autor, created_at

---

## 6. Fases de entrega

### Fase 1 — MVP (6–10 semanas)

- [ ] Hub registrado + permissões + dashboard
- [ ] CRUD pacientes pós-cirúrgicos
- [ ] Protocolos por tipo de procedimento
- [ ] Questionário diário (paciente mobile)
- [ ] Alertas automáticos + painel enfermagem
- [ ] Linha do tempo básica

### Fase 2 — Operacional (4–6 semanas)

- [ ] Chat integrado paciente ↔ equipe
- [ ] Vitória IA com base de orientações pós-op
- [ ] SLA de resposta da equipe
- [ ] Relatórios e exportação PDF/CSV
- [ ] Notificações push/e-mail

### Fase 3 — Integração (4–8 semanas)

- [ ] API/webhook — receber alta do prontuário
- [ ] Integração TOTVS / MV / sistemas hospitalares
- [ ] Multi-unidade (rede de clínicas)
- [ ] Analytics cross-paciente (Cortex)

---

## 7. Conformidade e saúde

Dados de saúde exigem cuidado adicional:

- **Consentimento** explícito do paciente no onboarding do acompanhamento
- **Minimização** — coletar só o necessário ao protocolo
- **Auditoria** — log de quem acessou prontuário/respostas
- **Retenção** — política de exclusão/anonymização após encerramento
- **Perfis restritos** — paciente vê apenas seus dados

A base LGPD da Unio (workspace isolado, grants, sessão segura) já cobre parte disso; a fase clínica adiciona termos e fluxos específicos.

---

## 8. Investimento e retorno (referência)

| Item | Benefício |
|------|-----------|
| Menos readmissões | Detecção precoce de complicações |
| Menos ligações | Questionário + Vitória respondem dúvidas rotineiras |
| Registro único | Substituir WhatsApp solto + planilha |
| Escala | Mesma plataforma para N clínicas (workspaces) |

---

## 9. Próximos passos na conversa

1. Tipo de cirurgia / especialidade (ortopedia, geral, plástica…)
2. Volume mensal de pacientes
3. Quem usa hoje (planilha, WhatsApp, outro sistema?)
4. Integração com prontuário existente?
5. Paciente acessa sozinho ou só equipe no início?

---

## 10. Registro no código (esboço inicial)

Já incluído no repositório:

| Arquivo | Função |
|---------|--------|
| `src/Config/PlannedHubRegistry.php` | Núcleo na sidebar |
| `src/Service/PermissionService.php` | Escopo e produtos |
| `src/Controller/Module/PosOperatorio/PosOperatorioController.php` | Rota `/pos-operatorio` |
| `src/Service/PosOperatorio/PosOperatorioService.php` | Dashboard (dados ilustrativos) |
| `src/Entity/PosOperatorioPaciente.php` | Entidade base |
| `src/Entity/PosOperatorioProtocolo.php` | Entidade base |
| `templates/modules/pos-operatorio/` | UI do hub |

**Próximo passo técnico:** migration Doctrine + CRUD de pacientes.

---

*Documento gerado para alinhamento Unio × proposta Professor Paulo — acompanhamento pós-operatório.*
