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
	this.schemaName = data.name;
	this.startval = data.startval;
	this.editor = null;

	this.data = data;

	// @TODO add upload providers
}

JsonForms.prototype.initialize = async function () {
	this.editorOptions = await this.getModule(this.data.editorOptions);
	this.editorScript = await this.getModule(this.data.editorScript);

	const enumProviders = new EnumProviders();
	this.enumProviders = this.getProviders(enumProviders);

	const autocompleteProviders = new AutocompleteProviders();
	this.autocompleteProviders = this.getProviders(autocompleteProviders);

	const defaultOptions = this.editorOptions;

	defaultOptions.callbacks ??= {};
	defaultOptions.callbacks.template = {
		...this.enumProviders,
		...(defaultOptions?.callbacks?.template ?? {}),
	};
	defaultOptions.callbacks.autocomplete = {
		...this.autocompleteProviders,
		...(defaultOptions?.callbacks?.autocomplete ?? {}),
	};

	this.defaultOptions = defaultOptions;
};

JsonForms.prototype.createDefaultEditor = function () {
	this.createEditor(this.el, {
		schema: this.schema,
		schemaName: this.schemaName,
		startval: this.startval,
	});

	return this.editor;
};

JsonForms.prototype.getProviders = function (providerClass) {
	const ret = {};
	for (const provider in providerClass.providers) {
		const obj = providerClass.providers[provider].bind(providerClass)();
		for (const action in obj) {
			ret[provider + JFUtilities.ucfirst(action)] = obj[action];
		}
	}

	return ret;
};

// Fetch all page titles in a given namespace
// Fetch all page titles in a given namespace (without namespace prefix)

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

JsonForms.prototype.getEditor = function () {
	return this.editor;
};

JsonForms.prototype.createEditor = function (el, config) {
	JFEditor.defaults.options = this.defaultOptions;

	this.editor = new JFEditor(el, {
		ajax: true,
		ajaxUrl: function (ref, fileBase) {
			const mwBaseUrl = mw.config.get('wgServer') + mw.config.get('wgScript');
			if (
				fileBase.indexOf(mwBaseUrl) === -1 &&
				mwBaseUrl.indexOf(fileBase) === -1
			) {
				return ref;
			}
			return `${mwBaseUrl}?title=${ref}&action=raw&uid=${JFUtilities.uniqueID()}`;
		},
		schemaSelector: null,
		...config,
	});

	if (typeof this.editorScript === 'function') {
		const updateEditorCallBack = (thisConfig) => {
			this.createEditor(this.el, { ...config, ...thisConfig });
		};
		this.editorScript(this.editor, this.config, updateEditorCallBack);
	}

	return this.editor;
};

