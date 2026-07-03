# Branches e deploy — como funciona a partir de hoje

Guia operacional atualizado após estabilização do pipeline (jul/2026).  
Para o modelo completo de longo prazo, veja também [BRANCHES.md](BRANCHES.md).

---

## Mapa rápido

```
┌─────────────────────────────────────────────────────────────────┐
│  DESENVOLVIMENTO                                                │
│  feature/*  ou  product/*  →  trabalho diário                   │
│       │                                                         │
│       ▼  merge quando pronto + CI verde                         │
│  production  ──push──►  GitHub Actions                          │
│       │              validate → deploy → HostGator              │
│       ▼                                                         │
│  Site em produção (unio / public_html)                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## Branch por branch

### `production` — **única que faz deploy**

| | |
|---|---|
| **Deploy** | Sim — automático a cada `git push origin production` |
| **CI** | Sim — workflow `CI` + `Deploy Production` |
| **Uso** | Código que está (ou vai estar) no ar na HostGator |
| **HEAD atual** | `7abf2c2` |

```bash
git checkout production
git pull origin production
git merge feature/sua-branch   # ou product/rh, etc.
git push origin production     # dispara validate + deploy
```

**Não** faça push direto sem validação local quando possível (`make validate-docker` ou `composer validate:ci`).

---

### `feature/*` — desenvolvimento de funcionalidade

| | |
|---|---|
| **Deploy** | Não |
| **CI** | Sim — a cada push (workflow `CI` via `validate-reusable.yml`) |
| **Exemplo ativo** | `feature/core-responsive-layout` (layout, kanban, validação) |
| **Fluxo** | Commits → push → CI verde → merge em `production` (ou `product/*` → `main` conforme [BRANCHES.md](BRANCHES.md)) |

Branches `feature/*` típicas no repo:

- `feature/core-*` — login, perfil, projetos, chat, permissões, layout
- `feature/admin-modulos` — admin

---

### `product/*` — módulos de longa duração

| Branch | Escopo |
|--------|--------|
| `product/core` | Layout, dashboard, shell |
| `product/rh` | RH |
| `product/pessoas` | Pessoas |
| `product/engenharia` | Engenharia |
| `product/hub-ti` | Hub TI |
| `product/hub-pos-operatorio` | Pós-operatório |
| `product/admin` | Admin tenant |
| … | Ver [BRANCHES.md](BRANCHES.md) |

| | |
|---|---|
| **Deploy** | Não (até merge em `production`) |
| **CI** | Sim — push dispara `CI` |
| **Fluxo oficial** | `feature/*` → `product/*` → `main` → staging → `production` |

**Atalho usado nesta sessão:** merge direto `feature/core-responsive-layout` → `production` para entregar layout e pipeline rapidamente. Para releases formais, preferir o fluxo completo via `main` / staging.

---

### `main` — integração

| | |
|---|---|
| **Deploy** | Não |
| **CI** | Sim |
| **Uso** | Tronco de integração antes de staging |
| **Estado** | Pode estar atrás de `production` / `feature/*` — alinhar quando fizer release |

---

### `new_staging` / `new_staging2` — homologação

| | |
|---|---|
| **Deploy** | Não configurado no workflow atual (só `production`) |
| **CI** | Sim — push dispara `CI` |
| **Uso** | QA e demos antes de produção (fluxo documentado em BRANCHES.md) |

---

### `hotfix/*` e `release/*` — sob demanda

Criadas a partir de `production` (hotfix) ou `main` (release). Ver [BRANCHES.md](BRANCHES.md).

---

## Fluxo recomendado **a partir de hoje**

### Dia a dia (feature)

```bash
git checkout -b feature/minha-mudanca
# ... editar ...
composer validate:pre-push    # ou make validate-docker
git add . && git commit -m "feat: ..."
git push origin feature/minha-mudanca
# Aguardar CI verde no GitHub
```

### Subir para HostGator

```bash
git checkout production
git pull origin production
git merge feature/minha-mudanca
git push origin production
# → validate (2–3 min) → deploy (1–2 min)
```

Acompanhar: https://github.com/joabe-nascimento/unio-corp/actions

### Hotfix urgente em produção

```bash
git checkout production
git pull
git checkout -b hotfix/descricao
# correção mínima
git push -u origin hotfix/descricao
# merge hotfix → production → push
# depois merge back em main / staging
```

---

## O que acontece no servidor a cada deploy

Script `scripts/deploy-server.sh`:

1. `rm -rf var/cache/prod/*`
2. `doctrine:migrations:migrate`
3. `cache:clear --env=prod --no-warmup`
4. `cache:warmup --env=prod`
5. `rsync` de `public/css`, `js`, `images`, `vendor`, `pos-operatorio` → `public_html`

**Cache:** sim, é apagado e recriado a cada deploy.

**Dados:** banco, uploads e logs **não** são apagados.

---

## Produção vs desenvolvimento local

| | Local (`APP_DEBUG=1`) | Produção (`APP_DEBUG=0`) |
|---|---|---|
| CSS | `unio-app.css` | **`unio-app.min.css`** |
| Seeds | `app:seed-users`, `app:seed-product-grants` OK | Bloqueados sem `--allow-prod` |
| Cron seeds | Não usar | **Remover** do cPanel |

**Importante:** após alterar `unio-app.css`, sempre rodar `php bin/minify-css.php` antes do deploy (já automatizado no workflow).
