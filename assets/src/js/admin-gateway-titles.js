(function () {
	'use strict';

	function parseJson(el, attr) {
		var raw = el.getAttribute(attr);
		if (!raw) return [];
		try {
			var v = JSON.parse(raw);
			return Array.isArray(v) ? v : [];
		} catch (e) {
			return [];
		}
	}

	function escapeHtml(s) {
		return (s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	function rowHtml(languages, selectedLang, text) {
		var opts = languages.map(function (l) {
			var sel = l.code === selectedLang ? ' selected="selected"' : '';
			return '<option value="' + escapeHtml(l.code || '') + '"' + sel + '>' + escapeHtml(l.label || '') + '</option>';
		}).join('');
		var textVal = escapeHtml(text);
		return (
			'<tr class="mondu-tt-row">' +
			'<td><select class="mondu-tt-lang">' + opts + '</select></td>' +
			'<td><input type="text" class="mondu-tt-text" value="' + textVal + '" /></td>' +
			'<td class="mondu-tt-remove"><button type="button" class="button mondu-tt-remove-btn">&times;</button></td>' +
			'</tr>'
		);
	}

	function collect(container) {
		var rows = [];
		container.querySelectorAll('.mondu-tt-row').forEach(function (tr) {
			var langSel = tr.querySelector('.mondu-tt-lang');
			var textInp = tr.querySelector('.mondu-tt-text');
			var lang = langSel ? langSel.value : '';
			var text = textInp ? textInp.value : '';
			rows.push({ lang: lang, text: text });
		});
		return rows;
	}

	function syncHidden(container) {
		var input = container.querySelector('.mondu-tt-input');
		if (!input) return;
		var rows = collect(container);
		try {
			input.value = JSON.stringify(rows);
		} catch (e) {}
	}

	function initBlock(block) {
		var languages = parseJson(block, 'data-languages');
		var initial = parseJson(block, 'data-initial');
		var tbody = block.querySelector('.mondu-tt-rows');
		var addBtn = block.querySelector('.mondu-tt-add');
		if (!tbody || !addBtn) return;

		initial.forEach(function (r) {
			var html = rowHtml(languages, r.lang, r.text);
			tbody.insertAdjacentHTML('beforeend', html);
		});

		function onChange() {
			syncHidden(block);
		}

		block.addEventListener('change', function (e) {
			if (e.target.closest('.mondu-tt-row')) onChange();
		});
		block.addEventListener('input', function (e) {
			if (e.target.closest('.mondu-tt-row')) onChange();
		});

		addBtn.addEventListener('click', function () {
			var html = rowHtml(languages, languages[0] ? languages[0].code : '', '');
			tbody.insertAdjacentHTML('beforeend', html);
			onChange();
		});

		block.addEventListener('click', function (e) {
			if (!e.target.classList.contains('mondu-tt-remove-btn')) return;
			var row = e.target.closest('.mondu-tt-row');
			if (row) {
				row.remove();
				onChange();
			}
		});

		onChange();
	}

	function init() {
		document.querySelectorAll('.mondu-title-translations').forEach(initBlock);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
