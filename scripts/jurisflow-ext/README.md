# Extensão de mercado — JurisFlow

Arquivos desta pasta entram no serviço Python de produção (`~/jurisflow-ai` na HostGator).

## O que entra

- `jobs_router.py` — produção (`~/jurisflow-ai/app/jobs_router.py`): jobs, extração, redação de PII e chains `publication-triage` / `hearing-prep`. **Não** redefine RAG (já existe no `app/main.py`).
- `market_router.py` — stub local (`jurisflow-ai-service/`), inclui RAG SQLite para desenvolvimento.

## Instalação no servidor

No `app/main.py` do JurisFlow completo:

```python
from app.jobs_router import router as jobs_router
app.include_router(jobs_router)
```

Copiar `jobs_router.py` para `~/jurisflow-ai/app/jobs_router.py`. O uvicorn na HostGator só carrega as rotas novas depois de reciclar o processo (CageFS). O watchdog religa sozinho se `/health` cair.

O stub local (`jurisflow-ai-service/main.py`) inclui `market_router.py` na raiz do serviço.

