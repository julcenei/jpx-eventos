# JPX Eventos — instruções para o Claude

Plugin WordPress da Jotapix. O dono (Julcenei) não quer cuidar do processo do GitHub: **o Claude cuida de tudo**.

## Ao terminar qualquer alteração no plugin

Pergunte, em uma linha, se ele quer publicar no GitHub, sugerindo o número da próxima versão. Exemplo:
"Quer que eu publique isso como versão 2.1.1?"

Se ele disser sim (ou já tiver pedido "atualiza no github", "publica", "sobe"), faça tudo sem pedir mais nada:

1. Escolha a versão (semver):
   - correção ou ajuste pequeno → `2.1.0` → `2.1.1`
   - recurso novo → `2.1.0` → `2.2.0`
   - mudança que quebra compatibilidade → `3.0.0` (confirme com ele antes)
   Se a versão não for alterada, nenhuma release é gerada.
2. Em `jpx-eventos.php`, altere `Version:` no cabeçalho **e** `JPX_EV_VERSION` (os dois iguais). Se o esquema da tabela mudou, aumente `JPX_EV_DB_VERSION`.
3. Em `readme.txt`, atualize `Stable tag:`.
4. No topo de `changelog.txt`, adicione a entrada no formato existente:
   ```
   2.1.1 — DD/MM/AAAA
   - Frase curta, em português, do ponto de vista de quem usa o plugin.
   ```
5. Verifique: `php -l` nos arquivos PHP alterados e `sh ferramentas/notas-versao.sh <versão>` (precisa imprimir as notas).
6. Faça o commit (mensagem em português) e `git push origin main`. **Não crie nem envie tags**: a automação `.github/workflows/release.yml` cria a tag, gera o .zip e publica a release sozinha.
7. Responda em linguagem simples: o que foi publicado e o número da versão. Ele pode baixar o .zip em github.com/julcenei/jpx-eventos/releases.

Se o push falhar, explique em poucas palavras e diga o que ele precisa fazer. Nunca peça que ele rode comandos de git.

## Referências

- Estrutura e ferramentas: `README.md` e `ferramentas/LEIAME.md`.
- Gerar o .zip localmente: `sh ferramentas/empacotar.sh`.
- Textos visíveis ao usuário em português do Brasil, com text domain `jpx-eventos`.
