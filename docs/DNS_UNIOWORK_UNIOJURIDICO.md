# DNS — uniowork.com.br e uniojuridico

## Diagnóstico rápido

| Comando | Resultado esperado |
|---------|-------------------|
| `nslookup uniojuridico.uniowork.com.br ns1136.hostgator.com.br` | **50.6.138.130** (OK na HostGator) |
| `nslookup uniojuridico.uniowork.com.br` (DNS público) | **50.6.138.130** após propagação |

## Subdomínio

1. No cPanel: **Subdomínios** → criar `uniojuridico` em `uniowork.com.br`
2. Document root: `/home2/joabef36/uniojuridico.uniowork.com.br`
3. No Registro.br: garantir nameservers apontando para HostGator (`ns1136` / `ns1137`)
4. Adicionar registro **A** `uniojuridico` → `50.6.138.130` na zona DNS pública (se usar Cloudflare/Registro.br)
5. Confirme: `nslookup uniojuridico.uniowork.com.br` deve retornar `50.6.138.130`

## DNS local (Windows, enquanto propaga)

Edite `C:\Windows\System32\drivers\etc\hosts` (requer admin):

```
50.6.138.130    uniojuridico.uniowork.com.br
```

Depois abra https://uniojuridico.uniowork.com.br/login

## Servidor

| Item | Caminho |
|------|---------|
| Subdomínio cPanel | `uniojuridico.uniowork.com.br` |
| Document root | `/home2/joabef36/uniojuridico.uniowork.com.br` |
| App Symfony | `/home2/joabef36/unio-uniojuridico` |

## Setup inicial

Workflow **Setup Unio Jurídico (server)** no GitHub Actions, ou no Terminal do cPanel (sem SSH).

Guia completo (reparo 404 HTTPS, billing, smoke): **[UNIOJURIDICO_DEPLOY_REPAIR.md](UNIOJURIDICO_DEPLOY_REPAIR.md)**

```bash
export DEPLOY_PATH=/home2/joabef36/unio-uniojuridico
export PUBLIC_HTML=/home2/joabef36/uniojuridico.uniowork.com.br
export DEFAULT_URI=https://uniojuridico.uniowork.com.br
bash "$DEPLOY_PATH/scripts/lib/sync-public-html-entrypoint.sh"
bash "$DEPLOY_PATH/scripts/lib/repair-subdomain-vhost.sh"
```
