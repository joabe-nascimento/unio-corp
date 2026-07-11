# Operação Unio Saúde — índice (jul/2026)

Documentação da instância **uniosaude** (Unio Saúde, deploy dedicado).

| Documento | Conteúdo |
|-----------|----------|
| [ARQUITETURA.md](ARQUITETURA.md) | Stack, camadas e módulos clínicos |
| [HUB_POS_OPERATORIO.md](HUB_POS_OPERATORIO.md) | Pós-operatório — pacientes, protocolos, alertas |
| [HUB_POS_OPERATORIO_INTEGRACAO.md](HUB_POS_OPERATORIO_INTEGRACAO.md) | Integração do hub clínico |
| [ROADMAP_TRANSICAO_ORGANISMO.md](ROADMAP_TRANSICAO_ORGANISMO.md) | Transição UI Organismo / Pulso |
| [DNS_UNIOWORK_UNIOSAUDE.md](DNS_UNIOWORK_UNIOSAUDE.md) | DNS e subdomínio uniosaude |
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
| **Deploy** | push `uniosaude` → validate + deploy |
| **Servidor** | `/home2/joabef36/unio-uniosaude` |
| **Login staff demo** | `renata.oliveira@unio.dev` / `unio123` |
| **Login paciente demo** | `joabe.nascimento@unio.dev` / `unio123` |

## Fluxo de acesso

Login em `/login` → redirecionamento direto para `/pulso` (sem seleção de workspace).

Portal do paciente: `/clinica/portal/login`
