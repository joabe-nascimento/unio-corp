# Checklist — Provisionar Unio Jurídico no HostGator (primeira vez)

Guia passo a passo para configurar o subdomínio **uniojuridico.uniowork.com.br** e banco de dados no cPanel da HostGator.

---

## Pré-requisitos

- Acesso ao cPanel: https://br1136.hostgator.com.br:2083 (usuário: `joabef36`)
- Domínio `uniowork.com.br` já configurado com nameservers da HostGator
- Chave SSH para deploy (`~/.ssh/unio_deploy`)

---

## Parte 1: Criar subdomínio (cPanel)

### 1. Acessar gerenciador de subdomínios

1. Login no cPanel
2. Buscar: **"Subdomínios"** ou **"Subdomains"**
3. Clicar no ícone

### 2. Criar novo subdomínio

| Campo | Valor |
|-------|-------|
| **Subdomínio** | `uniojuridico` |
| **Domínio** | `uniowork.com.br` (selecionar no dropdown) |
| **Document Root** | `/home2/joabef36/uniojuridico.uniowork.com.br` |

> O cPanel pode sugerir outro path — **sobrescrever** com o path acima para manter padrão.

3. Clicar em **"Criar"** ou **"Create"**
4. Aguardar confirmação: "Subdomínio `uniojuridico.uniowork.com.br` foi criado com sucesso"

### 3. Verificar criação

No File Manager, confirmar que a pasta foi criada:

```
/home2/joabef36/uniojuridico.uniowork.com.br/
```

Deve estar vazia ou com um `index.html` placeholder.

---

## Parte 2: Criar banco de dados MySQL

### 1. Acessar MySQL Databases

1. No cPanel, buscar: **"MySQL® Databases"**
2. Clicar no ícone

### 2. Criar novo banco

Na seção **"Create New Database"**:

| Campo | Valor |
|-------|-------|
| **New Database** | `unio_uniojuridico` |

> O cPanel adicionará prefixo automaticamente: `joabef36_unio_uniojuridico`

Clicar em **"Create Database"**

### 3. Criar usuário MySQL

Na seção **"Add New User"**:

| Campo | Valor | Observação |
|-------|-------|------------|
| **Username** | `uniojuridico_rw` | Nome sugerido (cPanel adicionará prefixo) |
| **Password** | (gerar senha forte) | Salvar em local seguro (ex: 1Password) |

Clicar em **"Create User"**

### 4. Associar usuário ao banco

Na seção **"Add User to Database"**:

| Campo | Valor |
|-------|-------|
| **User** | `joabef36_uniojuridico_rw` |
| **Database** | `joabef36_unio_uniojuridico` |

Clicar em **"Add"**

Na tela de privilégios, selecionar:
- ✅ **ALL PRIVILEGES** (ou marcar individualmente: SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER)

Clicar em **"Make Changes"**

### 5. Anotar credenciais

Salvar em local seguro:

```env
DATABASE_URL="mysql://joabef36_uniojuridico_rw:SENHA_GERADA@localhost:3306/joabef36_unio_uniojuridico?serverVersion=5.7.44&charset=utf8mb4"
```

---

## Parte 3: Configurar SSL/HTTPS (AutoSSL)

### 1. Verificar AutoSSL

1. No cPanel, buscar: **"SSL/TLS Status"**
2. Localizar linha: `uniojuridico.uniowork.com.br`
3. Verificar status:
   - ✅ **"AutoSSL certificate issued"** (OK, certificado ativo)
   - ⚠️ **"Pending"** ou **"Not issued"** (aguardar propagação DNS)

### 2. Se SSL não for emitido automaticamente

**Causa comum:** DNS ainda não propagou ou subdomínio criado recentemente.

**Solução:**
1. Aguardar 1-2 horas após criar o subdomínio
2. Verificar DNS: `nslookup uniojuridico.uniowork.com.br` deve retornar `50.6.138.130`
3. Se DNS estiver correto e SSL não emitir, clicar em **"Run AutoSSL"** manualmente

### 3. Testar HTTPS

```bash
curl -I https://uniojuridico.uniowork.com.br
```

Esperado: **HTTP 200** (ou 403/404 é OK — significa que HTTPS funciona, só falta o código da app).

---

## Parte 4: Enviar código inicial (primeiro deploy)

### 1. Criar `.env.local` no servidor

Via **File Manager** ou **Terminal do cPanel**, criar:

```
/home2/joabef36/unio-uniojuridico/.env.local
```

Conteúdo (copiar de `.env.uniojuridico.example` e ajustar):

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=GERAR_STRING_ALEATORIA_64_CHARS

DEFAULT_URI=https://uniojuridico.uniowork.com.br

DATABASE_URL="mysql://joabef36_uniojuridico_rw:SENHA_DO_PASSO_2@localhost:3306/joabef36_unio_uniojuridico?serverVersion=5.7.44&charset=utf8mb4"

