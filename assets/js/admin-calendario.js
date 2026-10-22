/* JPX Eventos — calendário do painel (criação rápida e arrastar para mudar a data) */
(function () {
	'use strict';
	var C = window.JPX_EV_CAL || {};
	var T = C.txt || {};
	var raiz = document.querySelector('[data-jpx-acal]');
	if (!raiz) { return; }
	var grade = raiz.querySelector('[data-jpx-grade]');
	var titulo = raiz.querySelector('[data-jpx-titulo]');
	var aviso = raiz.querySelector('[data-jpx-aviso]');
	var mes = C.mes;
	var dados = null;
	var pop = null;

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function api(caminho, corpo) {
		return fetch(C.rest + caminho, {
			method: corpo ? 'POST' : 'GET',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
			body: corpo ? JSON.stringify(corpo) : undefined
		}).then(function (r) {
			return r.json().then(function (j) { if (!r.ok) { throw j; } return j; });
		});
	}
	function avisar(txt, erro) {
		aviso.textContent = txt;
		aviso.className = 'jpx-acal__aviso' + (erro ? ' is-erro' : '');
		aviso.hidden = false;
		clearTimeout(avisar.t);
		avisar.t = setTimeout(function () { aviso.hidden = true; }, 3500);
	}
	function iso(a, m, d) { return a + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0'); }

	function carregar() {
		fecharPop();
		grade.classList.add('is-carregando');
		var u = new URL(window.location.href);
		u.searchParams.set('mes', mes);
		window.history.replaceState({}, '', u.toString());
		api('admin/mes?mes=' + mes).then(function (j) {
			dados = j;
			titulo.textContent = j.titulo;
			desenhar();
			grade.classList.remove('is-carregando');
		}).catch(function () { grade.classList.remove('is-carregando'); avisar(T.erro, true); });
	}

	function desenhar() {
		var p = mes.split('-').map(Number);
		var primeiro = new Date(p[0], p[1] - 1, 1).getDay();
		var nDias = new Date(p[0], p[1], 0).getDate();
		var porDia = {};
		dados.eventos.forEach(function (e) {
			var a = new Date(e.inicio + 'T12:00:00');
			var b = new Date(e.fim + 'T12:00:00');
			for (var d = new Date(a); d <= b; d.setDate(d.getDate() + 1)) {
				var k = iso(d.getFullYear(), d.getMonth() + 1, d.getDate());
				(porDia[k] = porDia[k] || []).push(e);
			}
		});
		var h = '<div class="jpx-acal__semana">' + C.dias.map(function (d) { return '<span>' + esc(d) + '</span>'; }).join('') + '</div><div class="jpx-acal__dias">';
		for (var i = 0; i < primeiro; i++) { h += '<div class="jpx-acal__dia is-fora"></div>'; }
		for (var dia = 1; dia <= nDias; dia++) {
			var k = iso(p[0], p[1], dia);
			var cls = 'jpx-acal__dia' + (k === dados.hoje ? ' is-hoje' : '') + (k < dados.hoje ? ' is-passado' : '');
			h += '<div class="' + cls + '" data-dia="' + k + '"><div class="jpx-acal__cab"><button type="button" class="jpx-acal__num" data-criar="' + k + '" aria-label="' + esc(T.novoEm + ' ' + dia) + '">' + dia + '</button>' +
				(dados.feriados[k] ? '<span class="jpx-acal__fer">' + esc(dados.feriados[k]) + '</span>' : '') + '</div>';
			(porDia[k] || []).sort(function (a, b) { return (a.hora || '') < (b.hora || '') ? -1 : 1; }).forEach(function (e) {
				var selo = e.status === 'rascunho' ? T.rascunhoSelo : e.status === 'agendado' ? T.agendadoSelo : e.status === 'cancelada' ? T.canceladaSelo : '';
				h += '<button type="button" class="jpx-acal__ev' + (e.status !== 'ativa' && e.status !== 'alterada' ? ' is-' + e.status : '') + '" draggable="' + (e.recorrente ? 'false' : 'true') + '" data-ev="' + e.id + '" data-oc="' + e.oc + '" data-ini="' + e.inicio + '" style="--c:' + esc(e.cor) + ';--cs:' + esc(e.suave) + '">' +
					'<i></i>' + (e.hora ? '<b>' + esc(e.hora) + '</b>' : '') + '<span>' + esc(e.titulo) + '</span>' + (selo ? '<em>' + esc(selo) + '</em>' : '') + (e.recorrente ? '<small aria-hidden="true">↻</small>' : '') + '</button>';
			});
			h += '</div>';
		}
		var resto = (7 - ((primeiro + nDias) % 7)) % 7;
		for (var r = 0; r < resto; r++) { h += '<div class="jpx-acal__dia is-fora"></div>'; }
		grade.innerHTML = h + '</div>';
	}

	/* ---------- Popovers ---------- */
	function fecharPop() {
		if (pop) { pop.remove(); pop = null; }
		document.querySelectorAll('.jpx-acal__dia.is-ativo').forEach(function (d) { d.classList.remove('is-ativo'); });
	}
	function abrirPop(celula, html) {
		fecharPop();
		pop = document.createElement('div');
		pop.className = 'jpx-acal__pop';
		pop.setAttribute('role', 'dialog');
		pop.innerHTML = html;
		celula.classList.add('is-ativo');
		celula.appendChild(pop);
		var r = pop.getBoundingClientRect();
		if (r.right > window.innerWidth - 16) { pop.classList.add('is-esq'); }
		return pop;
	}

	function criar(dia) {
		var cel = grade.querySelector('[data-dia="' + dia + '"]');
		var d = new Date(dia + 'T12:00:00');
		var rot = C.diasLongos[d.getDay()] + ', ' + d.getDate() + ' de ' + C.meses[d.getMonth()];
		var tipos = C.tipos.map(function (t, i) {
			return '<label class="jpx-acal__chip" style="--c:' + esc(t.cor) + '"><input type="radio" name="tipo" value="' + t.id + '"><span>' + esc(t.nome) + '</span></label>';
		}).join('');
		var locais = '<option value="0">' + esc(T.semLocal) + '</option>' + C.locais.map(function (l) {
			return '<option value="' + l.id + '"' + (String(l.id) === String(C.localPadrao) ? ' selected' : '') + '>' + esc(l.nome) + '</option>';
		}).join('');
		var p = abrirPop(cel,
			'<form class="jpx-acal__form">' +
			'<div class="jpx-acal__pop-topo"><strong>' + esc(T.novoEm) + ' · ' + esc(rot) + '</strong><button type="button" class="jpx-acal__x" data-fechar aria-label="' + esc(T.fechar) + '">×</button></div>' +
			'<label class="screen-reader-text" for="jpx-q-nome">' + esc(T.nome) + '</label>' +
			'<input id="jpx-q-nome" name="titulo" type="text" required placeholder="' + esc(T.nome) + '" class="jpx-acal__nome">' +
			'<div class="jpx-acal__linha"><span>' + esc(T.das) + '</span><input type="time" name="hora_inicio" value="19:00" aria-label="Início"><span>' + esc(T.as) + '</span><input type="time" name="hora_fim" value="21:00" aria-label="Término"></div>' +
			(tipos ? '<div class="jpx-acal__chips">' + tipos + '</div>' : '') +
			'<div class="jpx-acal__linha jpx-acal__linha--2"><select name="local" aria-label="Local">' + locais + '</select><select name="repete" aria-label="Repetição"><option value="nenhuma">' + esc(T.naoRepete) + '</option></select></div>' +
			'<div class="jpx-acal__pop-rodape"><a href="' + esc(C.novo + '&data=' + dia) + '">' + esc(T.mais) + '</a><span><small>' + esc(T.enter) + '</small> <button type="submit" class="button button-primary">' + esc(C.podePublicar ? T.publicar : T.rascunho) + '</button></span></div>' +
			'</form>');
		var f = p.querySelector('form');
		f.querySelector('[name="titulo"]').focus();
		api('admin/previa', { inicio: dia, regra: { freq: 'nenhuma' } }).then(function (j) {
			var sel = f.querySelector('[name="repete"]');
			sel.innerHTML = (j.sugestoes || []).filter(function (s) { return s.chave !== 'dias_uteis'; }).map(function (s) { return '<option value="' + esc(s.chave) + '">' + esc(s.rotulo) + '</option>'; }).join('');
		}).catch(function () {});
		var anterior = '19:00';
		f.querySelector('[name="hora_inicio"]').addEventListener('change', function (e) {
			var v = e.target.value; var fim = f.querySelector('[name="hora_fim"]');
			if (!v) { return; }
			var m = function (x) { var p = x.split(':'); return +p[0] * 60 + +p[1]; };
			var dur = fim.value && m(fim.value) > m(anterior) ? m(fim.value) - m(anterior) : 120;
			var t = Math.min(23 * 60 + 59, m(v) + dur);
			fim.value = String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0');
			anterior = v;
		});
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var fd = new FormData(f);
			var corpo = { data: dia };
			fd.forEach(function (v, k) { corpo[k] = v; });
			if (!corpo.titulo.trim()) { return; }
			f.querySelector('[type="submit"]').disabled = true;
			api('admin/rapido', corpo).then(function (j) {
				avisar(j.status === 'publish' ? T.publicado : T.rascunhoSelo);
				carregar();
			}).catch(function (er) { avisar((er && er.message) || T.erro, true); f.querySelector('[type="submit"]').disabled = false; });
		});
	}

	function detalhes(btn) {
		var e = dados.eventos.filter(function (x) { return String(x.id) === btn.getAttribute('data-ev') && (x.oc ? String(x.oc) === btn.getAttribute('data-oc') : true); })[0];
		if (!e) { return; }
		var cel = btn.closest('.jpx-acal__dia');
		abrirPop(cel,
			'<div class="jpx-acal__pop-topo"><strong>' + esc(e.titulo) + '</strong><button type="button" class="jpx-acal__x" data-fechar aria-label="' + esc(T.fechar) + '">×</button></div>' +
			'<p class="jpx-acal__info" style="--c:' + esc(e.cor) + '">' + (e.tipo ? '<span class="jpx-acal__tipo">' + esc(e.tipo) + '</span> ' : '') + esc([e.horario, e.local].filter(Boolean).join(' · ')) + '</p>' +
			(e.regra ? '<p class="jpx-acal__info">↻ ' + esc(e.regra) + '</p>' : '') +
			'<div class="jpx-acal__pop-rodape"><span><a class="button" href="' + esc(e.duplicar) + '">' + esc(T.duplicar) + '</a> <a class="button" href="' + esc(e.ver) + '" target="_blank">' + esc(T.ver) + '</a></span><a class="button button-primary" href="' + esc(e.editar) + '">' + esc(T.editar) + '</a></div>');
	}

	/* ---------- Eventos de interface ---------- */
	raiz.addEventListener('click', function (e) {
		var b;
		if ((b = e.target.closest('[data-nav]'))) {
			var n = parseInt(b.getAttribute('data-nav'), 10);
			if (n === 0) { var h = new Date(); mes = h.getFullYear() + '-' + String(h.getMonth() + 1).padStart(2, '0'); }
			else { var p = mes.split('-').map(Number); var d = new Date(p[0], p[1] - 1 + n, 1); mes = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'); }
			carregar();
			return;
		}
		if (e.target.closest('[data-fechar]')) { fecharPop(); return; }
		if (pop && pop.contains(e.target)) { return; }
		if ((b = e.target.closest('[data-ev]'))) { detalhes(b); return; }
		if ((b = e.target.closest('[data-criar]'))) { criar(b.getAttribute('data-criar')); return; }
		var cel = e.target.closest('.jpx-acal__dia[data-dia]');
		if (cel) { criar(cel.getAttribute('data-dia')); return; }
		fecharPop();
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fecharPop(); } });
	document.addEventListener('click', function (e) { if (pop && !raiz.contains(e.target)) { fecharPop(); } });

	/* ---------- Arrastar e soltar ---------- */
	var arrastando = null;
	grade.addEventListener('dragstart', function (e) {
		var b = e.target.closest('[data-ev]');
		if (!b || b.getAttribute('draggable') !== 'true') { return; }
		arrastando = b;
		fecharPop();
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', b.getAttribute('data-ev'));
		setTimeout(function () { b.classList.add('is-arrastando'); }, 0);
	});
	grade.addEventListener('dragend', function () {
		if (arrastando) { arrastando.classList.remove('is-arrastando'); }
		arrastando = null;
		grade.querySelectorAll('.is-alvo').forEach(function (c) { c.classList.remove('is-alvo'); });
	});
	grade.addEventListener('dragover', function (e) {
		var cel = e.target.closest('.jpx-acal__dia[data-dia]');
		if (!arrastando || !cel) { return; }
		e.preventDefault();
		grade.querySelectorAll('.is-alvo').forEach(function (c) { if (c !== cel) { c.classList.remove('is-alvo'); } });
		cel.classList.add('is-alvo');
	});
	grade.addEventListener('drop', function (e) {
		var cel = e.target.closest('.jpx-acal__dia[data-dia]');
		if (!arrastando || !cel) { return; }
		e.preventDefault();
		var novo = cel.getAttribute('data-dia');
		if (novo === arrastando.getAttribute('data-ini')) { return; }
		cel.appendChild(arrastando);
		api('admin/mover', { id: arrastando.getAttribute('data-ev'), data: novo })
			.then(function () { avisar(T.movido); carregar(); })
			.catch(function (er) { avisar((er && er.message) || T.erro, true); carregar(); });
	});
	grade.addEventListener('mousedown', function (e) {
		var b = e.target.closest('[data-ev][draggable="false"]');
		if (b) { b.title = T.recorrente; }
	});

	carregar();
})();
