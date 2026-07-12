# Unio Saúde — reparo HTTP 500 (.env.local)

## Causa (11/07/2026)

Após o deploy `6c12252`, linhas foram adicionadas ao `.env.local` **sem aspas** em valores com espaço:

```env
UNIO_ORGANISMO_BRAND_NAME=Unio Saúde   # inválido
UNIO_ORGANISMO_UNIT_LABEL=Clínica      # inválido
```

O Symfony Dotenv exige aspas → **HTTP 500** em todas as páginas.

Erro típico no log:

```text
FormatException: A value containing spaces must be surrounded by quotes in ".env.local"
```

---

## Reparo imediato — Terminal do cPanel

**Não use SSH externo** se estiver com `Connection refused`. Use:

**cPanel → Terminal** (conta `joabef36`)

Cole e execute:

```bash
cd /home2/joabef36/unio-uniosaude

# Opção A — remover linhas problemáticas (defaults YAML assumem Unio Saúde)
sed -i '/^UNIO_ORGANISMO_BRAND_NAME=/d' .env.local
sed -i '/^UNIO_ORGANISMO_UNIT_LABEL=/d' .env.local

# Opção B — corrigir com aspas (alternativa à opção A)
# sed -i 's/^UNIO_ORGANISMO_BRAND_NAME=.*/UNIO_ORGANISMO_BRAND_NAME="Unio Saúde"/' .env.local
# sed -i 's/^UNIO_ORGANISMO_UNIT_LABEL=.*/UNIO_ORGANISMO_UNIT_LABEL="Clínica"/' .env.local

php bin/console cache:clear --env=prod --no-debug

# Validar
curl -s -o /dev/null -w "login: %{http_code}\n" https://uniosaude.uniowork.com.br/login
```

Esperado: `login: 200` ou `login: 302`.

---

## Depois do site voltar

```bash
cd /home2/joabef36/unio-uniosaude

# Sandbox demo (Ana / João / Maria)
php bin/console app:clinic:sandbox-seed --allow-prod --no-interaction

# WALLET — só ativa botões quando houver certificados reais:
#   config/wallet/apple-pass.p12
#   config/wallet/google-service-account.json
grep WALLET .env.local
ls -la config/wallet/
```

Se `apple-pass.p12` e `google-service-account.json` **não existirem**, configure `WALLET_APPLE_TEAM_ID`, senha do `.p12` e `WALLET_GOOGLE_ISSUER_ID` quando tiver as credenciais Apple/Google.

---

## Script automático (após próximo deploy)

```bash
bash /home2/joabef36/unio-uniosaude/scripts/lib/repair-env-local-500.sh
```

---

## Prevenção

Sempre use aspas em `.env.local` para valores com espaço. Modelo: `.env.uniosaude.example`.
