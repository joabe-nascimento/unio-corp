# Importação de Dados Históricos da Azure OpenAI

Este guia explica como importar métricas históricas de uso de tokens da Azure OpenAI para o sistema Unio Jurídico.

## Pré-requisitos

1. **Azure CLI** instalado: https://docs.microsoft.com/cli/azure/install-azure-cli
2. **Permissões** na Azure: Leitor de Monitoramento (Monitoring Reader) ou superior
3. **Resource ID** do seu Azure OpenAI

## Passo 1: Obter o Resource ID

```bash
# Login na Azure
az login

# Listar recursos Azure OpenAI
az cognitiveservices account list --query "[?kind=='OpenAI'].{name:name, resourceGroup:resourceGroup, id:id}" -o table

# Copie o ID completo (formato: /subscriptions/{sub-id}/resourceGroups/{rg}/providers/Microsoft.CognitiveServices/accounts/{name})
```

## Passo 2: Obter o Management Token

```bash
# Token de acesso do Azure Resource Manager (válido por 1 hora)
az account get-access-token --query accessToken -o tsv
```

## Passo 3: Executar a importação

```bash
# Importação dos últimos 30 dias (padrão)
php bin/console app:import-azure-token-usage \
  --resource-id="/subscriptions/xxx/resourceGroups/xxx/providers/Microsoft.CognitiveServices/accounts/xxx" \
  --management-token="eyJ0eXAiOiJKV1Q..." \
  --days=30

# Importação com mais dias (ex: 90 dias)
php bin/console app:import-azure-token-usage \
  --resource-id="/subscriptions/xxx/resourceGroups/xxx/providers/Microsoft.CognitiveServices/accounts/xxx" \
  --management-token="eyJ0eXAiOiJKV1Q..." \
  --days=90

# Modo dry-run (apenas visualizar, sem salvar)
php bin/console app:import-azure-token-usage \
  --resource-id="/subscriptions/xxx/resourceGroups/xxx/providers/Microsoft.CognitiveServices/accounts/xxx" \
  --management-token="eyJ0eXAiOiJKV1Q..." \
  --dry-run
```

## Passo 4: Verificar o resultado

Acesse o Unio Jurídico e verifique o medidor de tokens no header:
- https://uniojuridico.uniowork.com.br

Os dados históricos agora devem estar visíveis em:
- **Hoje**: tokens usados nas últimas 24h
- **Mês**: tokens usados no mês corrente
- **Lifetime**: total histórico importado

## Notas Importantes

### Limitações da Azure Monitoring API

- **Granularidade**: A Azure agrega métricas por hora (PT1H)
- **Retenção**: Métricas são mantidas por 93 dias
- **Separação**: A métrica `TokenTransaction` não separa prompt/completion, então usamos uma estimativa 50/50
- **Custo**: A Azure cobra por consultas de métricas (baixo custo)

### Frequência de importação

- **Primeira vez**: Importe todo o histórico disponível (--days=90)
- **Manutenção**: Execute semanalmente para preencher gaps
- **Produção**: O rastreamento em tempo real já está ativo

### Segurança

- **Tokens expiram em 1 hora**: Obtenha um novo token para cada importação
- **Não commite tokens**: Tokens de acesso são sensíveis
- **Auditoria**: A Azure registra todas as consultas de métricas

## Troubleshooting

### Erro: "Invalid token"
```bash
# Obter novo token
az account get-access-token --query accessToken -o tsv
```

### Erro: "Resource not found"
```bash
# Verificar Resource ID
az cognitiveservices account show -n {account-name} -g {resource-group} --query id -o tsv
```

### Erro: "Insufficient permissions"
```bash
# Verificar permissões
az role assignment list --assignee $(az account show --query user.name -o tsv) --query "[].roleDefinitionName" -o table
```

Você precisa ter pelo menos: **Monitoring Reader** ou **Contributor**

## Alternativa: Script PowerShell automatizado

Para facilitar, também fornecemos um script PowerShell que faz tudo automaticamente:

```powershell
.\scripts\import-azure-token-history.ps1
```

Este script:
1. Faz login na Azure
2. Lista recursos OpenAI disponíveis
3. Obtém o token automaticamente
4. Executa a importação
5. Mostra o resultado

## Perguntas Frequentes

**Q: Os dados históricos vão sobrescrever os atuais?**
A: Não, o sistema mescla dados históricos com os atuais de forma inteligente.

**Q: Preciso importar toda vez?**
A: Não, apenas uma vez para popular o histórico. Depois disso, o rastreamento em tempo real já funciona.

**Q: Posso importar dados de múltiplos recursos?**
A: Sim, execute o comando uma vez para cada Resource ID.

**Q: Quanto tempo demora?**
A: Geralmente menos de 1 minuto para 90 dias de histórico.
