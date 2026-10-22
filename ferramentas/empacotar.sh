#!/bin/sh
# Gera o .zip instalável do plugin (sem as ferramentas de desenvolvimento).
# Uso: sh ferramentas/empacotar.sh   → cria ../jpx-eventos-<versão>.zip
set -e
cd "$(dirname "$0")/.."
VERSAO=$(sed -n "s/.*define( 'JPX_EV_VERSION', '\([^']*\)' );.*/\1/p" jpx-eventos.php)
SAIDA="$(cd .. && pwd)/jpx-eventos-$VERSAO.zip"
rm -f "$SAIDA"
cd ..
zip -qr "$SAIDA" "$(basename "$OLDPWD")" \
  -x '*/.git/*' '*/.gitignore' '*/.gitattributes' '*/ferramentas/*' '*/.github/*' '*/manual/fonte.html' '*/README.md' '*.DS_Store'
echo "Gerado: $SAIDA"
