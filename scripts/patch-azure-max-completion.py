#!/usr/bin/env python3
"""Patch provider.py para enviar max_completion_tokens via model_kwargs no Azure GPT-5."""
from pathlib import Path

p = Path("/home2/joabef36/jurisflow-ai/app/llm/provider.py")
text = p.read_text(encoding="utf-8")

old = '''def _azure_token_kwargs(max_tokens: Optional[int]) -> dict:
    """GPT-5 no Azure usa max_completion_tokens em vez de max_tokens."""
    tokens = max_tokens or 4096
    deployment = settings.azure_deployment_name.lower()
    if deployment.startswith("gpt-5") or deployment.startswith("o"):
        return {"max_completion_tokens": tokens}
    return {"max_tokens": tokens}
'''

new = '''def _azure_token_kwargs(max_tokens: Optional[int]) -> dict:
    """GPT-5/o no Azure exigem max_completion_tokens.

    AzureChatOpenAI do LangChain mapeia kwargs soltos para max_tokens; o
    gpt-5-mini rejeita/ignora max_tokens e na pratica limita em ~1024,
    gastando tudo em reasoning_tokens com content vazio. Passamos via
    model_kwargs para ir no payload real da API.
    """
    tokens = max_tokens or 4096
    deployment = settings.azure_deployment_name.lower()
    if deployment.startswith("gpt-5") or deployment.startswith("o"):
        return {"model_kwargs": {"max_completion_tokens": tokens}}
    return {"max_tokens": tokens}
'''

# tambem cobre se ja tiver sido parcialmente alterado
if "model_kwargs\": {\"max_completion_tokens\"" in text or "model_kwargs': {'max_completion_tokens'" in text:
    print("ja patched")
elif old not in text:
    # fallback: replace so a linha do return
    marker = 'return {"max_completion_tokens": tokens}'
    if marker not in text:
        raise SystemExit("nao encontrou trecho para patch")
    text = text.replace(
        marker,
        'return {"model_kwargs": {"max_completion_tokens": tokens}}',
        1,
    )
    p.write_text(text, encoding="utf-8")
    print("patched via marker")
else:
    p.write_text(text.replace(old, new, 1), encoding="utf-8")
    print("patched via bloco completo")
