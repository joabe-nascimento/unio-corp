# Operação Unio — índice (jul/2026)

Documentação consolidada após estabilização do deploy HostGator, CI/CD e validação pré-push.

| Documento | Conteúdo |
|-----------|----------|
| [OPERACAO_HISTORICO_CORRECOES.md](OPERACAO_HISTORICO_CORRECOES.md) | Cronologia completa: erros → correções → sucesso |
| [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) | Como cada branch funciona **a partir de hoje** |
| [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md) | Workflows CI, validate e Deploy Production |
| [OPERACAO_VALIDACAO_LOCAL.md](OPERACAO_VALIDACAO_LOCAL.md) | Script, Docker Compose, hooks git, comandos |
| [BRANCHES.md](BRANCHES.md) | Modelo oficial de branches (feature → product → main → staging → production) |
| [DEPLOY_AGORA.md](DEPLOY_AGORA.md) | Checklist rápido HostGator |
| [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) | Secrets, variáveis e SSH |

## Estado atual (último deploy com sucesso)

| Item | Valor |
|------|--------|
| **Branch de produção** | `production` @ `7abf2c2` |
| **Último commit** | `fix(core): sincroniza unio-app.min.css para corrigir kanban em prod` |
| **Deploy** | [Actions run](https://github.com/joabe-nascimento/unio-corp/actions) — validate + deploy **success** |
| **Servidor** | HostGator — `/home2/joabef36/unio` + `public_html` |
| **APP_DEBUG** | `0` em produção (usa `unio-app.min.css`) |

## Regra de ouro

> Nada sobe para a HostGator sem passar por `production` + pipeline **Validate** + **Deploy Production**.
