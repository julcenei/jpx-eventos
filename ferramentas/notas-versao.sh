#!/bin/sh
# Imprime as notas de uma versão a partir do changelog.txt, em Markdown.
# Uso: sh ferramentas/notas-versao.sh 2.1.0
set -e
cd "$(dirname "$0")/.."
VERSAO="$1"
awk -v v="$VERSAO" '
  $0 ~ "^[0-9]+\\.[0-9]+\\.[0-9]+" { if (f) exit; if (index($0, v) == 1) { f = 1; next } }
  f && /^  +/ { sub(/^ +/, " "); linha = linha $0; next }
  f { if (linha != "") print linha; linha = $0 }
  END { if (linha != "") print linha }
' changelog.txt
