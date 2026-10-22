# JPX Eventos

Plugin WordPress de agenda de eventos da **Jotapix Agência Criativa**: calendário mensal, cards, modal, recorrência com exceções, cadastro em 4 passos e criação rápida pelo calendário. Sucessor do *Eventos JPX* 1.x (migra os eventos antigos automaticamente).

- WordPress 6.0+ · PHP 7.4+ · sem jQuery, sem ACF
- Guia visual para o cliente em `manual/` (aparece em **Eventos › Ajuda**)
- Detalhes de uso e shortcodes: [`readme.txt`](readme.txt) · histórico: [`changelog.txt`](changelog.txt)

## Estrutura

```
jpx-eventos.php          arquivo principal (versão em JPX_EV_VERSION)
includes/                lógica: ocorrências, recorrência, render, REST, .ics, migração
includes/admin/          painel: cadastro, calendário, lista, configurações, termos, ajuda
assets/                  CSS e JS (front e painel)
templates/arquivo.php    arquivo de eventos / tipo / núcleo (temas clássicos)
manual/                  guia visual (index.html gerado a partir de fonte.html)
ferramentas/             empacotar, montar o manual, dados de demonstração, capturas
```

## Gerar o .zip

```sh
sh ferramentas/empacotar.sh
```

## Nova versão

1. Altere `Version:` e `JPX_EV_VERSION` em `jpx-eventos.php`.
2. Registre em `changelog.txt`.
3. Se o esquema da tabela mudar, aumente `JPX_EV_DB_VERSION`.
4. Gere o .zip e crie a *release* com ele anexado.

Licença: proprietária — Jotapix Agência Criativa.
