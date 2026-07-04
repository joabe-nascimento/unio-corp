# Deploy automático — GitHub Actions → HostGator (SSH)

Push na branch **`production`** → GitHub builda o projeto e envia para o servidor via SSH.

Repositório: `https://github.com/joabe-nascimento/unio-corp`

---

## Visão do fluxo

```
PC: git push origin production
        ↓
GitHub Actions (.github/workflows/deploy-production.yml)
  1. composer install --no-dev
  2. npm ci + vendor:sync
  3. rsync → /home2/joabef36/unio/
  4. SSH: migrations + cache + sync public_html
        ↓
https://uniowork.com.br atualizado
```

**Não sobrescreve no servidor:** `.env.local`, `var/log/`, `public/uploads/` (fotos e arquivos de usuários).

---

## Parte 1 — HostGator (uma vez)

### 1.1 Ativar SSH

1. cPanel → busque **SSH Access** / **Acesso SSH**
2. Se não aparecer, abra chamado na HostGator pedindo SSH (planos Business/Turbo costumam ter)
3. Anote:
   - **Host:** IP do servidor ou `uniowork.com.br`
   - **Porta:** geralmente `22`
   - **Usuário:** `joabef36`

### 1.2 Garantir pastas no servidor

No Gerenciador de arquivos:

```
/home2/joabef36/unio/          ← app Symfony
/home2/joabef36/public_html/   ← domínio (index.php + estáticos)
```

### 1.3 `.env.local` no servidor (obrigatório)

Arquivo: `/home2/joabef36/unio/.env.local`

Deve existir **antes** do primeiro deploy automático (criado na instalação manual).  
O GitHub **nunca** envia esse arquivo.

### 1.4 `public_html/index.php`

Confirme que existe e aponta para o Symfony:

```php
<?php

use App\Kernel;

require_once __DIR__.'/../unio/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
```

---

## Parte 2 — Chave SSH de deploy (uma vez)

No **PowerShell do seu PC**:

### 2.1 Gerar par de chaves (só para deploy)

```powershell
ssh-keygen -t ed25519 -C "github-deploy-unio" -f "$env:USERPROFILE\.ssh\unio_deploy" -N '""'
```

Arquivos criados:

- `C:\Users\SEU_USUARIO\.ssh\unio_deploy` → **privada** (vai para o GitHub)
- `C:\Users\SEU_USUARIO\.ssh\unio_deploy.pub` → **pública** (vai para a HostGator)

### 2.2 Instalar chave pública na HostGator

1. Abra `unio_deploy.pub` no Bloco de notas
2. cPanel → **SSH Access** → **Manage SSH Keys** → **Import Key**
3. Cole o conteúdo da `.pub` → **Import**
4. **Authorize** a chave

### 2.3 Testar conexão

```powershell
ssh -i "$env:USERPROFILE\.ssh\unio_deploy" -p 22 joabef36@SEU_HOST
```

Substitua `SEU_HOST` pelo host/IP do cPanel.  
Se entrar no shell, SSH está OK. Digite `exit` para sair.

---

## Parte 3 — Secrets no GitHub (uma vez)

1. Abra: `https://github.com/joabe-nascimento/unio-corp/settings/secrets/actions`
2. **New repository secret** para cada item:

| Nome | Valor |
|------|--------|
| `DEPLOY_SSH_HOST` | Host ou IP do servidor (ex.: `gatorXXXX.hostgator.com.br`) |
| `DEPLOY_SSH_USER` | `joabef36` |
| `DEPLOY_SSH_KEY` | Conteúdo **inteiro** do arquivo `unio_deploy` (privada), incluindo `-----BEGIN...` |
| `DEPLOY_SSH_PORT` | `22` (se a HostGator usar outra porta, coloque aqui) |

### 3.1 Variáveis do repositório (opcional)

Settings → **Secrets and variables** → **Actions** → aba **Variables**:

| Nome | Valor padrão |
|------|----------------|
| `DEPLOY_PATH` | `/home2/joabef36/unio` |
| `DEPLOY_PUBLIC_HTML` | `/home2/joabef36/public_html` |

