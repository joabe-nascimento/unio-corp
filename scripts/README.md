# Scripts de Qualidade e Deploy

Esta pasta contém scripts utilitários para garantir a qualidade do código e facilitar o deploy.

## 🛡️ Scripts de Qualidade

### `remove-bom.ps1`
Remove BOM (Byte Order Mark) de arquivos críticos que podem causar erros de parsing.

**Uso:**
```powershell
# Verificar BOM sem modificar arquivos
.\scripts\remove-bom.ps1 -CheckOnly

# Remover BOM automaticamente
.\scripts\remove-bom.ps1

# Modo verbose (mostra todos os arquivos verificados)
.\scripts\remove-bom.ps1 -Verbose
```

**Arquivos verificados:**
- `.env`, `.env.*`
- `*.php`, `*.yaml`, `*.yml`
- `*.twig`, `*.js`, `*.json`
- `*.xml`, `*.md`, `*.sh`

### `pre-deploy-check.ps1`
Executa verificações completas antes do deploy.

**Uso:**
```powershell
# Apenas verificar
.\scripts\pre-deploy-check.ps1

# Verificar e corrigir automaticamente
.\scripts\pre-deploy-check.ps1 -Fix
```

**Verificações:**
1. ✅ BOM em arquivos críticos
2. ✅ Nomes de arquivos problemáticos
3. ✅ PSR-4 compliance (classes renomeadas)
4. ✅ Variáveis necessárias no `.env`

## 🪝 Git Hooks

### Instalação
```powershell
.\scripts\install-git-hooks.ps1
```

### Hooks disponíveis

#### `pre-commit`
Executa automaticamente antes de cada commit:
- Verifica BOM em arquivos staged
- Impede commit se BOM for encontrado
- Fornece comando para correção

**Bypass (não recomendado):**
```bash
git commit --no-verify
```

## 🚀 Scripts de Deploy

### `deploy-uniojuridico-manual.ps1`
Deploy completo para produção (HostGator).

**Uso:**
```powershell
.\scripts\deploy-uniojuridico-manual.ps1
```

**Etapas:**
1. [0/5] Verificação e remoção de BOM
2. [1/5] `composer install --no-dev --optimize-autoloader`
3. [2/5] `npm ci` + sync vendor + minify CSS
4. [3/5] Empacotar e enviar tarball via SCP
5. [4/5] Extrair no servidor + `deploy-server.sh`

## 📋 Integração no Workflow

### Antes de commitar
O hook `pre-commit` roda automaticamente, mas você pode executar manualmente:
```powershell
.\scripts\remove-bom.ps1 -CheckOnly
```

### Antes de fazer push
```powershell
.\scripts\pre-deploy-check.ps1
```

### Antes de deploy em produção
O script `deploy-uniojuridico-manual.ps1` já executa `remove-bom.ps1` automaticamente.

## 🔧 Troubleshooting

### BOM encontrado após commit
```powershell
# Corrigir
.\scripts\remove-bom.ps1

# Adicionar correção ao commit anterior (se ainda não foi pushed)
git add -u
git commit --amend --no-edit
```

### Deploy falhou por BOM no servidor
```bash
# SSH no servidor
ssh -p 2222 joabef36@br1136.hostgator.com.br

# Remover BOM de arquivo específico
cd /home2/joabef36/unio-uniojuridico
sed -i '1s/^\xEF\xBB\xBF//' .env

# Limpar cache
php bin/console cache:clear --env=prod
```

## 🎯 Boas Práticas

1. ✅ **Sempre** rode `pre-deploy-check.ps1` antes de fazer deploy
2. ✅ Configure seu editor para salvar arquivos como **UTF-8 sem BOM**
3. ✅ Use os hooks git instalados (não faça `--no-verify` sem necessidade)
4. ✅ Em caso de erro de BOM, corrija localmente antes de fazer novo deploy

### Configuração de Editores

**VS Code** (`.vscode/settings.json`):
```json
{
  "files.encoding": "utf8",
  "files.autoGuessEncoding": false
}
```

**PhpStorm / IntelliJ**:
- File → Settings → Editor → File Encodings
- Project Encoding: UTF-8
- ☐ Create UTF-8 files with BOM

**Notepad++**:
- Encoding → Encode in UTF-8 (without BOM)
