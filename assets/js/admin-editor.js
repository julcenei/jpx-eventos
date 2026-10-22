/* JPX Eventos — tela de cadastro do evento */
(function () {
	'use strict';
	var C = window.JPX_EV_ED || {};
	var T = C.txt || {};
	var raiz = document.querySelector('[data-jpx-editor]');
	if (!raiz) { return; }
	var form = document.getElementById('post');

	function $(s, c) { return (c || document).querySelector(s); }
	function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }
	function val(nome) {
		var el = form.querySelector('[name="' + nome + '"]:checked') || form.querySelector('[name="' + nome + '"]:not([type="radio"]):not([type="checkbox"])');
		return el ? el.value : '';
	}
	function marcado(nome) { var el = form.querySelector('[name="' + nome + '"]'); return !!(el && el.checked); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

	var inicio = $('#jpx-inicio');
	var fim = $('#jpx-fim');
	var hi = $('#jpx-hi');
	var hf = $('#jpx-hf');
	var repete = $('[data-jpx-repete]');
	var excInput = $('[data-jpx-excecoes]');
	var exc = {};
	try { exc = JSON.parse(excInput.value || '{}') || {}; } catch (e) { exc = {}; }

	/* ---------- Mostrar/ocultar ---------- */
	function sincronizar() {
		var varios = marcado('jpx[varios_dias]');
		$('[data-jpx-fim]').hidden = !varios;
		$('[data-jpx-horas]').hidden = marcado('jpx[dia_inteiro]');
		$('[data-jpx-repete-caixa]').hidden = varios;
		var r = repete.value;
		$('[data-jpx-personalizar]').hidden = r !== 'personalizar';
		$('[data-jpx-termina]').hidden = r === 'nenhuma';
		$('[data-jpx-previa]').hidden = r === 'nenhuma';
		var freq = val('jpx[regra][freq]');
		$$('[data-jpx-se]').forEach(function (el) { el.hidden = el.getAttribute('data-jpx-se') !== freq; });
		var loc = val('jpx[local_id]');
		$('[data-jpx-novo-local]').hidden = loc !== 'novo';
		var sel = form.querySelector('[name="jpx[local_id]"]:checked');
		$('[data-jpx-endereco]').textContent = sel && sel.getAttribute('data-endereco') ? sel.getAttribute('data-endereco') : '';
		var vt = val('jpx[valor_tipo]');
		$('[data-jpx-valor]').hidden = !(vt === 'pago' || vt === 'solidario');
		if (varios && fim && !fim.value) { fim.value = inicio.value; }
		previaCard();
	}

	/* ---------- Horário: término sugerido ---------- */
	function min(v) { var p = v.split(':'); return parseInt(p[0], 10) * 60 + parseInt(p[1], 10); }
	function hhmm(m) { m = Math.max(0, Math.min(23 * 60 + 59, m)); return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'); }
	var inicioAnterior = hi ? hi.value : '';
	if (hi) {
		hi.addEventListener('change', function () {
			if (!hi.value) { return; }
			// O término acompanha o início, mantendo a duração (padrão: 2 horas).
			var dur = (inicioAnterior && hf.value && min(hf.value) > min(inicioAnterior)) ? min(hf.value) - min(inicioAnterior) : 120;
			hf.value = hhmm(min(hi.value) + dur);
			inicioAnterior = hi.value;
			previaCard();
		});
	}

	/* ---------- Repetição: sugestões e prévia das datas ---------- */
	var timer;
	function regraAtual() {
		var r = {};
		$$('[name^="jpx[regra]"]', form).forEach(function (el) {
			var k = el.name.replace(/^jpx\[regra\]\[/, '').replace(/\](\[\])?$/, '');
			if ((el.type === 'radio' || el.type === 'checkbox') && !el.checked) { return; }
			if (/\[\]$/.test(el.name)) { (r[k] = r[k] || []).push(el.value); } else { r[k] = el.value; }
		});
		return r;
	}
	function atualizarPrevia() {
		clearTimeout(timer);
		timer = setTimeout(function () {
			if (!inicio.value) { return; }
			var corpo = { inicio: inicio.value, regra: {} };
			var chave = repete.value;
			var regra = regraAtual();
			if (chave !== 'personalizar') {
				var op = repete.selectedOptions[0];
				corpo.regra = op && op.getAttribute('data-regra') ? JSON.parse(op.getAttribute('data-regra')) : { freq: 'nenhuma' };
				corpo.regra.fim = regra.fim; corpo.regra.ate = regra.ate; corpo.regra.contagem = regra.contagem;
			} else {
				corpo.regra = regra;
			}
			fetch(C.rest + 'admin/previa', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce },
				body: JSON.stringify(corpo)
			}).then(function (r) { return r.json(); }).then(function (j) {
				// Reescreve as sugestões conforme a data (mantendo a escolha).
				var atual = repete.value;
				var html = '';
				(j.sugestoes || []).forEach(function (s) { html += '<option value="' + esc(s.chave) + '" data-regra="' + esc(JSON.stringify(s.regra)) + '">' + esc(s.rotulo) + '</option>'; });
				html += '<option value="personalizar">' + esc(T.personalizar) + '</option>';
				repete.innerHTML = html;
				repete.value = Array.prototype.some.call(repete.options, function (o) { return o.value === atual; }) ? atual : (atual === 'nenhuma' ? 'nenhuma' : 'personalizar');
				if (repete.value === 'personalizar' && atual !== 'personalizar') { sincronizar(); }
				$('[data-jpx-resumo]').textContent = j.descricao + (j.total ? ' — ' + j.total + ' ' + T.ocorrencias : '');
				desenharDatas(j.datas || []);
			}).catch(function () {});
		}, 250);
	}

	function desenharDatas(datas) {
		var ul = $('[data-jpx-datas]');
		ul.innerHTML = '';
		datas.forEach(function (d) {
			var e = exc[d.data];
			var li = document.createElement('li');
			li.className = e ? 'is-' + e.status : '';
			var info = '';
			if (e && e.status === 'cancelada') { info = '<em class="jpx-ed__selo jpx-ed__selo--erro">' + esc(T.cancelada) + '</em>' + (e.nota ? ' ' + esc(e.nota) : ''); }
			else if (e) { info = '<em class="jpx-ed__selo">' + esc(T.alterada) + '</em> ' + esc([e.local, e.hora_inicio, e.nota].filter(Boolean).join(' · ')); }
			li.innerHTML = '<strong>' + esc(d.rotulo) + '</strong><span class="jpx-ed__info">' + info + '</span><span class="jpx-ed__acoes">' +
				(e ? '<button type="button" class="button-link" data-acao="restaurar">' + esc(T.restaurar) + '</button>'
					: '<button type="button" class="button-link" data-acao="alterar">' + esc(T.alterar) + '</button><button type="button" class="button-link jpx-ed__perigo" data-acao="cancelar">' + esc(T.cancelar) + '</button>') +
				'</span>';
			li.setAttribute('data-data', d.data);
			ul.appendChild(li);
		});
	}

	$('[data-jpx-datas]').addEventListener('click', function (ev) {
		var b = ev.target.closest('[data-acao]');
		if (!b) { return; }
		var li = b.closest('li');
		var data = li.getAttribute('data-data');
		var acao = b.getAttribute('data-acao');
		if (acao === 'cancelar') { exc[data] = { status: 'cancelada' }; }
		if (acao === 'restaurar') { delete exc[data]; }
		if (acao === 'alterar') {
			li.querySelector('.jpx-ed__info').innerHTML =
				'<input type="text" data-c="local" placeholder="' + esc(T.local) + '">' +
				'<input type="time" data-c="hora_inicio" aria-label="' + esc(T.hora) + '">' +
				'<input type="text" data-c="nota" placeholder="' + esc(T.nota) + '">' +
				'<button type="button" class="button" data-acao="aplicar">' + esc(T.ok) + '</button>';
			li.querySelector('[data-c="local"]').focus();
			return;
		}
		if (acao === 'aplicar') {
			var e = { status: 'alterada' };
			$$('[data-c]', li).forEach(function (i) { e[i.getAttribute('data-c')] = i.value; });
			if (e.local || e.hora_inicio || e.nota) { exc[data] = e; }
		}
		excInput.value = JSON.stringify(exc);
		atualizarPrevia();
	});

	/* ---------- Prévia do card ---------- */
	var MES = C.meses || [];
	var DIA = C.dias || [];
	function previaCard() {
		var card = $('[data-jpx-card]');
		if (!card) { return; }
		var titulo = ($('#title') || {}).value || '';
		var tipo = form.querySelector('[name="jpx[tipo]"]:checked');
		var cor = tipo && tipo.getAttribute('data-cor') ? tipo.getAttribute('data-cor') : getComputedStyle(document.documentElement).getPropertyValue('--jpx-strong');
		var nomeTipo = tipo && tipo.value !== '0' ? tipo.parentNode.textContent.trim() : '';
		var d = inicio.value ? new Date(inicio.value + 'T12:00:00') : null;
		$('[data-jpx-card-capa]').style.background = cor;
		$('[data-jpx-card-num]').textContent = d ? String(d.getDate()).padStart(2, '0') : '';
		$('[data-jpx-card-tipo]').textContent = nomeTipo;
		$('[data-jpx-card-tipo]').style.color = cor;
		$('[data-jpx-card-tipo]').hidden = !nomeTipo;
		$('[data-jpx-card-mes]').textContent = d ? MES[d.getMonth()].toUpperCase() : '';
		$('[data-jpx-card-mes]').style.color = cor;
		$('[data-jpx-card-dia]').textContent = d ? String(d.getDate()).padStart(2, '0') : '';
		$('[data-jpx-card-titulo]').textContent = titulo || T.semNome;
		$('[data-jpx-card-titulo]').classList.toggle('is-vazio', !titulo);
		var quando = d ? DIA[d.getDay()].charAt(0).toUpperCase() + DIA[d.getDay()].slice(1) + ', ' + d.getDate() + ' de ' + MES[d.getMonth()] : '';
		if (marcado('jpx[dia_inteiro]')) { quando += ' · dia inteiro'; } else if (hi.value) { quando += ' · ' + hi.value + (hf.value ? '–' + hf.value : ''); }
		$('[data-jpx-card-quando]').textContent = quando;
		var loc = form.querySelector('[name="jpx[local_id]"]:checked');
		var nomeLocal = '';
		if (loc && loc.value === 'novo') { nomeLocal = ($('#jpx-ln') || {}).value || ''; }
		else if (loc && loc.value !== '0') { nomeLocal = loc.getAttribute('data-nome') || ''; }
		$('[data-jpx-card-local]').textContent = nomeLocal;
		var ok = { nome: !!titulo, data: !!inicio.value && (marcado('jpx[dia_inteiro]') || !!hi.value), local: !!nomeLocal };
		Object.keys(ok).forEach(function (k) { var li = $('[data-jpx-check="' + k + '"]'); if (li) { li.classList.toggle('is-ok', ok[k]); } });
	}

	/* ---------- Descrição recolhível ---------- */
	var desc = document.getElementById('postdivrich');
	var extras = $('[data-jpx-extras]');
	var btn = $('[data-jpx-expandir]');
	var temConteudo = (document.getElementById('content') || {}).value || ($('#jpx-resumo') || {}).value || ($('#jpx-prog') || {}).value;
	function expandir(abrir) {
		if (desc) { desc.hidden = !abrir; }
		if (extras) { extras.hidden = !abrir; }
		btn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
		if (abrir) { window.dispatchEvent(new Event('resize')); }
	}
	if (btn) {
		expandir(!!temConteudo);
		btn.addEventListener('click', function () { expandir(btn.getAttribute('aria-expanded') !== 'true'); });
	}

	/* ---------- Eventos ---------- */
	form.addEventListener('change', function (e) {
		sincronizar();
		var n = e.target.name || '';
		if (n === 'jpx[inicio]' || n === 'jpx[repete]' || n.indexOf('jpx[regra]') === 0) { atualizarPrevia(); }
	});
	form.addEventListener('input', function (e) {
		if (e.target.id === 'title' || e.target.id === 'jpx-ln') { previaCard(); }
	});
	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
			var b = document.getElementById('publish');
			if (b) { e.preventDefault(); b.click(); }
		}
	});

	sincronizar();
	atualizarPrevia();
	var t = document.getElementById('title');
	if (t && raiz.getAttribute('data-novo') === '1') { t.focus(); }
})();
