function AutocompleteProviders() {
	this.providers = {
		wikipedia: this.wikipedia,
		dawa: this.dawa,
		wikidata: this.wikidata
	};
}

function stripHtml(str) {
	const tmp = document.createElement('div');
	tmp.innerHTML = str;
	return tmp.textContent || tmp.innerText || '';
}

function escapeHtml(str) {
	if (str === null || str === undefined) {
		return '';
	}

	return String(str)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

function renderByInputType(jseditor_editor, props, innerHtml) {
	const name = (jseditor_editor.inputOptions?.name || '').toLowerCase();

	switch (name) {
		case 'autocomplete':
			return ['<li ' + props + '>', innerHtml, '</li>'].join('');

		case 'lookupelement':
		default:
			return [
				'<div class="oo-ui-labelElement-label">',
				innerHtml,
				'</div>',
			].join('');
	}
}

// @source https://pmk65.github.io/jedemov2/dist/demo.html autocomplete demo, javascript tab
AutocompleteProviders.prototype.wikipedia = function () {
	return {
		search: (jseditor_editor, input) => {
			if (input.length < 3) {
				return Promise.resolve([]);
			}

			const url =
				'https://en.wikipedia.org/w/api.php' +
				'?action=query' +
				'&list=search' +
				'&format=json' +
				'&origin=*' +
				'&srsearch=' +
				encodeURIComponent(input);

			return fetch(url)
				.then((res) => res.json())
				.then((data) =>
					data.query && data.query.search ? data.query.search : [],
				);
		},

		getResultValue: (jseditor_editor, result) => {
			return result.title || '';
		},

		renderResult: (jseditor_editor, result, props) => {
			const title = escapeHtml(result.title || '');
			const snippet = escapeHtml(stripHtml(result.snippet || ''));

			const inner = [
				'<div class="wiki-title">',
				title,
				'</div>',
				snippet
					? '<div class="wiki-snippet"><small>' + snippet + '</small></div>'
					: '',
			].join('');

			return renderByInputType(jseditor_editor, props, inner);
		},
	};
};

// @source https://pmk65.github.io/jedemov2/dist/demo.html autocomplete demo, javascript tab
AutocompleteProviders.prototype.dawa = function () {
	return {
		search: (jseditor_editor, input) => {
			if (input.length < 3) {
				return Promise.resolve([]);
			}

			const url =
				'https://dawa.aws.dk/vejnavne/autocomplete' +
				'?q=' +
				encodeURIComponent(input);

			return fetch(url)
				.then((res) => res.json())
				.then((data) => (Array.isArray(data) ? data : []));
		},

		getResultValue: (jseditor_editor, result) => {
			return result.tekst || '';
		},

		renderResult: (jseditor_editor, result, props) => {
			const text = escapeHtml(result.tekst || '');
			const inner = ['<div class="wiki-title">', text, '</div>'].join('');
			return renderByInputType(jseditor_editor, props, inner);
		},
	};
};

AutocompleteProviders.prototype.wikidata = function () {
	return {
		search: (jseditor_editor, input) => {
			if (input.length < 3) {
				return Promise.resolve([]);
			}

			const url =
				'https://www.wikidata.org/w/api.php' +
				'?action=wbsearchentities' +
				'&language=en' +
				'&format=json' +
				'&origin=*' +
				'&search=' +
				encodeURIComponent(input);

			return fetch(url)
				.then((res) => res.json())
				.then((data) => data.search || []);
		},

		getResultValue: (jseditor_editor, result) => {
			// or result.label
			return result.id;
		},

		renderResult: (jseditor_editor, result, props) => {
			const label = escapeHtml(result.label || '');
			const desc = escapeHtml(result.description || '');
			const id = escapeHtml(result.id || '');

			const inner = [
				'<div class="wiki-title">',
				label,
				' <small class="muted">(',
				id,
				')</small>',
				'</div>',
				desc
					? '<div class="wiki-snippet"><small>' + desc + '</small></div>'
					: '',
			].join('');

			return renderByInputType(jseditor_editor, props, inner);
		},
	};
};

