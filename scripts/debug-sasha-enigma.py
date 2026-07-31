#!/usr/bin/env python3
"""Debug: chama sasha_chat e o orchestrator com o enigma, mostrando erros reais."""
import asyncio
import traceback

async def main():
    from app.chains.sasha_assistant import sasha_chat
    from app.orchestration.sasha_orchestrator import sasha_orchestrator
    from app.orchestration.router import route_query
    from app.llm.provider import get_llm_by_provider
    from langchain_core.messages import HumanMessage

    msg = (
        "Você era o dono de uma vila, e nessa vila haviam somente apenas seis tias, "
        "cada prima tinha um irmão, e cada irmão tinha um avô, quantas pessoas tinham "
        "na casa e quem era o dono ?"
    )

    print("=== 1) Azure direto ===")
    try:
        llm = get_llm_by_provider("azure", temperature=0.4, max_tokens=1024)
        out = await llm.ainvoke([HumanMessage(content="Responda em 1 frase: quanto é 2+2?")])
        print("OK:", getattr(out, "content", out)[:300])
    except Exception as e:
        print("FAIL:", type(e).__name__, e)
        traceback.print_exc()

    print("\n=== 2) route_query ===")
    try:
        decision, strategy = await route_query(msg, "1", None)
        print("strategy=", strategy, "intent=", getattr(decision.intent, "value", decision.intent), "use_agent=", decision.use_agent)
        print("explanation=", decision.explanation)
    except Exception as e:
        print("FAIL:", type(e).__name__, e)
        traceback.print_exc()

    print("\n=== 3) sasha_chat (chain) ===")
    try:
        answer = await sasha_chat(
            escritorio_id="1",
            message=msg,
            use_rag=False,
            history=None,
            time_context={"date": "30/07/2026", "time": "17:20", "period": "tarde"},
        )
        print("ANSWER:", answer[:800])
    except Exception as e:
        print("FAIL:", type(e).__name__, e)
        traceback.print_exc()

    print("\n=== 4) orchestrator ===")
    try:
        answer, meta = await sasha_orchestrator(
            escritorio_id="1",
            message=msg,
            use_rag=False,
            history=None,
            time_context={"date": "30/07/2026", "time": "17:20", "period": "tarde"},
        )
        print("META:", meta)
        print("ANSWER:", answer[:800])
    except Exception as e:
        print("FAIL:", type(e).__name__, e)
        traceback.print_exc()

if __name__ == "__main__":
    asyncio.run(main())
