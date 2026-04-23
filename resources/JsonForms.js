/**
 * This file is part of the MediaWiki extension JsonForms.
 *
 * JsonForms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * JsonForms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JsonForms. If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @author thomas-topway-it <support@topway.it>
 * @copyright Copyright ©2025-2026, https://wikisphere.org
 */

function JsonForms(el, data) {
	this.moduleCache = new Map();
	this.el = el;
	this.schema = data.schema;
	this.schemaName = data.schemaName;
	this.startval = data.startval;
	this.editor = null;

	this.data = data;
	// @TODO add upload providers
}

JsonForms.prototype.initialize = async function () {
	this.editorOptions = await this.getModule(this.data.editorOptions);
	this.editorScript = await this.getModule(this.data.editorScript);

	this.enumProviders = this.formatProviders(JsonForms.EnumProviders);
	this.autocompleteProviders = this.formatProviders(JsonForms.AutocompleteProviders);

	const UISchemaConverters = new JsonForms.UISchemaConverters();
	UISchemaConverters.initConverters();
	
	// console.log('defaultOptions',defaultOptions)

	const defaultOptions = this.editorOptions;

	// console.log('defaultOptions',defaultOptions)

	defaultOptions.callbacks ??= {};
	defaultOptions.callbacks.template = {
		...this.enumProviders,
		...(defaultOptions?.callbacks?.template ?? {}),
	};
	defaultOptions.callbacks.autocomplete = {
		...this.autocompleteProviders,
		...(defaultOptions?.callbacks?.autocomplete ?? {}),
	};
	
	defaultOptions.callbacks.ui_schema_converters = {
		...UISchemaConverters.converters,
		...(defaultOptions?.callbacks?.ui_schema_converters ?? {}),
	};

	this.defaultOptions = defaultOptions;
};

JsonForms.prototype.createDefaultEditor = function (config = {}) {
	this.createEditor(this.el, {
		JsonForms: this,
		schema: this.schema,
		schemaName: this.schemaName,
		startval: this.startval,
		...config,
	});

	return this.editor;
};

JsonForms.prototype.formatProviders = function (providersClass) {
	const ret = {};
	for (const provider in providersClass.providers) {
		const obj = providersClass.providers[provider].bind(providersClass)();
		for (const action in obj) {
			ret[provider + JsonForms.Utilities.ucfirst(action)] = obj[action];
		}
	}
	return ret;
};

// @see KnowledgeGraph.js
JsonForms.prototype.getModule = async function (str) {
	if (this.moduleCache.has(str)) {
		return this.moduleCache.get(str);
	}

	try {
		const module = await import(`data:text/javascript;base64,${btoa(str)}`);
		const result = module.default ?? null;
		this.moduleCache.set(str, result);
		return result;
	} catch (err) {
		console.error('Failed to load module:', err);
		return null;
	}
};

JsonForms.prototype.MWSchemaUrl = function (maybeUrl) {
	const mwBaseUrl = mw.config.get('wgServer') + mw.config.get('wgScript');
	return `${mwBaseUrl}?title=${maybeUrl}&action=raw`;
};

JsonForms.prototype.isMWSchema = function (maybeUrl, fileBase) {
console.log('config',mw.config)

	if (JsonForms.Utilities.hasProtocol(maybeUrl)) {
		return false;
	}
	if (!fileBase) {
		return true;
	}

	const mwBaseUrl = mw.config.get('wgServer') + mw.config.get('wgScript');
	return (
		fileBase.indexOf(mwBaseUrl) !== -1 || mwBaseUrl.indexOf(fileBase) !== -1
	);

};

JsonForms.prototype.fetchSchema = function (schema) {
	console.log('fetchSchema',schema)
	const payload = {
		action: 'jsonforms-fetch-schema',
		format: 'json',
		schema,
	};

	console.log('payload',payload)
	return new Promise((resolve, reject) => {
		new mw.Api().get(payload).done(function (thisRes) {
			// console.log('thisRes', thisRes);
			let result = thisRes[payload.action].result;
			result = JSON.parse(result);
			resolve(result);
		});
	}).catch((err) => {
		reject(err);
	});
};

JsonForms.prototype.getEditor = function () {
	return this.editor;
};

JsonForms.prototype.createEditor = function (el, config) {
	JFEditor.defaults.options = this.defaultOptions;

	this.editor = new JFEditor(el, {
		schemaSelector: null,
		...config,
		ajax: true,
		JsonForms: this,
	});

	if (typeof this.editorScript === 'function') {
		const updateEditorCallBack = (thisConfig) => {
			this.createEditor(this.el, { ...config, ...thisConfig });
		};
		this.editorScript(this.editor, this.config, updateEditorCallBack);
	}

	return this.editor;
};

$(function () {
	function resizeTreeSidePanel() {
		// const actualHeight = secondColumnContent.scrollHeight;

		const leftSelector =
			'.jsonforms-treewidget.oo-ui-menuLayout-showMenu .oo-ui-menuLayout-menu';
		const rightSelector =
			'.jsonforms-treewidget.oo-ui-menuLayout-showMenu .oo-ui-menuLayout-content';

		const $left = $(leftSelector);
		const $right = $(rightSelector);

		if (!$left[0] || !$right[0]) {
			return;
		}
		const $container = $('.container');

		const leftRect = $left[0].getBoundingClientRect();
		const containerRect = $right[0].getBoundingClientRect();

		const viewportHeight = $(window).height();
		const toViewport = viewportHeight - leftRect.top;
		const toContainer = containerRect.bottom - leftRect.top;
		let available = Math.min(toViewport, toContainer);
		available = Math.max(0, available);

		$left.css('max-height', available + 'px');
	}

	$(window).on('scroll resize', resizeTreeSidePanel);
	resizeTreeSidePanel();
});

