#!/usr/bin/env python3
from pathlib import Path
import inspect
from langchain_openai import AzureChatOpenAI

sig = inspect.signature(AzureChatOpenAI.__init__)
params = list(sig.parameters)
print("has max_completion_tokens:", "max_completion_tokens" in params)
print("has reasoning_effort:", "reasoning_effort" in params)
print("token-ish:", [p for p in params if "token" in p or "reason" in p or "max" in p])

p = Path("/home2/joabef36/jurisflow-ai/app/llm/provider.py")
text = p.read_text(encoding="utf-8")

start = text.find("def _azure_token_kwargs")
if start < 0:
    raise SystemExit("funcao nao encontrada")
end = text.find("\ndef get_openrouter_model_list", start)
if end < 0:
    raise SystemExit("fim da funcao nao encontrado")

new_fn = '''def _azure_token_kwargs(max_tokens: Optional[int]) -> dict:
    """Parametros corretos para GPT-5/o no Azure.

    - max_completion_tokens (nao max_tokens)
    - reasoning_effort=low para nao esgotar o budget so em raciocinio
      (com effort alto, content fica vazio e finish_reason=length)
    """
    tokens = max_tokens or 4096
    deployment = settings.azure_deployment_name.lower()
    if deployment.startswith("gpt-5") or deployment.startswith("o"):
        return {
            "max_completion_tokens": tokens,
            "reasoning_effort": "low",
        }
    return {"max_tokens": tokens}


'''

text = text[:start] + new_fn + text[end + 1 :]
p.write_text(text, encoding="utf-8")
print("provider patched")

from importlib import reload
import app.llm.provider as prov

reload(prov)
print("kwargs4096", prov._azure_token_kwargs(4096))
llm = prov.get_llm_by_provider("azure", temperature=0.4, max_tokens=4096)
print(
    "fields",
    {
        "max_tokens": getattr(llm, "max_tokens", None),
        "max_completion_tokens": getattr(llm, "max_completion_tokens", None),
        "reasoning_effort": getattr(llm, "reasoning_effort", None),
        "model_kwargs": getattr(llm, "model_kwargs", None),
    },
)
