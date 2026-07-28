# Sistema de Importação de Tokens Históricos da Azure OpenAI

## Visão Geral

Este sistema permite importar dados históricos de uso de tokens da Azure OpenAI para o Unio Jurídico, preenchendo o medidor visual com dados reais de consumo anterior.

## Arquitetura

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│  Azure Monitor  │ ──────> │ Symfony Command  │ ──────> │  JurisFlow AI   │
│      API        │  metrics│ (PHP)            │  HTTP   │   (Python)      │
│                 │         │  ImportAzure...  │         │ /v1/usage/import│
└─────────────────┘         └──────────────────┘         └─────────────────┘
                                                                    │
                                                                    v
                                                          ┌─────────────────┐
                                                          │ llm_usage.json  │
                                                          │  (persistência) │
                                                          └─────────────────┘
                                                                    │
                                                                    v
                                                          ┌─────────────────┐
                                                          │  UI do Unio     │
                                                          │ Token Meter     │
                                                          └─────────────────┘
```

## Componentes Criados

### 1. Comando Symfony
**Arquivo**: `src/Command/ImportAzureTokenUsageCommand.php`

- Consulta a Azure Monitoring API
- Obtém métricas de `TokenTransaction`
- Agrega dados por período (hoje/mês/lifetime)
- Envia para o JurisFlow AI via HTTP

### 2. Script PowerShell Automatizado
**Arquivo**: `scripts/import-azure-token-history.ps1`

- Login automático na Azure
- Lista recursos OpenAI disponíveis
- Obtém token de acesso
- Executa o comando Symfony

### 3. Endpoint de Importação (JurisFlow AI)
**Arquivo**: `docs/jurisflow-ai-usage-import-endpoint.py`

- Endpoint `POST /v1/usage/import`
- Mescla dados importados com existentes
- Persiste em `llm_usage.json`
- Endpoint `POST /v1/usage/reset` (dev/teste)

### 4. Documentação
**Arquivo**: `docs/azure-token-import-guide.md`

- Guia completo de uso
- Troubleshooting
- FAQs

## Como Usar

### Método 1: Script PowerShell (Recomendado)

```powershell
# Navegue até o projeto
cd "C:\projetos\Nova pasta\unio-corp\unio-corp"

# Execute o script
.\scripts\import-azure-token-history.ps1

# Com opções
.\scripts\import-azure-token-history.ps1 -Days 90        # Importar 90 dias
.\scripts\import-azure-token-history.ps1 -DryRun         # Apenas visualizar
```

### Método 2: Comando Symfony (Manual)

```bash
# 1. Obter Resource ID
az cognitiveservices account list --query "[?kind=='OpenAI'].{name:name,id:id}" -o table

# 2. Obter token
TOKEN=$(az account get-access-token --query accessToken -o tsv)

# 3. Importar
php bin/console app:import-azure-token-usage \
  --resource-id="/subscriptions/.../accounts/seu-openai" \
  --management-token="$TOKEN" \
  --days=30
```

## Instalação no Servidor (JurisFlow AI)

Para ativar o endpoint de importação no JurisFlow AI em produção:

```bash
# 1. Conectar ao servidor
ssh joabef36@br1136.hostgator.com.br -p 2222

# 2. Navegar para o JurisFlow AI
cd /home2/joabef36/unio-uniojuridico/jurisflow-ai-service

# 3. Criar diretório da API
mkdir -p app/api

# 4. Copiar o código do endpoint
cat > app/api/usage_import.py << 'EOF'
[Cole o conteúdo de docs/jurisflow-ai-usage-import-endpoint.py]
EOF

# 5. Atualizar main.py
# Adicione estas linhas no main.py:
#   from app.api.usage_import import router as usage_import_router
#   app.include_router(usage_import_router, prefix="/v1/usage", tags=["usage"])

# 6. Reiniciar o serviço
systemctl restart jurisflow-ai

# 7. Verificar se está funcionando
curl -X POST http://localhost:8090/v1/usage/import \
  -H "Content-Type: application/json" \
  -d '{"today":{"total_tokens":100,"requests":1},"month":{"total_tokens":100,"requests":1},"lifetime":{"total_tokens":100,"requests":1}}'
```

## Fluxo de Dados

1. **Azure Monitoring API** mantém métricas agregadas por hora (até 93 dias)
2. **Comando Symfony** consulta essas métricas e as agrega por período
3. **JurisFlow AI** recebe os dados e os mescla com o rastreamento atual
4. **UI do Unio** exibe os dados totais (histórico + atual)

## Métricas Importadas

- **TokenTransaction**: Total de tokens processados pela Azure OpenAI
- **Granularidade**: Agregado por hora (PT1H)
- **Retenção**: 93 dias na Azure
- **Separação**: Estimativa 50/50 para prompt/completion

## Notas Importantes

### Primeira Importação
- Execute com `--days=90` para importar todo o histórico disponível
- Pode levar 1-2 minutos para processar

### Execuções Subsequentes
- Não é necessário reimportar
- O rastreamento em tempo real já funciona
- Execute apenas se precisar corrigir dados

### Segurança
- Tokens Azure expiram em 1 hora
- Não commite tokens no Git
- Comando requer permissão "Monitoring Reader"

## Variáveis de Ambiente

Adicione ao `.env` do Symfony (opcional):

```env
# URL base do JurisFlow AI (para importação)
JURISFLOW_AI_BASE_URL=http://localhost:8090

# Em produção:
# JURISFLOW_AI_BASE_URL=https://uniojuridico.uniowork.com.br/jurisflow-ai
```

## Troubleshooting

### "Invalid token" ou "Token expired"
```bash
# Obter novo token
az account get-access-token --query accessToken -o tsv
```

### "Resource not found"
```bash
# Verificar Resource ID correto
az cognitiveservices account list --query "[?kind=='OpenAI'].id" -o tsv
```

### "Insufficient permissions"
```bash
# Adicionar permissão Monitoring Reader
az role assignment create \
  --role "Monitoring Reader" \
  --assignee <seu-email@domain.com> \
  --scope <resource-id>
```

### JurisFlow AI não acessível
```bash
# Verificar status do serviço
systemctl status jurisflow-ai

# Ver logs
journalctl -u jurisflow-ai -f
```

## Próximos Passos

Após a importação:

1. Acesse: https://uniojuridico.uniowork.com.br
2. Faça login como `joabe.nascimento@unio.dev`
3. Observe o medidor de tokens no header (canto superior direito)
4. Interaja com a Sasha para ver os tokens atualizarem em tempo real

## Desenvolvimento Futuro

Possíveis melhorias:

- [ ] Importação automática agendada (cron)
- [ ] Dashboard detalhado de custos por dia/semana
- [ ] Alertas quando ultrapassar limites
- [ ] Separação por modelo (gpt-4, gpt-3.5, etc.)
- [ ] Exportação de relatórios em PDF/Excel
- [ ] Integração com Azure Cost Management API para custo real em R$