Se não criar, o workflow usa esses caminhos por padrão.

### 3.2 Environment `production` (recomendado)

Settings → **Environments** → **New environment** → nome: `production`

Opcional: exigir aprovação manual antes de cada deploy.

---

## Parte 4 — Subir o workflow (uma vez)

No PC, na branch que você usa para integrar (ex.: `main`), mergeie o workflow e envie para `production`:

```powershell
cd C:\projetos\huplex
git add .github/workflows/deploy-production.yml scripts/deploy-server.sh docs/DEPLOY_GITHUB_ACTIONS.md
git commit -m "ci: deploy automático production via SSH"
git push origin main
```

Depois, na branch `production`:

```powershell
git checkout production
git merge main
git push origin production
```

O primeiro push em `production` dispara o deploy.

Acompanhe em: `https://github.com/joabe-nascimento/unio-corp/actions`

---

## Parte 5 — Rotina do dia a dia

### Desenvolver e publicar

```powershell
cd C:\projetos\huplex

# 1. Trabalhe na sua branch
git checkout feature/minha-feature
# ... alterações ...
git add .
git commit -m "feat: minha alteração"
git push origin feature/minha-feature

# 2. Abra PR → main (CI roda testes)

# 3. Quando estiver pronto para produção
git checkout production
git merge main
git push origin production
```

O push em **`production`** sozinho dispara o deploy (~3–8 min).

### O que o GitHub faz automaticamente

1. `composer install --no-dev`
2. `npm ci` + `npm run vendor:sync`
3. Envia arquivos para `unio/` (exceto `.env.local`, uploads, cache)
4. No servidor: `doctrine:migrations:migrate`, `cache:warmup`
5. Copia `css/`, `js/`, `images/`, `vendor/` para `public_html/`

---

## Parte 6 — Conferir se deu certo

1. GitHub → **Actions** → workflow **Deploy Production** → verde
2. Site: https://uniowork.com.br/login
3. Se falhar, abra o job e leia o log do passo **Rsync** ou **Post-deploy**

---

## Problemas comuns

| Erro | Solução |
|------|---------|
| `Permission denied (publickey)` | Chave pública não autorizada no cPanel SSH |
| `DEPLOY_SSH_KEY` inválida | Cole a chave privada completa, com quebras de linha |
| `composer` / `php` no servidor | Não precisa — o build roda no GitHub; só migrations usam PHP no servidor |
| Site 500 após deploy | Confirme `.env.local` no servidor; rode cache warmup manual se necessário |
| CSS quebrado | Verifique se `public_html/css` foi atualizado (passo Post-deploy) |
| Migration falhou | Veja log do SSH; corrija DB e rode de novo com push vazio ou re-run workflow |

### Re-run manual no GitHub

Actions → **Deploy Production** → run com falha → **Re-run all jobs**

---

## Pipeline avançado (jul/2026)

| Recurso | O que faz |
|---------|-----------|
| **Smoke test** | Após deploy, `curl` em `/login`, `/termos`, `/privacidade` — workflow falha se ≠ 200 |
| **GitHub Release** | Cada deploy prod OK cria tag `deploy-N` com release notes |
| **CI noturno** | Cron 03:00 BRT — testes sem push |
| **paths-ignore** | PR só com `docs/` ou `*.md` não dispara CI |
| **Backup DB** | `mysqldump` em `var/backups/db/` antes de cada migration (últimos 7) |

Backups no servidor: `~/unio/var/backups/db/pre-migrate-*.sql.gz`

---

## Segurança

- Nunca commite `.env.local` ou chaves SSH no Git
- Rotacione `DEPLOY_SSH_KEY` se a chave vazar
- Use branch `production` só para código estável
- Apague scripts temporários do deploy manual (`install-once.php`, `fix-once.php`) em `public_html`

---

## Resumo

| Quando | O que fazer |
|--------|-------------|
| Setup inicial | SSH + chave + secrets GitHub + `.env.local` no servidor |
| Cada release | `git push origin production` |
| Upload manual | **Não precisa mais** (exceto emergência) |
