# Validação local — antes de push e deploy

Como rodar as mesmas checagens do CI na sua máquina (jul/2026).

---

## Comandos rápidos

| Comando | Quando usar |
|---------|-------------|
| `composer validate:pre-push` | Antes de cada push (banco local existente) |
| `composer validate:ci` | Igual ao CI — recria banco do zero |
| `composer validate:pre-push:quick` | Só lints (rápido) |
| `composer validate:docker` | Tudo via Docker (sem PHP/MySQL local) |
| `make validate-docker` | Atalho para Docker |
| `composer hooks:install` | Instala hook `pre-push` (valida automaticamente) |

---

## 1. Script principal

**Arquivo:** `scripts/validate-before-push.sh`

### Variáveis de ambiente

| Variável | Efeito |
|----------|--------|
| `VALIDATE_FRESH_DB=1` | Drop/create/schema/seeds (modo CI) |
| `QUICK=1` | Pula PHPStan, PHPUnit, npm |
| `SKIP_PHPSTAN=1` | Pula PHPStan |
| `SKIP_TESTS=1` | Pula PHPUnit |
| `SKIP_DB=1` | Pula tudo que precisa de banco |
| `SKIP_VALIDATE=1` | No hook: pula validação no `git push` |
| `GIT_BRANCH=production` | Pula PHPStan (como no deploy) |

### Exemplos

```bash
# Validação completa igual CI
composer validate:ci

# Só lints
QUICK=1 bash scripts/validate-before-push.sh

# Pular validação uma vez
SKIP_VALIDATE=1 git push
# ou
git push --no-verify
```

---

## 2. Docker Compose

**Arquivo:** `docker-compose.yml` (profile `validate`)

### Serviços

| Serviço | Função |
|---------|--------|
| `mariadb` | MariaDB 10.11 na porta **3307** (host) |
| `validate` | PHP 8.2 + Composer + Node — roda o script de validação |

### Uso

```bash
docker compose --profile validate run --rm validate
# ou
bash scripts/validate-docker.sh
# ou
make validate-docker
```

O container:

1. Instala `vendor/` e `node_modules/` se ausentes
2. Define `VALIDATE_FRESH_DB=1` e `DATABASE_URL` apontando para `mariadb`
3. Executa `scripts/validate-before-push.sh`

**Requisito:** Docker Desktop instalado e rodando.

---

## 3. Hook Git pre-push

**Instalar (uma vez):**

```bash
composer hooks:install
# Windows PowerShell:
.\scripts\install-git-hooks.ps1
```

**Comportamento:** cada `git push` executa `validate-before-push.sh` antes de enviar.

**Pular:** `SKIP_VALIDATE=1 git push` ou `git push --no-verify`

---

## 4. CSS minificado (crítico para produção)

Produção carrega `unio-app.min.css` quando `APP_DEBUG=0`.

```bash
# Após editar public/css/unio-app.css:
php bin/minify-css.php

# Bump cache no template (se mudança grande):
# templates/base.html.twig → ?v=295
```

**Automatizado em:**

- `validate-before-push.sh` (step "CSS minificado")
- `deploy-production.yml` (antes do tar)

---

## 5. Validação de sistema (permissões)

```bash
php bin/console app:validate-system
# ou
composer validate:system
```

Verifica: rotas core, usuários seed, matriz de permissões, workspace.

Se falhar após mudanças em grants:

```bash
php bin/console app:seed-users
php bin/console app:seed-product-grants --force
```

---

## 6. Makefile

```bash
make help              # lista targets
make validate          # composer validate:pre-push
make validate-ci       # composer validate:ci
make validate-quick    # QUICK=1
make validate-docker   # Docker Compose
make hooks-install     # git hook
```

---

## Checklist antes de merge em `production`

- [ ] `composer validate:ci` ou `make validate-docker` passou
- [ ] Se alterou CSS: `php bin/minify-css.php` commitado
- [ ] CI verde na branch de origem
- [ ] Sem secrets em `.env` no commit
- [ ] Merge em `production` + `git push origin production`
- [ ] Actions: validate + deploy **success**
- [ ] Hard refresh no browser (Ctrl+Shift+R)

---

## Windows — notas

- Preferir **Git Bash** para `validate-before-push.sh`
- PowerShell: `.\scripts\validate-before-push.ps1 -Quick`
- Composer via Windows Store pode falhar (`PharException`) — usar Composer instalado globalmente ou Docker
