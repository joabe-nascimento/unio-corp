# Wallet pass — credenciais (somente Unio Saúde / UnioClínica)

**Não usar no Uniowork** (`production` / uniowork.com.br). Esta pasta e as variáveis `WALLET_*` são exclusivas do deploy clínico (`uniosaude` / uniosaude.uniowork.com.br).

O bloqueio é automático: com `UNIO_ORGANISMO_BRAND_NAME=Unio Studio` (ou `UNIT_LABEL=Projeto`), rotas de carteirinha, comprovante, verificação, hub `/paciente`, wallet e módulos clínicos da landing retornam **404**.

Arquivos esperados (não commitar segredos reais):

| Arquivo | Descrição |
|---------|-----------|
| `AppleWWDRCAG4.pem` | Certificado intermediário Apple (público; já incluído) |
| `apple-pass.p12` | Certificado Pass Type ID exportado do Apple Developer |
| `google-service-account.json` | Conta de serviço com Google Wallet API habilitada |

Variáveis em `.env.local` do servidor **Unio Saúde**:

```
WALLET_APPLE_PASS_TYPE_ID=pass.br.com.uniosaude.carteirinha
WALLET_APPLE_TEAM_ID=XXXXXXXXXX
WALLET_APPLE_CERT_PATH=config/wallet/apple-pass.p12
WALLET_APPLE_CERT_PASSWORD=senha-do-p12
WALLET_GOOGLE_ISSUER_ID=3388000000000000000
WALLET_GOOGLE_SERVICE_ACCOUNT_PATH=config/wallet/google-service-account.json
WALLET_GOOGLE_ORIGINS=https://uniosaude.uniowork.com.br
```

Os botões de carteira digital só aparecem quando o perfil é **clínica** (`UNIO_ORGANISMO_BRAND_NAME` / `UNIO_ORGANISMO_UNIT_LABEL` de Unio Saúde) **e** as credenciais estão configuradas.
