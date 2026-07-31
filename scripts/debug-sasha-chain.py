#!/usr/bin/env python3
import asyncio
import traceback

async def main():
    from app.chains.sasha_assistant import _build_chain, _answer_from_message
    from app.llm.response_quality import is_invalid_assistant_response
    from app.llm.provider import get_llm_by_provider
    from app.llm.errors import is_rate_limit_error

    msg = (
        "Você era o dono de uma vila, e nessa vila haviam somente apenas seis tias, "
        "cada prima tinha um irmão, e cada irmão tinha um avô, quantas pessoas tinham "
        "na casa e quem era o dono ?"
    )
    inputs = {
        "message": msg,
        "escritorio_id": "1",
        "use_rag": False,
        "history": None,
        "time_context": {"date": "30/07/2026", "time": "17:20", "period": "tarde"},
        "numero_processo_atual": None,
    }

    llm = get_llm_by_provider("azure", temperature=0.4, max_tokens=1024)
    chain = _build_chain(llm)
    try:
        message_out = await chain.ainvoke(inputs)
        print("RAW_TYPE=", type(message_out))
        print("RAW_CONTENT_TYPE=", type(getattr(message_out, "content", None)))
        print("RAW_CONTENT=", repr(getattr(message_out, "content", message_out))[:2000])
        answer = _answer_from_message(message_out)
        print("ANSWER=", repr(answer)[:1000])
        print("INVALID=", is_invalid_assistant_response(answer))
        print("ADDITIONAL=", getattr(message_out, "additional_kwargs", None))
        print("RESPONSE_META=", getattr(message_out, "response_metadata", None))
    except Exception as e:
        print("EXC_TYPE=", type(e).__name__)
        print("EXC=", e)
        print("RATE_LIMIT=", is_rate_limit_error(e))
        traceback.print_exc()

if __name__ == "__main__":
    asyncio.run(main())
