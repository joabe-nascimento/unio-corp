#!/usr/bin/env python3
import asyncio
from langchain_core.messages import HumanMessage
from app.llm.provider import get_llm_by_provider, _azure_token_kwargs

print("kwargs", _azure_token_kwargs(4096))
llm = get_llm_by_provider("azure", temperature=0.4, max_tokens=4096)
print("max_tokens", getattr(llm, "max_tokens", None))
print("model_kwargs", getattr(llm, "model_kwargs", None))

async def t():
    out = await llm.ainvoke([HumanMessage(content="Responda so a palavra: PING")])
    print("content=", repr(out.content)[:300])
    print("usage=", out.response_metadata.get("token_usage"))

asyncio.run(t())
