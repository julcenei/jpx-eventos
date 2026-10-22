# Ferramentas de desenvolvimento

Não vão no .zip do plugin.

| Arquivo | Para quê |
|---|---|
| `empacotar.sh` | Gera `../jpx-eventos-<versão>.zip` pronto para instalar. |
| `notas-versao.sh` | Imprime as notas de uma versão a partir do `changelog.txt` (usado pela release automática). |
| `montar-manual.py` | Gera `manual/index.html` a partir de `manual/fonte.html` (ajusta largura/altura das imagens). |
| `dados-demo.php` | Cria dados fictícios (associação exemplo) num WordPress de testes: `wp eval-file ferramentas/dados-demo.php`. |
| `capturas-manual.py` | Refaz as capturas anotadas e as animações do manual com Playwright, a partir de um WordPress de testes em `http://localhost:8899` com os dados de demonstração. |

Requisitos das ferramentas Python: `pip install playwright pillow` e `playwright install chromium`.
