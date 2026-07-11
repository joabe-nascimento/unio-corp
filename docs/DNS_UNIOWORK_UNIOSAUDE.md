# DNS — uniowork.com.br e uniosaude

## Diagnóstico rápido

| Comando | Resultado esperado |
|---------|-------------------|
| `nslookup uniosaude.uniowork.com.br ns1136.hostgator.com.br` | **50.6.138.130** (OK na HostGator) |
| `nslookup uniosaude.uniowork.com.br` (DNS público) | **50.6.138.130** após propagação |

## Subdomínio

1. No cPanel: **Subdomínios** → criar `uniosaude` em `uniowork.com.br`
2. Document root: `/home2/joabef36/uniosaude.uniowork.com.br`
3. No Registro.br: garantir nameservers apontando para HostGator (`ns1136` / `ns1137`)
4. Adicionar registro **A** `uniosaude` → `50.6.138.130` na zona DNS pública (se usar Cloudflare/Registro.br)
5. Confirme: `nslookup uniosaude.uniowork.com.br` deve retornar `50.6.138.130`

## DNS local (Windows, enquanto propaga)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\uniosaude-hosts-local.ps1
```

Depois abra https://uniosaude.uniowork.com.br/login

## Servidor

| Item | Caminho |
|------|---------|
| Subdomínio cPanel | `uniosaude.uniowork.com.br` |
| Document root | `/home2/joabef36/uniosaude.uniowork.com.br` |
| App Symfony | `/home2/joabef36/unio-uniosaude` |

## Setup inicial

Workflow **Setup Unio Saúde (server)** no GitHub Actions, ou no Terminal do cPanel (sem SSH).

Guia completo (reparo 404 HTTPS, billing, smoke): **[UNIOSAUDE_DEPLOY_REPAIR.md](UNIOSAUDE_DEPLOY_REPAIR.md)**

```bash
export DEPLOY_PATH=/home2/joabef36/unio-uniosaude
export PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
export DEFAULT_URI=https://uniosaude.uniowork.com.br
bash "$DEPLOY_PATH/scripts/repair-uniosaude-now.sh"
```
