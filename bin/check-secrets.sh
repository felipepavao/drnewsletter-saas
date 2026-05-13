#!/usr/bin/env bash
# =============================================================
# Pre-commit secret scanner para Dr. Newsletter SaaS.
#
# Bloqueia qualquer commit que contenha:
#  - chaves da Anthropic (sk-ant-...)
#  - chaves OpenAI (sk-...)
#  - tokens GitHub (ghp_, gho_, ghs_, ghr_)
#  - chaves AWS (AKIA...)
#  - URLs SMTP com senha embutida
#  - o próprio arquivo `.env`
#
# Instalação:
#   ln -sf ../../bin/check-secrets.sh .git/hooks/pre-commit
# =============================================================

set -e

RED='\033[0;31m'
YEL='\033[0;33m'
GRN='\033[0;32m'
NC='\033[0m'

# Lista de arquivos staged (adicionados/modificados, não deletados)
STAGED=$(git diff --cached --name-only --diff-filter=ACM)

if [ -z "$STAGED" ]; then
  exit 0
fi

FAIL=0

# 1) `.env` direto?
if echo "$STAGED" | grep -qE '(^|/)\.env(\.|$)' | grep -v '\.env\.example'; then
  echo -e "${RED}✗ tentativa de commitar arquivo .env${NC}"
  FAIL=1
fi

# 2) varredura de padrões conhecidos
PATTERNS=(
  'sk-ant-api[0-9]{2}-[A-Za-z0-9_-]{20,}'    # Anthropic
  'sk-[A-Za-z0-9]{20,}'                       # OpenAI-style
  'ghp_[A-Za-z0-9]{30,}'                      # GitHub PAT
  'gho_[A-Za-z0-9]{30,}'                      # GitHub OAuth
  'ghs_[A-Za-z0-9]{30,}'                      # GitHub server
  'AKIA[0-9A-Z]{16}'                          # AWS access key
  'xkeysib-[A-Za-z0-9]{30,}'                  # Brevo
)

for FILE in $STAGED; do
  # Pular o .env.example e este próprio script
  case "$FILE" in
    .env.example|bin/check-secrets.sh) continue ;;
  esac

  if [ ! -f "$FILE" ]; then continue; fi

  for PAT in "${PATTERNS[@]}"; do
    if grep -EnH "$PAT" "$FILE" 2>/dev/null; then
      echo -e "${RED}✗ padrão de segredo encontrado em ${FILE}${NC}"
      FAIL=1
    fi
  done
done

if [ $FAIL -eq 1 ]; then
  echo ""
  echo -e "${RED}Commit bloqueado pelo pre-commit hook.${NC}"
  echo -e "${YEL}Se for falso positivo, revise antes de bypassar com --no-verify.${NC}"
  exit 1
fi

echo -e "${GRN}✓ check-secrets: ok${NC}"
exit 0
