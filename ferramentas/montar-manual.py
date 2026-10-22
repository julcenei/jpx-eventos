"""Gera manual/index.html (versão do plugin) a partir de manual/fonte.html.

fonte.html é o conteúdo do guia sem <html>/<head>/<body> (o mesmo arquivo serve para
publicar a versão web). Rode depois de editar o texto ou trocar imagens:
    python3 ferramentas/montar-manual.py
"""
import os
import re

from PIL import Image

RAIZ = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'manual')
fonte = open(os.path.join(RAIZ, 'fonte.html'), encoding='utf-8').read()


def medidas(m):
    caminho = m.group(1)
    w, h = Image.open(os.path.join(RAIZ, caminho)).size
    return f'src="{caminho}" width="{w}" height="{h}"'


fonte = re.sub(r'src="(img/[^"]+)" width="\d+" height="\d+"', medidas, fonte)
open(os.path.join(RAIZ, 'fonte.html'), 'w', encoding='utf-8').write(fonte)
cabeca, corpo = fonte.split('</style>', 1)
doc = ('<!doctype html>\n<html lang="pt-BR">\n<head>\n<meta charset="utf-8">\n'
       '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n'
       + cabeca + '</style>\n</head>\n<body>\n' + corpo + '\n</body>\n</html>\n')
open(os.path.join(RAIZ, 'index.html'), 'w', encoding='utf-8').write(doc)
print('manual/index.html atualizado')
