# DNS — uniowork.com.br e clinicaunio

## Diagnostico (jul/2026)

| Consulta | Resultado |
|----------|-----------|
| `nslookup clinicaunio.uniowork.com.br ns1136.hostgator.com.br` | **50.6.138.130** (OK na HostGator) |
| `nslookup clinicaunio.uniowork.com.br` (DNS publico) | **NXDOMAIN** |
| Registro.br (RDAP) — NS do dominio | `hadlee.ns.cloudflare.com`, `shane.ns.cloudflare.com` |

A Zona DNS do **cPanel** tem o subdominio; a internet consulta os **nameservers do Registro.br** (Cloudflare), que nao incluem `clinicaunio`.

## Correcao definitiva (HostGator)

Altere os nameservers de `uniowork.com.br` para os da HostGator:

1. **Registro.br** → login → **Meus dominios** → `uniowork.com.br` → **Alterar servidores DNS**
2. Ou **Portal HostGator** → **Dominios** → `uniowork.com.br` → **Servidores DNS** (se o dominio estiver na conta)
3. Use **somente** estes dois:
   - `ns1136.hostgator.com.br`
   - `ns1137.hostgator.com.br`
4. Salve e aguarde propagacao (15 min – 4 h)
5. Confirme: `nslookup clinicaunio.uniowork.com.br` deve retornar `50.6.138.130`

**Atencao:** ao trocar NS, e-mail (Titan), site principal e outros subdominios passam a usar **apenas** a Zona DNS do cPanel. Confira no cPanel → **Zona DNS** se `uniowork.com.br`, `www`, MX e registros de e-mail estao corretos antes de propagar.

## Teste imediato (so no seu PC)

Como administrador:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\clinicaunio-hosts-local.ps1
```

Depois abra https://clinicaunio.uniowork.com.br/login

## Servidor (ja configurado)

| Item | Valor |
|------|--------|
| Subdominio cPanel | `clinicaunio.uniowork.com.br` |
| Document root | `/home2/joabef36/clinicaunio.uniowork.com.br` |
| App | `/home2/joabef36/unio-clinicaunio` |
| IP (registro A) | `50.6.138.130` |
