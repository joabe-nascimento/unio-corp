# Como Iniciar o JurisFlow AI Service

## ⚠️ IMPORTANTE

O **JurisFlow** é um serviço Python separado que precisa estar rodando para a Sasha funcionar.  
Sem ele, o chat retorna "Lumen está temporariamente indisponível".

---

## 🚀 Iniciar Localmente (Desenvolvimento)

### 1. Abrir terminal no diretório do JurisFlow

```powershell
cd "C:\projetos\projeto-unef\Nova pasta\JurisFlow-ai-service"
```

### 2. Certificar que não há processo na porta 8090

```powershell
# Ver o que está na porta
netstat -ano | findstr ":8090"

# Se houver, matar o processo (substitua PID pelo número que apareceu)
taskkill /F /PID <PID>

# Ou matar todos os Python de uma vez
taskkill /F /IM python.exe
```

### 3. Iniciar o JurisFlow

```powershell
python -m uvicorn app.main:app --port 8090
```

**Saída esperada:**
```
INFO:     Started server process [XXXX]
INFO:     Waiting for application startup.
INFO:     Application startup complete.
INFO:     Uvicorn running on http://127.0.0.1:8090 (Press CTRL+C to quit)
```

### 4. Testar se está funcionando

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8090/v1/status" -Method GET
```

**Deve retornar:**
```json
{
  "service": "JurisFlow AI Platform",
  "status": "online",
  "llm_provider": "azure",
  "llm_model": "gpt-5-mini"
}
```

---

## 🌐 Produção (HostGator / VPS)

### Opções de Deploy

#### Opção A: Railway / Render / Fly.io
1. Conectar repositório do JurisFlow
2. Definir variáveis de ambiente do `.env.hostgator`
3. Deploy automático

#### Opção B: VPS própria (DigitalOcean, AWS, Azure)
```bash
cd /var/www/jurisflow-ai-service
source venv/bin/activate
uvicorn app.main:app --host 0.0.0.0 --port 8090 &
```

#### Opção C: HostGator (Python CGI/WSGI)
Seguir: `DEPLOY_HOSTGATOR.md` no repositório do JurisFlow

---

## ✅ Checklist de Funcionamento

- [ ] JurisFlow rodando na porta 8090
- [ ] `/health` retorna `{"status":"ok"}`
- [ ] `/v1/status` retorna JSON com `"status":"online"`
- [ ] `.env` do Unio Jurídico tem `LEGAL_AI_URL=http://127.0.0.1:8090`
- [ ] Chat da Sasha responde (não diz "indisponível")

---

## 🐛 Problemas Comuns

### "Lumen está temporariamente indisponível"
- **Causa:** JurisFlow não está rodando ou endpoint errado
- **Solução:** Verificar se está rodando na porta 8090 e testar `/v1/status`

### "error while attempting to bind on address"
- **Causa:** Porta 8090 já ocupada
- **Solução:** Matar processo anterior com `taskkill /F /IM python.exe`

### "detail": "Not Found" no endpoint `/v1/assistant/Sasha/chat`
- **Causa:** Código desatualizado do JurisFlow
- **Solução:** `git pull` no repositório do JurisFlow e reiniciar

---

## 📝 Configuração do Symfony (.env.local)

```bash
LEGAL_AI_ENABLED=true
LEGAL_AI_URL=http://127.0.0.1:8090  # Local
# LEGAL_AI_URL=https://ia.uniojuridico.com.br  # Produção
LEGAL_AI_ESCRITORIO_ID=default
```

---

## 🔄 Reiniciar Quando Necessário

Sempre que:
- Atualizar código do JurisFlow (`git pull`)
- Modificar `.env` do JurisFlow
- Após reiniciar o computador
- Se o chat parar de responder

**Comando rápido:**
```powershell
cd "C:\projetos\projeto-unef\Nova pasta\JurisFlow-ai-service"
taskkill /F /IM python.exe; Start-Sleep 2
python -m uvicorn app.main:app --port 8090
```
