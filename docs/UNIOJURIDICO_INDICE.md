# Unio Jurídico — Índice de documentação

Documentação completa da plataforma Unio Jurídico (branch `uniojuridico`).

---

## 📋 Início rápido

| Para | Documento | Descrição |
|------|-----------|-----------|
| **Provisionar pela primeira vez** | [UNIOJURIDICO_PROVISIONAR.md](UNIOJURIDICO_PROVISIONAR.md) | ✅ Checklist completo: subdomínio, banco, SSL, primeiro deploy |
| **Deploy manual (PC → servidor)** | [DEPLOY_MANUAL_UNIOJURIDICO.md](DEPLOY_MANUAL_UNIOJURIDICO.md) | Script `deploy-uniojuridico-manual.ps1` |
| **Credenciais e acessos** | [UNIOJURIDICO_ACESSOS.md](UNIOJURIDICO_ACESSOS.md) | URLs, logins, SSH, cPanel, banco de dados |

---

## 🏗️ Arquitetura e IA

| Documento | Descrição |
|-----------|-----------|
| [docs/uniojuridico/README.md](uniojuridico/README.md) | Arquitetura, identidade visual, integração com JurisFlow AI Service |

---

## 🗄️ Banco de dados

| Documento | Descrição |
|-----------|-----------|
| [UNIOJURIDICO_BANCO.md](UNIOJURIDICO_BANCO.md) | MySQL, migrations, backup, tabelas |

---

## 🌐 DNS e infraestrutura

| Documento | Descrição |
|-----------|-----------|
| [DNS_UNIOWORK_UNIOJURIDICO.md](DNS_UNIOWORK_UNIOJURIDICO.md) | Configuração subdomínio, DNS, nameservers |

---

## 🔧 Troubleshooting

| Documento | Descrição |
|-----------|-----------|
| [UNIOJURIDICO_DEPLOY_REPAIR.md](UNIOJURIDICO_DEPLOY_REPAIR.md) | Scripts de reparo: 404, cache, vhost, SSL |

---

## 🚀 Fluxo típico (desenvolvimento → produção)

### 1. Desenvolvimento local

```bash
cd C:\projetos\Nova pasta\unio-corp\unio-corp
git checkout uniojuridico
git pull origin uniojuridico

# Alterar código...
# Testar localmente com symfony server:start ou php -S

git add .
git commit -m "feat: nova funcionalidade"
git push origin uniojuridico
```

### 2. Deploy em produção

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniojuridico-manual.ps1
```

### 3. Verificar

```bash
# Smoke test
Invoke-WebRequest -Uri "https://uniojuridico.uniowork.com.br/login" -Method Head
```

---

## 📦 Estrutura de arquivos (servidor)

```
/home2/joabef36/
├── unio-uniojuridico/               # App Symfony
│   ├── .env.local                   # Config produção (não versionar)
│   ├── bin/console                  # CLI Symfony
│   ├── var/log/prod.log             # Logs de erro
│   ├── var/cache/prod/              # Cache Symfony
│   └── var/backups/db/              # Backups automáticos MySQL
│
└── uniojuridico.uniowork.com.br/    # Document root (Apache)
    ├── index.php                    # Entry point Symfony
    ├── .htaccess                    # Rewrite rules
    └── build/                       # Assets compilados
```

---

## 🔑 Variáveis de ambiente importantes

No `.env.local` do servidor (`/home2/joabef36/unio-uniojuridico/.env.local`):

```bash
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=...
DEFAULT_URI=https://uniojuridico.uniowork.com.br
DATABASE_URL="mysql://..."

# Identidade Unio Jurídico
UNIO_ORGANISMO_ENABLED=true
UNIO_ORGANISMO_BRAND_NAME="Unio Jurídico"
UNIO_ORGANISMO_BRAND_SLOGAN="Justiça que acompanha."

# IA Jurídica (JurisFlow AI Service)
LEGAL_AI_ENABLED=true
LEGAL_AI_URL=http://127.0.0.1:8090  # ou URL pública se em outro servidor
LEGAL_AI_ESCRITORIO_ID=default
```

---

## 🤖 IA Jurídica (Bruna)

O chat da Vitória/Lumen automaticamente troca para a IA jurídica (**Bruna**, assistente jurídica) quando a identidade da plataforma é "Unio Jurídico".

**Funcionalidades:**
- ✅ Calcular prazos processuais
- ✅ Pesquisar jurisprudência
- ✅ Resumir documentos/petições
- ✅ Analisar contratos
- ✅ Calcular honorários advocatícios (tabela OAB)
- ✅ RAG multi-tenant (base de conhecimento por escritório)

**Dependência:** Requer o **JurisFlow AI Service** (Python/FastAPI) rodando — ver [docs/uniojuridico/README.md](uniojuridico/README.md).

---

## 📞 Suporte

| Canal | Info |
|-------|------|
| **Servidor HostGator** | Abrir ticket no cPanel |
| **SSL/DNS** | Verificar Cloudflare ou Registro.br |
| **Código (bugs)** | GitHub Issues ou contato direto |

---

## 🎨 Identidade visual

| Item | Valor |
|------|--------|
| **Nome** | Unio Jurídico |
| **Slogan** | Justiça que acompanha. |
| **Cor primária** | `#9C2C3C` (bordô) |
| **Cor secundária** | `#B8892B` (dourado) |
| **Logo** | `/public/images/logos/unio-juridico.png` |
| **Favicon** | `/public/images/logos/favicon-unio-juridico.png` |

---

## 📚 Links úteis

| Recurso | URL |
|---------|-----|
| **App produção** | https://uniojuridico.uniowork.com.br |
| **Login** | https://uniojuridico.uniowork.com.br/login |
| **Admin** | https://uniojuridico.uniowork.com.br/admin |
| **cPanel** | https://br1136.hostgator.com.br:2083 |
| **Repositório** | https://github.com/joabe-nascimento/unio-corp (branch `uniojuridico`) |
