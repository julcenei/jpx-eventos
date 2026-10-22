/* JPX Eventos — botões "?" com dica curta */
(function () {
	'use strict';
	function fecharTodas(exceto) {
		document.querySelectorAll('.jpx-dica__btn[aria-expanded="true"]').forEach(function (b) {
			if (b === exceto) { return; }
			b.setAttribute('aria-expanded', 'false');
			var bal = document.getElementById(b.getAttribute('aria-controls'));
			if (bal) { bal.hidden = true; }
		});
	}
	document.addEventListener('click', function (e) {
		var b = e.target.closest('.jpx-dica__btn');
		if (!b) {
			if (!e.target.closest('.jpx-dica__balao')) { fecharTodas(); }
			return;
		}
		e.preventDefault();
		var aberto = b.getAttribute('aria-expanded') === 'true';
		fecharTodas(b);
		b.setAttribute('aria-expanded', aberto ? 'false' : 'true');
		var bal = document.getElementById(b.getAttribute('aria-controls'));
		if (bal) { bal.hidden = aberto; }
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fecharTodas(); } });
})();
