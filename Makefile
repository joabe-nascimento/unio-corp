# Atalhos de qualidade — espelham o CI (composer validate:ci / docker compose).
.PHONY: validate validate-ci validate-quick validate-docker hooks hooks-install help

help:
	@echo "Targets:"
	@echo "  make validate        — validação local (banco existente)"
	@echo "  make validate-ci     — validação igual ao CI (recria banco)"
	@echo "  make validate-quick  — só lints (sem PHPStan/testes/npm)"
	@echo "  make validate-docker — validação completa via Docker Compose"
	@echo "  make hooks-install   — instala git hook pre-push"

validate:
	composer validate:pre-push

validate-ci:
	composer validate:ci

validate-quick:
	QUICK=1 composer validate:pre-push

validate-docker:
	bash scripts/validate-docker.sh

hooks hooks-install:
	composer hooks:install
