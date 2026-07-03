# Operação Unio — índice (jul/2026)

Documentação consolidada após estabilização do deploy HostGator, CI/CD e validação pré-push.

| Documento | Conteúdo |
|-----------|----------|
| [OPERACAO_HISTORICO_CORRECOES.md](OPERACAO_HISTORICO_CORRECOES.md) | Cronologia completa: erros → correções → sucesso |
| [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) | Como cada branch funciona **a partir de hoje** |
| [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md) | Workflows CI, validate e Deploy Production |
| [OPERACAO_VALIDACAO_LOCAL.md](OPERACAO_VALIDACAO_LOCAL.md) | Script, Docker Compose, hooks git, comandos |
| [OPERACAO_EMAIL_PLATAFORMA.md](OPERACAO_EMAIL_PLATAFORMA.md) | E-mails HostGator/Titan, login, assinaturas, secrets e deploy |
| [BRANCHES.md](BRANCHES.md) | Modelo oficial de branches (feature → product → main → staging → production) |
| [DEPLOY_AGORA.md](DEPLOY_AGORA.md) | Checklist rápido HostGator |
| [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) | Secrets, variáveis e SSH |

## Estado atual (último deploy)

| Item | Valor |
|------|--------|
| **Branch de produção** | `production` @ `3f2a3b1` |
| **Último commit** | `fix: CI so em branches oficiais e corrige block page_scroll_mode no Twig` |
| **Deploy** | [Actions](https://github.com/joabe-nascimento/unio-corp/actions) — CI + Deploy Production em push para `production` |
| **Servidor** | HostGator — `/home2/joabef36/unio` + `public_html` |
| **Sync espelhos** | `bash scripts/sync-branches.sh production` |

## Regra de ouro

> Nada sobe para a HostGator sem passar por `production` + pipeline **Validate** + **Deploy Production**.

> Espelhar branches: `bash scripts/sync-branches.sh production` — não push manual em massa.