# ... (copiar demais variáveis de .env.uniojuridico.example)
```

### 2. Executar primeiro deploy do PC

```powershell
cd C:\projetos\Nova pasta\unio-corp\unio-corp
git checkout uniojuridico
git pull origin uniojuridico

powershell -ExecutionPolicy Bypass -File scripts\deploy-uniojuridico-manual.ps1
```

Isso enviará o código, rodará migrations e sincronizará o `public_html`.

### 3. Verificar deploy

```powershell
# Smoke test
Invoke-WebRequest -Uri "https://uniojuridico.uniowork.com.br/login" -Method Head
# Esperado: StatusCode: 200
```

---

## Parte 5: Setup inicial da aplicação

### 1. Criar usuário admin

Via Terminal do cPanel ou SSH:

```bash
cd /home2/joabef36/unio-uniojuridico
php bin/console app:user:seed
```

Isso criará o usuário padrão:
- **E-mail:** `admin@uniowork.com.br`
- **Senha:** `unio123`

### 2. Primeiro login

1. Abrir: https://uniojuridico.uniowork.com.br/login
2. Login com credenciais acima
3. Ir em `/admin/usuario` e **alterar senha**

### 3. Configurar branding (opcional)

Em `/admin/configuracoes`, ajustar:
- Logo (ou usar o padrão Unio Jurídico já configurado)
- Cor primária (padrão: `#9C2C3C` bordô)
- Tagline (padrão: "Justiça que acompanha.")

---

## Checklist final

| Item | Status | Como verificar |
|------|--------|----------------|
| ✅ Subdomínio criado | | cPanel → Subdomínios |
| ✅ Banco de dados criado | | cPanel → MySQL Databases |
| ✅ Usuário MySQL associado | | cPanel → MySQL Databases |
| ✅ SSL/HTTPS funcionando | | `curl -I https://uniojuridico.uniowork.com.br` |
| ✅ `.env.local` configurado | | Terminal/SSH: `cat ~/unio-uniojuridico/.env.local` |
| ✅ Código enviado (primeiro deploy) | | Script `deploy-uniojuridico-manual.ps1` |
| ✅ Migrations aplicadas | | `php bin/console doctrine:migrations:status` |
| ✅ Login funcionando | | https://uniojuridico.uniowork.com.br/login |
| ✅ Senha admin alterada | | Login + `/admin/usuario` |

---

## Troubleshooting

### DNS não resolve

**Problema:** `nslookup uniojuridico.uniowork.com.br` não retorna `50.6.138.130`

**Solução:**
1. Aguardar propagação (até 24h)
2. Verificar nameservers em Registro.br: devem ser `ns1136.hostgator.com.br` / `ns1137.hostgator.com.br`
3. Temporário: editar `C:\Windows\System32\drivers\etc\hosts` e adicionar: `50.6.138.130 uniojuridico.uniowork.com.br`

### 404 Not Found após deploy

**Problema:** Site retorna 404 em todas as URLs

**Solução:** Ver [UNIOJURIDICO_DEPLOY_REPAIR.md](UNIOJURIDICO_DEPLOY_REPAIR.md)

```bash
# No Terminal do cPanel:
export DEPLOY_PATH=/home2/joabef36/unio-uniojuridico
export PUBLIC_HTML=/home2/joabef36/uniojuridico.uniowork.com.br
bash "$DEPLOY_PATH/scripts/lib/sync-public-html-entrypoint.sh"
```

### 500 Internal Server Error

**Problema:** Site retorna erro 500

**Solução:**
1. Verificar logs: `tail -f /home2/joabef36/unio-uniojuridico/var/log/prod.log`
2. Limpar cache: `php bin/console cache:clear --env=prod`
3. Verificar `.env.local` (DATABASE_URL, APP_SECRET)

---

## Próximos passos

1. ✅ Subdomínio e banco provisionados
2. ✅ Primeiro deploy realizado
3. ✅ Login funcionando
4. 🔲 Subir JurisFlow AI Service (IA jurídica) — ver [docs/uniojuridico/README.md](uniojuridico/README.md)
5. 🔲 Configurar `LEGAL_AI_URL` no `.env.local`
6. 🔲 Testar chat da Sasha no Pulso
7. 🔲 Cadastrar primeiro escritório/cliente

---

## Documentos relacionados

- [DNS_UNIOWORK_UNIOJURIDICO.md](DNS_UNIOWORK_UNIOJURIDICO.md) — configuração DNS e subdomínio
- [DEPLOY_MANUAL_UNIOJURIDICO.md](DEPLOY_MANUAL_UNIOJURIDICO.md) — deploy manual passo a passo
- [UNIOJURIDICO_BANCO.md](UNIOJURIDICO_BANCO.md) — banco de dados e migrations
- [UNIOJURIDICO_ACESSOS.md](UNIOJURIDICO_ACESSOS.md) — credenciais e URLs
- [docs/uniojuridico/README.md](uniojuridico/README.md) — arquitetura e integração de IA
