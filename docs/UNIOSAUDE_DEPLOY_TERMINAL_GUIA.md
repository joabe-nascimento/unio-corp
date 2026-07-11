# Deploy pelo terminal — Unio Saúde (guia prático)

Guia para subir alterações para **https://uniosaude.uniowork.com.br** sem depender do GitHub Actions.

---

## O que já está no servidor (como conferir)

Após um deploy bem-sucedido, o relatório mostra:

```
RELATÓRIO DE SUCESSO — deploy
Commit:  <hash do git>
Status:  Deploy server OK
```

Conferência rápida via SSH:

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br `
  "grep Commit /home2/joabef36/unio-uniosaude/var/log/deploy-report.txt | tail -1"
```

Verificar um arquivo específico (ex.: encoding UTF-8):

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br `
  "sed -n '7p' /home2/joabef36/unio-uniosaude/templates/modules/pos-operatorio/ops/produtos.html.twig"
```

Deve aparecer: `Ative pós-operatório, carteirinha digital e guia médico por clínica`

---

## Pré-requisitos (uma vez)

1. **Pasta correta do projeto** (com `scripts\deploy-uniosaude-manual.ps1`):

   ```
   C:\projetos\Nova pasta\unio-corp\unio-corp
   ```

   > Rodar na pasta pai (`unio-corp` sem a subpasta) dá erro: *"arquivo .ps1 não existe"*.

2. **Chave SSH** em `config\deploy-uniosaude.local.env`:

   ```
   DEPLOY_KEY_FILE=C:\Users\joabe\.ssh\unio_deploy
   ```

3. **Ferramentas no PC:** `git`, `composer`, `npm`, `php`, `tar`, `ssh`, `scp`.

Teste SSH:

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br "echo ok"
```

---

## Fluxo completo (recomendado)

```powershell
cd "C:\projetos\Nova pasta\unio-corp\unio-corp"

git status
git add <arquivos>
git commit -m "sua mensagem"
git push origin uniosaude

powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1
```

| Passo | Obrigatório? | Efeito |
|-------|--------------|--------|
| `git push` | Recomendado | Salva no GitHub (backup/histórico) |
| `deploy-uniosaude-manual.ps1` | **Sim** para o site | Envia código ao servidor |

---

## Se o deploy falhar na 1ª tentativa

### Erro: `$'\r': command not found` (exit 127)

Scripts `.sh` com fim de linha Windows (CRLF). O repositório usa LF (`.gitattributes`). Rode de novo:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1 -SkipBuild
```

O `-SkipBuild` reenvia o pacote e executa só a etapa do servidor (mais rápido).

### Erro: `deploy-remote.env: No such file`

Falha transitória na extração remota. Repita com `-SkipBuild`.

### Site não reflete mudanças

1. Hard refresh no navegador: **Ctrl+F5**
2. Limpar cache Symfony no servidor:

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br `
  "cd /home2/joabef36/unio-uniosaude && php bin/console cache:clear --env=prod"
```

---

## Slogan antigo ("Cuidado que continua.")

Pode persistir em `var/admin_config.json` no servidor. O deploy agora executa `scripts/lib/migrate-legacy-branding.sh` e o código normaliza na leitura.

Para corrigir manualmente:

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br `
  "bash /home2/joabef36/unio-uniosaude/scripts/lib/migrate-legacy-branding.sh /home2/joabef36/unio-uniosaude"
```

---

## Encoding UTF-8 nos templates

- Salve arquivos `.twig` e `.yaml` sempre em **UTF-8** (sem BOM).
- No repositório: `*.sh text eol=lf` em `.gitattributes`.
- Se acentos quebrarem na tela (`ps-operatrio`), o arquivo no servidor está corrompido — corrija localmente e rode deploy de novo.

---

## Caminhos no servidor

| Item | Caminho |
|------|---------|
| Aplicação | `/home2/joabef36/unio-uniosaude` |
| Site (docroot) | `/home2/joabef36/uniosaude.uniowork.com.br` |
| Relatório deploy | `var/log/deploy-report.txt` |
| Config admin | `var/admin_config.json` |

CSS/JS são servidos via symlink: `public_html/css` → `unio-uniosaude/public/css`.

---

## Checklist pós-deploy

- [ ] Terminal mostra `Deploy manual concluido` e `HTTP 200`
- [ ] `deploy-report.txt` com **SUCESSO**
- [ ] Login: https://uniosaude.uniowork.com.br/login
- [ ] Ctrl+F5 nas páginas alteradas
- [ ] Slogan: **Saúde que acompanha.**
- [ ] Acentos corretos em Produtos da plataforma

---

## Referência

Documentação complementar: [DEPLOY_MANUAL_UNIOSAUDE.md](DEPLOY_MANUAL_UNIOSAUDE.md)
