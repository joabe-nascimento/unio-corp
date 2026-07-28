# Unio Jurídico — Setup inicial (servidor)

Script de reparo/setup para configurar o subdomínio **uniojuridico.uniowork.com.br** após o provisionamento inicial no cPanel.

---

## Quando usar

- **Primeira vez** após criar o subdomínio no cPanel
- Após deploy que resultou em **404** ou **403** no HTTPS
- Quando o `public_html` está desatualizado ou vazio
- Após mudança de domínio/paths no servidor

---

## Pré-requisitos

1. Subdomínio criado no cPanel: `uniojuridico.uniowork.com.br`
2. Document root: `/home2/joabef36/uniojuridico.uniowork.com.br`
3. App Symfony em: `/home2/joabef36/unio-uniojuridico`
4. Banco de dados MySQL criado: `joabef36_unio_uniojuridico`
5. `.env.local` configurado no servidor com `DATABASE_URL`, `APP_SECRET`, etc.

---

## Método 1: Terminal cPanel (recomendado)

No **Terminal do cPanel** (sem SSH do PC):

```bash
# Definir variáveis de ambiente
export DEPLOY_PATH=/home2/joabef36/unio-uniojuridico
export PUBLIC_HTML=/home2/joabef36/uniojuridico.uniowork.com.br
export DEFAULT_URI=https://uniojuridico.uniowork.com.br

# Sincronizar public_html (index.php, .htaccess)
bash "$DEPLOY_PATH/scripts/lib/sync-public-html-entrypoint.sh"

# Reparar vhost Apache (se 404 persistir)
bash "$DEPLOY_PATH/scripts/lib/repair-subdomain-vhost.sh"

# Limpar cache Symfony
cd "$DEPLOY_PATH"
php bin/console cache:clear --env=prod --no-debug

# Rodar migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Smoke test
curl -I "$DEFAULT_URI/login"
# Esperado: HTTP/1.1 200 OK
```

---

## Método 2: SSH do PC

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br
```

Depois execute os mesmos comandos do Método 1.

---

## Checklist pós-setup

| Item | Verificação | Comando |
|------|-------------|---------|
| ✅ Subdomínio resolve DNS | `50.6.138.130` | `nslookup uniojuridico.uniowork.com.br` |
| ✅ HTTPS acessível | HTTP 200 | `curl -I https://uniojuridico.uniowork.com.br/login` |
| ✅ Migrations aplicadas | Current: Version... | `php bin/console doctrine:migrations:status` |
| ✅ Cache limpo | Sem erros | `php bin/console cache:clear --env=prod` |
| ✅ Assets sincronizados | Arquivos presentes | `ls /home2/joabef36/uniojuridico.uniowork.com.br` |
| ✅ Certificado SSL | Válido (Let's Encrypt) | Abrir no navegador |

---

## Troubleshooting

### 404 Not Found após sync

**Causa:** Apache não reconhece o subdomínio ou está cacheado.

**Solução:**

```bash
bash "$DEPLOY_PATH/scripts/lib/repair-subdomain-vhost.sh"
```

Se persistir, abra ticket no suporte HostGator para verificar vhost Apache.

### 500 Internal Server Error

**Causa:** Erro no código, cache corrompido ou `.env.local` inválido.

**Solução:**

```bash
tail -f "$DEPLOY_PATH/var/log/prod.log"
php bin/console cache:clear --env=prod --no-debug
```

### IA Jurídica não responde

**Causa:** `LEGAL_AI_URL` incorreto ou JurisFlow Service offline.

**Solução:**

```bash
# No .env.local, verificar:
cat "$DEPLOY_PATH/.env.local" | grep LEGAL_AI

# Testar conexão com o serviço Python:
curl -I http://127.0.0.1:8090/health
# Esperado: HTTP 200
```

Se o serviço estiver em outro servidor, ajuste `LEGAL_AI_URL` para a URL pública (HTTPS).

---

## Próximos passos

1. Fazer login em https://uniojuridico.uniowork.com.br/login
2. Alterar senha default (`unio123`) em `/admin/usuario`
3. Configurar branding (logo, cores) em `/admin/configuracoes`
4. Subir o JurisFlow AI Service e configurar `LEGAL_AI_URL` no `.env.local`
5. Testar chat da IA (Sasha) no Pulso

---

## Documentos relacionados

- [DEPLOY_MANUAL_UNIOJURIDICO.md](DEPLOY_MANUAL_UNIOJURIDICO.md) — deploy manual passo a passo
- [DNS_UNIOWORK_UNIOJURIDICO.md](DNS_UNIOWORK_UNIOJURIDICO.md) — configuração subdomínio e DNS
- [UNIOJURIDICO_BANCO.md](UNIOJURIDICO_BANCO.md) — banco de dados e migrations
- [UNIOJURIDICO_ACESSOS.md](UNIOJURIDICO_ACESSOS.md) — credenciais e URLs
