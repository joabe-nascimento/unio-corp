# Extensão de mercado — JurisFlow

Arquivos desta pasta entram no serviço Python de produção (`~/jurisflow-ai` na HostGator).

## O que entra

- `market_router.py` — jobs (`/v1/jobs`), extração de metadados, redação de PII, RAG persistente em SQLite, chains `publication-triage` e `hearing-prep`.

## Instalação no servidor

No `app/main.py` do JurisFlow completo:

```python
from app.market_router import router as market_router
app.include_router(market_router)
```

Copiar o arquivo para `~/jurisflow-ai/app/market_router.py` e reiniciar o uvicorn (watchdog/keepalive já cobre).

O stub local (`jurisflow-ai-service/main.py`) já inclui o mesmo router na raiz do serviço.
