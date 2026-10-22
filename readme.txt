=== JPX Eventos ===
Autor: Jotapix Agência Criativa
Versão: 2.1.0
Requer WordPress: 6.0+  |  Requer PHP: 7.4+  |  Testado até: WordPress 7.1

Agenda de eventos com calendário mensal, cards, modal, recorrência e cadastro rápido.
Sucessor do plugin "Eventos JPX" 1.x — aproveita os eventos já cadastrados.

== O que faz ==

* Agenda com lista (próximos / anteriores), calendário mensal, busca e filtros por tipo e núcleo.
* Calendário no celular vira lista por dia.
* Modal com resumo ao clicar num evento do calendário.
* Página do evento com data, local, mapa (carrega só ao clicar), inscrição,
  "Adicionar à agenda" (Google, Outlook, .ics) e compartilhar no WhatsApp.
* Eventos recorrentes (toda semana, todo mês na 2ª terça, na última quinta…),
  com cancelamento ou troca de local em datas específicas.
* Cadastro em 4 passos, criação rápida direto no calendário do painel e arrastar para mudar a data.
* Tipos de evento e núcleos com cor própria; locais salvos.
* Identidade visual (cores, cantos, fontes) nas Configurações — sem mexer em CSS.
* Dados estruturados (Schema.org Event) para o Google e agenda assinável (.ics).
* Sem jQuery; CSS/JS carregados só nas páginas que usam a agenda.

== Instalação / atualização a partir do Eventos JPX 1.x ==

1. Faça o backup (UpdraftPlus).
2. Plugins > Adicionar novo > Enviar plugin > escolha jpx-eventos.zip > Instalar > Ativar.
3. Na ativação o plugin:
   - desativa automaticamente o "Eventos JPX" antigo (ele continua instalado, como reserva);
   - guarda uma cópia dos campos antigos (opção jpx_eventos_backup_legado);
   - converte os horários escritos à mão ("19 horas", "8h às 10h"…) e avisa os que não entendeu;
   - herda as cores verdes originais.
4. Veja o aviso no painel e ajuste os horários listados (se houver).
5. Eventos > Configurações: confira cores e escolha a "Página da agenda".
6. Na página da agenda, troque o conteúdo por [jpx_agenda] (opcional: [lista_eventos] já mostra o visual novo).
7. Se a página do evento é um template do Divi Theme Builder com [evento_detalhe], nada muda:
   o shortcode passa a mostrar o layout novo. Nesse caso marque "Manual" em Configurações > Página do evento.
8. O ACF pode ser desativado se nenhum outro recurso do site usar.

Os endereços dos eventos (/eventos/nome-do-evento/) continuam os mesmos.

== Shortcodes ==

[jpx_agenda]      Agenda completa. Opções: cabecalho="nao", chamada="…", titulo="…", descricao="…",
                  visao="calendario", tipo="slug", nucleo="slug", por_pagina="9", filtros="nao".
[jpx_calendario]  Só o calendário. Opções: tipo, nucleo, filtros="sim", cabecalho="sim".
[jpx_eventos]     Próximos eventos (home, núcleos). Opções: quantidade="4", layout="cards|compacto",
                  tipo, nucleo, titulo="…", link_agenda="nao", passados="sim", agrupar="nao", colunas="3",
                  se_vazio="mensagem" (padrão: sem próximos eventos, mostra os últimos realizados).
[jpx_evento]      Página do evento completa (para templates de construtores). Opção: id="123".
Compatíveis: [lista_eventos], [eventos_home], [evento_detalhe].

Agenda assinável: https://seusite/?jpx_ics=agenda  (aceita &tipo= e &nucleo=)

== Ajuda para o cliente ==

Eventos › Ajuda abre o guia visual (manual/index.html). As telas do plugin mostram o quadro
"Primeiros passos" até tudo estar configurado, e botões "?" levam ao trecho certo do guia.

== Para desenvolvedores ==

Filtros: jpx_ev_feriados (incluir feriados municipais), jpx_ev_publicos, jpx_ev_schema,
jpx_ev_dados_evento, jpx_ev_carregar_sempre, jpx_ev_hoje.
Ação: jpx_ev_evento_salvo.
Template do arquivo: copie templates/arquivo.php para seu-tema/jpx-eventos/arquivo.php.
Tabela: {prefixo}jpx_ocorrencias (uma linha por data; regerada ao salvar e diariamente para recorrentes).
