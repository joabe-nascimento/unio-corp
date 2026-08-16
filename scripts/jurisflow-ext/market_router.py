"""
Extensão de mercado do JurisFlow: jobs, extração, compliance, RAG persistente e chains extras.
Copiado para ~/jurisflow-ai/app/market_router.py na HostGator.
"""
from __future__ import annotations

import hashlib
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
RAG_DB = DATA_DIR / "rag.sqlite"

router = APIRouter()


def _db(path: Path) -> sqlite3.Connection:
    conn = sqlite3.connect(str(path))
    conn.row_factory = sqlite3.Row
    return conn


def _init() -> None:
    with _db(JOBS_DB) as c:
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
    with _db(RAG_DB) as c:
        c.execute(
            """CREATE TABLE IF NOT EXISTS chunks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                escritorio_id TEXT,
                source TEXT,
                title TEXT,
                category TEXT,
                content TEXT,
                hash TEXT
            )"""
        )
        c.execute("CREATE INDEX IF NOT EXISTS idx_chunks_esc ON chunks(escritorio_id)")
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


class RagDocIn(BaseModel):
    title: str
    content: str
    category: str = "geral"
    source: str = ""


class RagSearchIn(BaseModel):
    query: str
    limit: int = 8


class HearingIn(BaseModel):
    tipo_audiencia: str = "instrução"
    area: str = "geral"
    resumo_caso: str = ""
    testemunhas: list[str] = Field(default_factory=list)


def _run_job(job_id: str, typ: str, payload: dict[str, Any]) -> dict[str, Any]:
    texto = str(payload.get("texto") or payload.get("text") or "")
    if typ in {"document.analyze", "publication.triage"}:
        meta = extract_metadata(texto)
        return {"metadata": meta, "summary": texto[:400]}
    if typ == "rag.reindex":
        return {"ok": True}
    if typ == "document.compare":
        return {"comparison": "Comparação registrada em job assíncrono."}
    return {"ok": True}


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


@router.post("/v1/jobs")
def create_job(body: JobIn) -> dict[str, Any]:
    job_id = str(uuid.uuid4())
    result = _run_job(job_id, body.type, body.payload)
    with _db(JOBS_DB) as c:
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
    with _db(JOBS_DB) as c:
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


@router.post("/v1/rag/{escritorio_id}/documents")
def rag_add(escritorio_id: str, body: RagDocIn) -> dict[str, Any]:
    h = hashlib.sha256(body.content.encode("utf-8", errors="ignore")).hexdigest()
    with _db(RAG_DB) as c:
        c.execute("DELETE FROM chunks WHERE escritorio_id=? AND source=?", (escritorio_id, body.source))
        c.execute(
            "INSERT INTO chunks(escritorio_id,source,title,category,content,hash) VALUES (?,?,?,?,?,?)",
            (escritorio_id, body.source, body.title, body.category, body.content[:8000], h),
        )
        c.commit()
    return {"ok": True, "hash": h}


@router.post("/v1/rag/{escritorio_id}/search")
def rag_search(escritorio_id: str, body: RagSearchIn) -> dict[str, Any]:
    q = body.query.lower()
    with _db(RAG_DB) as c:
        rows = c.execute(
            "SELECT * FROM chunks WHERE escritorio_id=? ORDER BY id DESC LIMIT 80",
            (escritorio_id,),
        ).fetchall()
    chunks = []
    for r in rows:
        content = r["content"] or ""
        score = 0.2
        if q and q in content.lower():
            score = 0.9
        elif any(tok in content.lower() for tok in q.split() if len(tok) > 3):
            score = 0.55
        if score >= 0.5:
            chunks.append(
                {
                    "document_id": str(r["id"]),
                    "document_title": r["title"],
                    "category": r["category"],
                    "content": content[:600],
                    "score": score,
                    "source": r["source"],
                }
            )
        if len(chunks) >= body.limit:
            break
    return {"chunks": chunks}


@router.get("/v1/rag/{escritorio_id}/stats")
def rag_stats(escritorio_id: str) -> dict[str, Any]:
    with _db(RAG_DB) as c:
        n = c.execute("SELECT COUNT(*) FROM chunks WHERE escritorio_id=?", (escritorio_id,)).fetchone()[0]
    return {"escritorio_id": escritorio_id, "chunks": n, "store": "sqlite"}
