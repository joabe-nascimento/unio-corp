# Unio Jurídico — Acessos (produção)

Credenciais e URLs para gerenciamento da instância **uniojuridico** (jul/2026).

---

## URLs principais

| Ambiente | URL |
|----------|-----|
| **App produção** | https://uniojuridico.uniowork.com.br |
| Login | https://uniojuridico.uniowork.com.br/login |
| Admin | https://uniojuridico.uniowork.com.br/admin |
| Pulso (dashboard) | https://uniojuridico.uniowork.com.br/pulso |

---

## Credenciais default

### Login inicial (após seed/setup)

| Campo | Valor |
|-------|-------|
| E-mail | `admin@uniowork.com.br` |
| Senha | `unio123` (alterar no primeiro acesso) |

> **IMPORTANTE:** Troque a senha imediatamente após o primeiro login via `/admin/usuario`.

---

## Servidor (SSH/cPanel)

| Item | Valor |
|------|--------|
| **Host SSH** | `br1136.hostgator.com.br` (não o domínio público) |
| **Porta** | `2222` |
| **Usuário** | `joabef36` |
| **App path** | `/home2/joabef36/unio-uniojuridico` |
| **Document root** | `/home2/joabef36/uniojuridico.uniowork.com.br` |

### Conectar via SSH

```bash
ssh -p 2222 -i ~/.ssh/unio_deploy joabef36@br1136.hostgator.com.br
cd /home2/joabef36/unio-uniojuridico
```

---

## Banco de dados

| Item | Valor |
|------|--------|
| **Nome do banco** | `joabef36_unio_uniojuridico` |
| **Host** | `localhost` |
| **Porta** | `3306` |
| **Usuário** | (conforme provisionado no cPanel MySQL) |
| **Senha** | (conforme provisionado no cPanel MySQL) |

Acesso via **phpMyAdmin** no cPanel ou via SSH:

```bash
mysql -u USUARIO -p joabef36_unio_uniojuridico
```

---

## cPanel

| Item | Valor |
|------|--------|
| **URL** | https://br1136.hostgator.com.br:2083 |
| **Usuário** | `joabef36` |
| **Senha** | (conforme conta HostGator) |

### Ferramentas úteis

- **Subdomínios** → gerenciar `uniojuridico.uniowork.com.br`
- **MySQL Databases** → criar/gerenciar banco `joabef36_unio_uniojuridico`
- **File Manager** → `/home2/joabef36/unio-uniojuridico`
- **Terminal** → executar comandos Symfony
- **SSL/TLS Status** → verificar certificado HTTPS (AutoSSL/Let's Encrypt)

---

## IA Jurídica (JurisFlow AI Service)

| Item | Valor |
|------|--------|
| **Serviço Python** | JurisFlow AI Service (LangChain + RAG + Agents) |
| **URL dev** | http://127.0.0.1:8090 |
| **URL produção** | (a definir — hospedar em VM/Railway/Render) |
| **Vertical** | `legal` |
| **Assistente** | Bruna (assistente jurídica) |

### Subir localmente (dev)

```bash
cd "C:\projetos\projeto-unef\Nova pasta\JurisFlow-ai-service"
.venv\Scripts\activate
uvicorn app.main:app --reload --port 8090
```

Configure no `.env.local` da Unio Jurídico:

```bash
LEGAL_AI_ENABLED=true
LEGAL_AI_URL=http://127.0.0.1:8090
LEGAL_AI_ESCRITORIO_ID=default
```

---

## Monitoramento

### Health check

```bash
curl https://uniojuridico.uniowork.com.br/login -I
# Esperado: HTTP 200
```

### Logs de deploy

```bash
ssh -p 2222 -i ~/.ssh/unio_deploy joabef36@br1136.hostgator.com.br
cat /home2/joabef36/unio-uniojuridico/var/log/deploy-report.txt
```

### Erros Symfony

```bash
tail -f /home2/joabef36/unio-uniojuridico/var/log/prod.log
```

---

## Troubleshooting comum

| Problema | Solução |
|----------|---------|
| 404 em HTTPS após deploy | Ver [DNS_UNIOWORK_UNIOJURIDICO.md](DNS_UNIOWORK_UNIOJURIDICO.md) — sync public_html |
| 500 Internal Server Error | Verificar `var/log/prod.log` + `php bin/console cache:clear --env=prod` |
| Chat IA não responde | Verificar `LEGAL_AI_URL` + status do JurisFlow Service (`/health`) |
| DNS não propaga | Aguardar 24h ou editar `/etc/hosts` (Windows: `C:\Windows\System32\drivers\etc\hosts`) |

---

## Segurança

- **SSH:** Sempre use chave privada (não senha). Chave em `~/.ssh/unio_deploy`.
- **Banco:** Usuário MySQL com permissões restritas ao schema `joabef36_unio_uniojuridico`.
- **App:** `APP_DEBUG=0` em produção. Nunca commite `.env.local` (gitignore).
- **Senhas:** Altere senha default `unio123` no primeiro login.
- **HTTPS:** Certificado gerenciado pelo AutoSSL (Let's Encrypt) via cPanel.

---

## Documentos relacionados

- [DEPLOY_MANUAL_UNIOJURIDICO.md](DEPLOY_MANUAL_UNIOJURIDICO.md) — deploy manual passo a passo
- [DNS_UNIOWORK_UNIOJURIDICO.md](DNS_UNIOWORK_UNIOJURIDICO.md) — configuração subdomínio
- [UNIOJURIDICO_BANCO.md](UNIOJURIDICO_BANCO.md) — banco de dados e migrations
- [docs/uniojuridico/README.md](uniojuridico/README.md) — arquitetura e integração de IA
