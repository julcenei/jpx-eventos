/* JPX Eventos — front (sem dependências)
 * - Agenda: filtros, busca, abas, lista/calendário e "carregar mais" sem recarregar a página.
 * - Modal do calendário (<dialog>).
 * - Página do evento: mapa sob demanda, copiar link, compartilhar.
 * Tudo funciona sem JavaScript (links e formulário comuns); o script só melhora.
 */
(function () {
	'use strict';

	var CFG = window.JPX_EV || { rest: '', txt: {} };
	var T = CFG.txt || {};

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	var ICO = {
		cal: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		rel: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		loc: '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		pes: '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		rep: '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
		x: '<path d="M18 6L6 18M6 6l12 12"/>',
		ext: '<path d="M15 3h6v6M10 14L21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		mais: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4"/>'
	};
	function ico(n, t) {
		t = t || 18;
		return '<svg class="jpx-ico" width="' + t + '" height="' + t + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICO[n] + '</svg>';
	}

	/* ------------------------------------------------------------------
	 * Modal
	 * ---------------------------------------------------------------- */
	var modal = null;
	var origem = null;

	function abrirModal(d, de) {
		if (!modal) {
			modal = document.createElement('dialog');
			modal.className = 'jpx-ev jpx-modal';
			modal.setAttribute('aria-label', T.evento || 'Evento');
			document.body.appendChild(modal);
			modal.addEventListener('click', function (e) {
				if (e.target === modal) { modal.close(); }
				if (e.target.closest('[data-jpx-fechar]')) { modal.close(); }
			});
			modal.addEventListener('close', function () { if (origem) { origem.focus(); } });
		}
		origem = de || null;
		var estilo = d.c ? '--c:' + d.c + ';--c-soft:' + d.cs : '';
		var linhas = '';
		linhas += '<li>' + ico('cal') + '<span>' + esc(d.dl) + '</span></li>';
		if (d.h) { linhas += '<li>' + ico('rel') + '<span>' + esc(d.h) + '</span></li>'; }
		if (d.l) { linhas += '<li>' + ico('loc') + '<span>' + esc(d.l) + (d.m ? ' <a href="' + esc(d.m) + '" target="_blank" rel="noopener">' + esc(T.mapa) + '</a>' : '') + '</span></li>'; }
		if (d.n) { linhas += '<li>' + ico('pes') + '<span>' + esc(d.n) + '</span></li>'; }
		if (d.r) { linhas += '<li>' + ico('rep') + '<span>' + esc(d.r) + '</span></li>'; }
		var badges = (d.v ? '<span class="jpx-badge jpx-badge--cor">' + esc(d.v) + '</span>' : '') + (d.p ? '<span class="jpx-badge">' + esc(d.p) + '</span>' : '');
		modal.setAttribute('style', estilo);
		modal.innerHTML =
			'<div class="jpx-modal__capa"><div class="jpx-capa jpx-capa--gerada"><span class="jpx-capa__num" aria-hidden="true">' + esc(d.d) + '</span></div>' +
			(d.tp ? '<span class="jpx-tag"><span class="jpx-tag__ponto"></span>' + esc(d.tp) + '</span>' : '') +
			'<button type="button" class="jpx-modal__fechar" data-jpx-fechar aria-label="' + esc(T.fechar) + '">' + ico('x') + '</button></div>' +
			'<div class="jpx-modal__corpo">' +
			'<h2 class="jpx-modal__titulo" tabindex="-1" autofocus>' + esc(d.t) + '</h2>' +
			(d.nt ? '<p class="jpx-modal__nota">' + esc(d.nt) + '</p>' : '') +
			'<ul class="jpx-meta jpx-meta--grande">' + linhas + '</ul>' +
			(d.rs ? '<p class="jpx-modal__resumo">' + esc(d.rs) + '</p>' : '') +
			(badges ? '<div class="jpx-badges">' + badges + '</div>' : '') +
			'<div class="jpx-modal__acoes">' +
			(d.i ? '<a class="jpx-btn jpx-btn--cheio" href="' + esc(d.i) + '" target="_blank" rel="noopener">' + esc(T.inscrever) + ico('ext', 16) + '</a>' : '') +
			'<a class="jpx-btn jpx-btn--borda" href="' + esc(d.u) + '">' + esc(T.detalhes) + '</a>' +
			'<a class="jpx-btn jpx-btn--texto" href="' + esc(d.g) + '" target="_blank" rel="noopener">' + ico('mais') + esc(T.agenda) + '</a>' +
			'</div></div>';
		if (typeof modal.showModal === 'function') {
			modal.showModal();
		} else {
			window.location.href = d.u;
		}
	}

	document.addEventListener('click', function (e) {
		var el = e.target.closest('[data-jpx-evento]');
		if (!el || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
		var d;
		try { d = JSON.parse(el.getAttribute('data-jpx-evento')); } catch (err) { return; }
		if (typeof HTMLDialogElement === 'undefined') { return; }
		e.preventDefault();
		abrirModal(d, el);
	});

	/* ------------------------------------------------------------------
	 * Agenda
	 * ---------------------------------------------------------------- */
	function Agenda(raiz) {
		this.raiz = raiz;
		this.estado = JSON.parse(raiz.getAttribute('data-estado') || '{}');
		this.fixos = JSON.parse(raiz.getAttribute('data-fixos') || '{}');
		this.resultado = $('[data-jpx-resultado]', raiz);
		this.cal = $('[data-jpx-cal]', raiz);
		this.carregado = { lista: !!this.resultado.innerHTML.trim(), cal: !!this.cal.innerHTML.trim() };
		this.req = 0;
		this.ligar();
	}

	Agenda.prototype.params = function (extra) {
		var s = Object.assign({}, this.estado, extra || {});
		var p = new URLSearchParams();
		p.set('visao', s.visao);
		p.set('aba', s.aba);
		p.set('pag', s.pag || 1);
		if (s.tipo) { p.set('tipo', s.tipo); }
		if (s.nucleo) { p.set('nucleo', s.nucleo); }
		if (s.q) { p.set('q', s.q); }
		if (s.mes) { p.set('mes', s.mes); }
		if (this.fixos.tipo) { p.set('fixo_tipo', this.fixos.tipo); }
		if (this.fixos.nucleo) { p.set('fixo_nucleo', this.fixos.nucleo); }
		if (this.fixos.por_pagina) { p.set('por_pagina', this.fixos.por_pagina); }
		var base = new URL(window.location.href);
		['jpx_v', 'jpx_aba', 'jpx_tipo', 'jpx_nucleo', 'jpx_q', 'jpx_mes', 'jpx_pag'].forEach(function (k) { base.searchParams.delete(k); });
		p.set('base', base.toString());
		return p;
	};

	Agenda.prototype.atualizarUrl = function () {
		var u = new URL(window.location.href);
		var s = this.estado;
		var mapa = { jpx_v: s.visao === 'cal' ? 'cal' : '', jpx_aba: s.aba === 'ant' ? 'ant' : '', jpx_tipo: s.tipo, jpx_nucleo: s.nucleo, jpx_q: s.q, jpx_mes: s.visao === 'cal' ? s.mes : '' };
		Object.keys(mapa).forEach(function (k) {
			if (mapa[k]) { u.searchParams.set(k, mapa[k]); } else { u.searchParams.delete(k); }
		});
		u.searchParams.delete('jpx_pag');
		window.history.replaceState({}, '', u.toString());
	};

	Agenda.prototype.buscar = function (alvo, anexar) {
		var self = this;
		var id = ++this.req;
		var caixa = alvo === 'cal' ? this.cal : this.resultado;
		if (!anexar) { caixa.classList.add('is-carregando'); }
		var p = this.params(alvo === 'cal' ? { visao: 'cal' } : { visao: 'lista' });
		return fetch(CFG.rest + 'agenda?' + p.toString(), { credentials: 'same-origin' })
			.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
			.then(function (j) {
				if (id !== self.req) { return; }
				caixa.classList.remove('is-carregando');
				if (anexar) {
					var grade = $('.jpx-grade', self.resultado);
					if (!grade) {
						grade = document.createElement('div');
						grade.className = 'jpx-grade';
						self.resultado.insertBefore(grade, $('.jpx-mais', self.resultado));
					}
					grade.insertAdjacentHTML('beforeend', j.html);
					var mais = $('.jpx-mais', self.resultado);
					if (mais) { mais.hidden = !j.mais; }
				} else {
					caixa.innerHTML = j.html;
					self.carregado[alvo === 'cal' ? 'cal' : 'lista'] = true;
				}
				if (j.contagem) {
					$$('[data-jpx-n]', self.raiz).forEach(function (n) { n.textContent = j.contagem[n.getAttribute('data-jpx-n')]; });
				}
			})
			.catch(function () {
				caixa.classList.remove('is-carregando');
				if (!anexar) { caixa.innerHTML = '<p class="jpx-vazio">' + esc(T.erro) + '</p>'; }
			});
	};

	Agenda.prototype.recarregar = function () {
		this.estado.pag = 1;
		this.atualizarUrl();
		this.carregado = { lista: false, cal: false };
		this.buscar(this.estado.visao === 'cal' ? 'cal' : 'lista');
	};

	Agenda.prototype.ligar = function () {
		var self = this;
		var raiz = this.raiz;

		raiz.addEventListener('click', function (e) {
			var a;
			if ((a = e.target.closest('[data-jpx-visao]'))) {
				e.preventDefault();
				var v = a.getAttribute('data-jpx-visao');
				self.estado.visao = v;
				$$('[data-jpx-visao]', raiz).forEach(function (b) { b.setAttribute('aria-pressed', b === a || b.getAttribute('data-jpx-visao') === v ? 'true' : 'false'); });
				$('[data-jpx-parte="lista"]', raiz).hidden = v !== 'lista';
				$('[data-jpx-parte="cal"]', raiz).hidden = v !== 'cal';
				var hid = $('input[name="jpx_v"]', raiz);
				if (hid) { hid.value = v; }
				self.atualizarUrl();
				if (!self.carregado[v === 'cal' ? 'cal' : 'lista']) { self.buscar(v === 'cal' ? 'cal' : 'lista'); }
				return;
			}
			if ((a = e.target.closest('[data-jpx-aba]'))) {
				e.preventDefault();
				self.estado.aba = a.getAttribute('data-jpx-aba');
				$$('.jpx-abas [data-jpx-aba]', raiz).forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-jpx-aba') === self.estado.aba ? 'true' : 'false'); });
				self.estado.pag = 1;
				self.atualizarUrl();
				self.buscar('lista');
				return;
			}
			if ((a = e.target.closest('[data-jpx-tipo]'))) {
				e.preventDefault();
				self.estado.tipo = a.getAttribute('data-jpx-tipo');
				$$('[data-jpx-tipo]', raiz).forEach(function (b) { b.setAttribute('aria-pressed', b === a ? 'true' : 'false'); });
				self.recarregar();
				return;
			}
			if ((a = e.target.closest('[data-jpx-mais]'))) {
				e.preventDefault();
				self.estado.pag = (self.estado.pag || 1) + 1;
				self.buscar('lista', true);
				return;
			}
			if ((a = e.target.closest('[data-jpx-mes]'))) {
				e.preventDefault();
				self.estado.mes = a.getAttribute('data-jpx-mes');
				self.atualizarUrl();
				self.buscar('cal');
			}
		});

		var form = $('[data-jpx-filtros]', raiz);
		if (form) {
			var tempo;
			form.addEventListener('submit', function (e) { e.preventDefault(); });
			var q = $('input[name="jpx_q"]', form);
			if (q) {
				q.addEventListener('input', function () {
					clearTimeout(tempo);
					tempo = setTimeout(function () { self.estado.q = q.value.trim(); self.recarregar(); }, 350);
				});
			}
			var n = $('select[name="jpx_nucleo"]', form);
			if (n) {
				n.addEventListener('change', function () { self.estado.nucleo = n.value; self.recarregar(); });
			}
		}
	};

	/* ------------------------------------------------------------------
	 * Página do evento
	 * ---------------------------------------------------------------- */
	function paginaEvento() {
		$$('[data-jpx-mapa]').forEach(function (m) {
			var b = $('button', m);
			if (!b) { return; }
			b.addEventListener('click', function () {
				var f = document.createElement('iframe');
				f.src = m.getAttribute('data-jpx-mapa');
				f.loading = 'lazy';
				f.title = 'Mapa';
				f.referrerPolicy = 'no-referrer-when-downgrade';
				m.innerHTML = '';
				m.appendChild(f);
			});
		});
		$$('[data-jpx-copiar]').forEach(function (b) {
			b.addEventListener('click', function () {
				var url = b.getAttribute('data-jpx-copiar');
				var ok = function () {
					b.setAttribute('aria-label', T.copiado);
					b.title = T.copiado;
					b.classList.add('is-ok');
				};
				if (navigator.clipboard) { navigator.clipboard.writeText(url).then(ok); } else { window.prompt('', url); }
			});
		});
		if (navigator.share) {
			$$('[data-jpx-compartilhar]').forEach(function (b) {
				b.hidden = false;
				b.addEventListener('click', function () {
					navigator.share({ title: b.getAttribute('data-titulo'), url: b.getAttribute('data-jpx-compartilhar') }).catch(function () {});
				});
			});
		}
	}

	function iniciar() {
		$$('[data-jpx-agenda]').forEach(function (r) { new Agenda(r); });
		paginaEvento();
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', iniciar);
	} else {
		iniciar();
	}
})();
