document.addEventListener('DOMContentLoaded', function () {
	var cbs = document.querySelectorAll('.trd-article-cb');
	if (!cbs.length) return;

	var selAll = document.getElementById('trd-select-all');
	var countEl = document.getElementById('trd-sel-count');
	var btnSel = document.getElementById('trd-btn-selected');
	var btnSelText = document.getElementById('trd-btn-sel-text');
	var i18nEl = document.getElementById('trd-i18n-dlsel');
	var dlLabel = i18nEl ? i18nEl.textContent.trim() : 'Download Selected';

	function updateCount() {
		var n = document.querySelectorAll('.trd-article-cb:checked').length;
		countEl.textContent = n;
		btnSel.disabled = (n === 0);
		btnSelText.textContent = n > 0 ? dlLabel + ' (' + n + ')' : dlLabel;
		selAll.checked = (n === cbs.length && n > 0);

		for (var i = 0; i < cbs.length; i++) {
			var card = cbs[i].closest('.trd-card');
			if (card) {
				if (cbs[i].checked) {
					card.classList.add('trd-card--checked');
				} else {
					card.classList.remove('trd-card--checked');
				}
			}
		}
	}

	for (var i = 0; i < cbs.length; i++) {
		cbs[i].addEventListener('change', updateCount);
	}

	if (selAll) {
		selAll.addEventListener('change', function () {
			var checked = this.checked;
			for (var j = 0; j < cbs.length; j++) {
				cbs[j].checked = checked;
			}
			updateCount();
		});
	}
});
