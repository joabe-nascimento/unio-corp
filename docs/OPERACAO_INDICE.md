# Operação Unio Clínica — índice (jul/2026)

Documentação da instância **clinicaunio** (Unio Clínica, deploy dedicado).

| Documento | Conteúdo |
|-----------|----------|
| [ARQUITETURA.md](ARQUITETURA.md) | Stack, camadas e módulos clínicos |
| [HUB_POS_OPERATORIO.md](HUB_POS_OPERATORIO.md) | Pós-operatório — pacientes, protocolos, alertas |
| [HUB_POS_OPERATORIO_INTEGRACAO.md](HUB_POS_OPERATORIO_INTEGRACAO.md) | Integração do hub clínico |
| [ROADMAP_TRANSICAO_ORGANISMO.md](ROADMAP_TRANSICAO_ORGANISMO.md) | Transição UI Organismo / Pulso |
| [DNS_UNIOWORK_CLINICAUNIO.md](DNS_UNIOWORK_CLINICAUNIO.md) | DNS e subdomínio clinicaunio |
| [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) | Pipeline SSH HostGator |
| [DEPLOY_AGORA.md](DEPLOY_AGORA.md) | Checklist rápido de deploy |
| [OPERACAO_VALIDACAO_LOCAL.md](OPERACAO_VALIDACAO_LOCAL.md) | Validação antes do push |
| [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md) | Workflows CI e deploy |
| [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) | Branch `clinicaunio` → deploy automático |
| [OPERACAO_HISTORICO_CORRECOES.md](OPERACAO_HISTORICO_CORRECOES.md) | Histórico de correções |

## Estado atual

| Item | Valor |
|------|--------|
| **Branch** | `clinicaunio` |
| **URL** | https://clinicaunio.uniowork.com.br |
| **Deploy** | push `clinicaunio` → validate + deploy |
| **Servidor** | `/home2/joabef36/unio-clinicaunio` |
| **Login staff demo** | `gestor@nexus.dev` / `unio123` |
| **Login paciente demo** | `tenant@unio.dev` / `unio123` |

## Fluxo de acesso

Login em `/login` → redirecionamento direto para `/pulso` (sem seleção de workspace).

Portal do paciente: `/clinica/portal/login`
