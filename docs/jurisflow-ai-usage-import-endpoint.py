"""
Endpoint para importação de dados históricos de tokens da Azure OpenAI.

Este arquivo deve ser adicionado ao JurisFlow AI service para permitir
a importação de métricas históricas obtidas da Azure Monitoring API.

## Como adicionar ao JurisFlow AI:

1. Copie este conteúdo para: jurisflow-ai-service/app/api/usage_import.py

2. No main.py, adicione:
   ```python
   from app.api.usage_import import router as usage_import_router
   app.include_router(usage_import_router, prefix="/v1/usage", tags=["usage"])
   ```

3. Reinicie o serviço:
   ```bash
   systemctl restart jurisflow-ai
   ```

## Teste local:

```bash
curl -X POST http://localhost:8090/v1/usage/import \
  -H "Content-Type: application/json" \
  -d '{
    "today": {"total_tokens": 1000, "requests": 10},
    "month": {"total_tokens": 50000, "requests": 500},
    "lifetime": {"total_tokens": 200000, "requests": 2000}
  }'
```
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from pathlib import Path
import json
from datetime import datetime, timezone
from typing import Optional

router = APIRouter()

# Caminho para o arquivo de persistência (mesmo usado pelo usage_tracker.py)
USAGE_FILE = Path(__file__).parent.parent.parent / "var" / "llm_usage.json"


class UsagePeriod(BaseModel):
    """Dados de uso para um período específico."""

    prompt_tokens: int = Field(default=0, ge=0)
    completion_tokens: int = Field(default=0, ge=0)
    total_tokens: int = Field(default=0, ge=0)
    requests: int = Field(default=0, ge=0)


class UsageImportPayload(BaseModel):
    """Payload para importação de dados históricos."""

    today: UsagePeriod
    month: UsagePeriod
    lifetime: UsagePeriod


@router.post("/import")
async def import_historical_usage(payload: UsageImportPayload) -> dict:
    """
    Importa dados históricos de uso de tokens da Azure OpenAI.

    Este endpoint mescla dados históricos importados com os dados
    existentes, somando os valores quando apropriado.

    Args:
        payload: Dados agregados por período (hoje, mês, lifetime)

    Returns:
        dict: Status da importação e estatísticas atualizadas
    """
    try:
        # Garantir que o diretório existe
        USAGE_FILE.parent.mkdir(parents=True, exist_ok=True)

        # Carregar dados existentes
        existing_data = {}
        if USAGE_FILE.exists():
            with open(USAGE_FILE, "r", encoding="utf-8") as f:
                existing_data = json.load(f)

        # Mesclar dados (somar valores)
        merged_data = {
            "today": _merge_usage(
                existing_data.get("today", {}), payload.today.model_dump()
            ),
            "month": _merge_usage(
                existing_data.get("month", {}), payload.month.model_dump()
            ),
            "lifetime": _merge_usage(
                existing_data.get("lifetime", {}), payload.lifetime.model_dump()
            ),
            "last_request_at": datetime.now(timezone.utc).isoformat(),
            "last_import_at": datetime.now(timezone.utc).isoformat(),
        }

        # Salvar dados mesclados
        with open(USAGE_FILE, "w", encoding="utf-8") as f:
            json.dump(merged_data, f, indent=2, ensure_ascii=False)

        return {
            "status": "success",
            "message": "Dados históricos importados e mesclados com sucesso",
            "imported": {
                "today_tokens": payload.today.total_tokens,
                "month_tokens": payload.month.total_tokens,
                "lifetime_tokens": payload.lifetime.total_tokens,
            },
            "current": {
                "today_tokens": merged_data["today"]["total_tokens"],
                "month_tokens": merged_data["month"]["total_tokens"],
                "lifetime_tokens": merged_data["lifetime"]["total_tokens"],
            },
        }

    except Exception as e:
        raise HTTPException(
            status_code=500, detail=f"Erro ao importar dados históricos: {str(e)}"
        )


def _merge_usage(existing: dict, imported: dict) -> dict:
    """
    Mescla dados de uso existentes com importados.

    Args:
        existing: Dados existentes no sistema
        imported: Dados importados da Azure

    Returns:
        dict: Dados mesclados (somados)
    """
    return {
        "prompt_tokens": existing.get("prompt_tokens", 0)
        + imported.get("prompt_tokens", 0),
        "completion_tokens": existing.get("completion_tokens", 0)
        + imported.get("completion_tokens", 0),
        "total_tokens": existing.get("total_tokens", 0)
        + imported.get("total_tokens", 0),
        "requests": existing.get("requests", 0) + imported.get("requests", 0),
    }


@router.post("/reset")
async def reset_usage() -> dict:
    """
    Reseta todos os dados de uso (apenas para desenvolvimento/testes).

    Returns:
        dict: Confirmação do reset
    """
    try:
        if USAGE_FILE.exists():
            USAGE_FILE.unlink()

        return {
            "status": "success",
            "message": "Dados de uso resetados com sucesso",
        }

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erro ao resetar dados: {str(e)}")
