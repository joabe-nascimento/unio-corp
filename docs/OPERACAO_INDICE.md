# Operação Unio Saúde — índice (jul/2026)

Documentação da instância **uniosaude** (Unio Saúde, deploy dedicado).

| Documento | Conteúdo |
|-----------|----------|
| [ARQUITETURA.md](ARQUITETURA.md) | Stack, camadas e módulos clínicos |
| [HUB_POS_OPERATORIO.md](HUB_POS_OPERATORIO.md) | Pós-operatório — pacientes, protocolos, alertas |
| [HUB_POS_OPERATORIO_INTEGRACAO.md](HUB_POS_OPERATORIO_INTEGRACAO.md) | Integração do hub clínico |
| [ROADMAP_TRANSICAO_ORGANISMO.md](ROADMAP_TRANSICAO_ORGANISMO.md) | Transição UI Organismo / Pulso |
| [DNS_UNIOWORK_UNIOSAUDE.md](DNS_UNIOWORK_UNIOSAUDE.md) | DNS e subdomínio uniosaude |
| [UNIOSAUDE_DEPLOY_REPAIR.md](UNIOSAUDE_DEPLOY_REPAIR.md) | Vhost HTTPS, reparo manual e pós-deploy automático |
| [DEPLOY_MANUAL_UNIOSAUDE.md](DEPLOY_MANUAL_UNIOSAUDE.md) | **Deploy manual PC → HostGator (sem Actions)** |
| [UNIOSAUDE_DEPLOY_OPERACAO.md](UNIOSAUDE_DEPLOY_OPERACAO.md) | **Operação atual: push vs manual, X vermelho, billing** |
| [UNIOSAUDE_ACESSOS.md](UNIOSAUDE_ACESSOS.md) | **Login, senhas e portal do beneficiário (produção)** |
| [UNIOSAUDE_BANCO.md](UNIOSAUDE_BANCO.md) | **MySQL: migrations, backup, tabelas carteirinha/comprovante** |
| [UNIOSAUDE_IDEIAS_ROADMAP.md](UNIOSAUDE_IDEIAS_ROADMAP.md) | **Ideia de produto, etapas E0–E5 (continuidade → agenda → atendimento → fatura → TISS)** |
| [UNIOSAUDE_REPAIR_500.md](UNIOSAUDE_REPAIR_500.md) | **HTTP 500 — .env.local inválido (reparo cPanel)** |
| [UNIOSAUDE_DEPLOY_RESULTADO_2026-07-11.md](UNIOSAUDE_DEPLOY_RESULTADO_2026-07-11.md) | Relatório do deploy manual 11/07 |
| [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) | Pipeline SSH HostGator |
| [DEPLOY_AGORA.md](DEPLOY_AGORA.md) | Checklist rápido de deploy |
| [OPERACAO_VALIDACAO_LOCAL.md](OPERACAO_VALIDACAO_LOCAL.md) | Validação antes do push |
| [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md) | Workflows CI e deploy |
| [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) | Branch `uniosaude` → deploy automático |
| [OPERACAO_HISTORICO_CORRECOES.md](OPERACAO_HISTORICO_CORRECOES.md) | Histórico de correções |

## Estado atual

| Item | Valor |
|------|--------|
| **Branch** | `uniosaude` |
| **URL** | https://uniosaude.uniowork.com.br |
| **Deploy** | [UNIOSAUDE_DEPLOY_OPERACAO.md](UNIOSAUDE_DEPLOY_OPERACAO.md) — push → GitHub; produção → script manual |
| **Servidor** | `/home2/joabef36/unio-uniosaude` |
| **Login staff (produção)** | **`renata.oliveira@unio.dev` / `unio123`** — ver [UNIOSAUDE_ACESSOS.md](UNIOSAUDE_ACESSOS.md) |
| **Login tenant** | `joabe.nascimento@unio.dev` / `unio123` |
| **Carteirinha demo** | CPF `529.982.247-25` + código `PO-0042` (dois passos) |

## Fluxo de acesso

Login em `/login` → redirecionamento direto para `/pulso` (sem seleção de workspace).

Portal do paciente: `/clinica/portal/login`
