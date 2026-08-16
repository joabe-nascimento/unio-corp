from pathlib import Path

p = Path("/home2/joabef36/jurisflow-ai/app/main.py")
t = p.read_text(encoding="utf-8")
if "jobs_router" in t:
    print("jobs_router already wired")
else:
    if "from app.jobs_router import router as jobs_router" not in t:
        t = t.replace(
            "from app.pipelines.runner import run_pipeline",
            "from app.pipelines.runner import run_pipeline\nfrom app.jobs_router import router as jobs_router",
            1,
        )
    if "app.include_router(jobs_router)" not in t:
        t = t.replace(
            "app.add_middleware(\n    CORSMiddleware,",
            "app.add_middleware(\n    CORSMiddleware,",
            1,
        )
        # include after CORS middleware closing paren of that block
        marker = "    allow_headers=[\"*\"],\n)"
        idx = t.find(marker)
        if idx == -1:
            t += "\napp.include_router(jobs_router)\n"
        else:
            insert_at = idx + len(marker)
            t = t[:insert_at] + "\n\napp.include_router(jobs_router)\n" + t[insert_at:]
    p.write_text(t, encoding="utf-8")
    print("wired jobs_router")
