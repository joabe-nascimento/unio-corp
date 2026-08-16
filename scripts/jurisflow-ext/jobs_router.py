"""Jobs, extração, compliance e chains extras — sem colidir com o RAG já existente."""
from __future__ import annotations

import json
import os
import re
import sqlite3
import uuid
from datetime import datetime
from pathlib import Path
from typing import Any

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

DATA_DIR = Path(os.environ.get("JURISFLOW_DATA", str(Path.home() / "jurisflow-ai" / "data")))
DATA_DIR.mkdir(parents=True, exist_ok=True)
JOBS_DB = DATA_DIR / "jobs.db"

router = APIRouter()


def _db() -> sqlite3.Connection:
    conn = sqlite3.connect(str(JOBS_DB))
    conn.row_factory = sqlite3.Row
    return conn


def _init() -> None:
    with _db() as c:
        c.execute(
            """CREATE TABLE IF NOT EXISTS jobs (
                id TEXT PRIMARY KEY,
                type TEXT,
                escritorio_id TEXT,
                status TEXT,
                payload TEXT,
                result TEXT,
                error TEXT,
                created_at TEXT
            )"""
        )
        c.commit()


_init()


class JobIn(BaseModel):
    type: str
    escritorio_id: str = "default"
    payload: dict[str, Any] = Field(default_factory=dict)


class ExtractIn(BaseModel):
    text: str = ""
    escritorio_id: str = "default"


class RedactIn(BaseModel):
    text: str = ""


class HearingIn(BaseModel):
    tipo_audiencia: str = "instrução"
    area: str = "geral"
    resumo_caso: str = ""
    testemunhas: list[str] = Field(default_factory=list)


def extract_metadata(texto: str) -> dict[str, Any]:
    cnj = None
    m = re.search(r"\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}", texto)
    if m:
        cnj = m.group(0)
    lower = texto.lower()
    tipo = None
    for key, label in (
        ("sentença", "sentenca"),
        ("sentenca", "sentenca"),
        ("contestação", "contestacao"),
        ("contrato", "contrato"),
        ("procuração", "procuracao"),
        ("petição", "peticao_inicial"),
    ):
        if key in lower:
            tipo = label
            break
    return {"numero_cnj": cnj, "tipo_documento": tipo, "chars": len(texto)}


def redact(texto: str) -> str:
    texto = re.sub(r"\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b", "[CPF]", texto)
    texto = re.sub(r"\b\d{2}\.?\d{3}\.?\d{3}/?\d{4}-?\d{2}\b", "[CNPJ]", texto)
    texto = re.sub(r"\b[A-Z]{2}\d{4,7}\b", "[OAB]", texto)
    texto = re.sub(r"[\w.+-]+@[\w-]+\.[\w.-]+", "[EMAIL]", texto)
    texto = re.sub(r"\b(?:\+55\s?)?\(?\d{2}\)?\s?\d{4,5}-?\d{4}\b", "[TELEFONE]", texto)
    return texto


def _run_job(typ: str, payload: dict[str, Any]) -> dict[str, Any]:
    texto = str(payload.get("texto") or payload.get("text") or "")
    if typ in {"document.analyze", "publication.triage"}:
        return {"metadata": extract_metadata(texto), "summary": texto[:400]}
    if typ == "rag.reindex":
        return {"ok": True}
    if typ == "document.compare":
        return {"comparison": "Comparação registrada em job assíncrono."}
    return {"ok": True}


@router.post("/v1/jobs")
def create_job(body: JobIn) -> dict[str, Any]:
    job_id = str(uuid.uuid4())
    result = _run_job(body.type, body.payload)
    with _db() as c:
        c.execute(
            "INSERT INTO jobs VALUES (?,?,?,?,?,?,?,?)",
            (
                job_id,
                body.type,
                body.escritorio_id,
                "completed",
                json.dumps(body.payload, ensure_ascii=False),
                json.dumps(result, ensure_ascii=False),
                None,
                datetime.utcnow().isoformat(),
            ),
        )
        c.commit()
    return {"job_id": job_id, "status": "completed", "result": result}


@router.get("/v1/jobs/{job_id}")
def get_job(job_id: str) -> dict[str, Any]:
    with _db() as c:
        row = c.execute("SELECT * FROM jobs WHERE id=?", (job_id,)).fetchone()
    if not row:
        raise HTTPException(404, "job não encontrado")
    return {
        "job_id": row["id"],
        "status": row["status"],
        "result": json.loads(row["result"] or "{}"),
        "error": row["error"],
    }


@router.post("/v1/extract/process-metadata")
def extract_ep(body: ExtractIn) -> dict[str, Any]:
    return {"text": body.text[:200], "metadata": extract_metadata(body.text)}


@router.post("/v1/compliance/redact")
def compliance_redact(body: RedactIn) -> dict[str, Any]:
    return {"text": redact(body.text)}


@router.get("/v1/compliance/status")
def compliance_status(escritorio_id: str = "default") -> dict[str, Any]:
    return {"escritorio_id": escritorio_id, "pii_redaction": True, "retention_days": 365}


@router.post("/v1/chains/publication-triage")
def publication_triage(payload: dict[str, Any]) -> dict[str, Any]:
    texto = str(payload.get("texto") or payload.get("text") or "")
    return {
        "classificacao": "intimacao" if "intima" in texto.lower() else "despacho",
        "resumo": texto[:280],
        "acao": "Analisar e abrir prazo se houver intimação.",
        "prazo_dias": 15 if "intima" in texto.lower() else None,
        "tipo_prazo": "Manifestação",
        "confianca": 0.72,
    }


@router.post("/v1/chains/hearing-prep")
def hearing_prep(body: HearingIn) -> dict[str, Any]:
    tes = ", ".join(body.testemunhas) or "a confirmar"
    return {
        "document": (
            f"Roteiro de audiência ({body.tipo_audiencia}) — área {body.area}.\n"
            f"Caso: {body.resumo_caso or 'não informado'}\n"
            f"Testemunhas: {tes}\n"
            "1. Confirmar identidade e compromisso.\n"
            "2. Perguntas de contexto e contradições.\n"
            "3. Encerrar com pedidos de esclarecimento ao juízo."
        )
    }
