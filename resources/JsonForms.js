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

function JsonForms() {
	this.moduleCache = new Map();
	this.formDescriptor = {};
	this.editorOptions = {};
	this.jsoneditor = null;
	this.outerEditor = null;

	const enumProviders = new EnumProviders();
	this.enumProviders = this.getProviders(enumProviders);

	const autocompleteProviders = new AutocompleteProviders();
	this.autocompleteProviders = this.getProviders(autocompleteProviders);

	// @TODO add upload providers
}

JsonForms.prototype.getProviders = function (providerClass) {
	const ret = {};
	for (const provider in providerClass.providers) {
		const obj = providerClass.providers[provider].bind(providerClass)();
		for (const action in obj) {
			ret[provider + this.ucfirst(action)] = obj[action];
		}
	}

	return ret;
};

JsonForms.prototype.ucfirst = function (str) {
	if (typeof str !== 'string' || !str) return str;
	return str.charAt(0).toUpperCase() + str.slice(1);
};

// Fetch all page titles in a given namespace
// Fetch all page titles in a given namespace (without namespace prefix)

JsonForms.prototype.submitForm = function () {
	// console.log('this.jsoneditor.editors', this.jsoneditor.editors);

	const editors = {};
	const vars = {};
	// console.log('this.jsoneditor.editors', this.jsoneditor.editors);
	for (let path in this.jsoneditor.editors) {
		let editor = this.jsoneditor.editors[path];

		// console.log('editor', editor);
		// console.log('editor.isPrimitive()', editor.isPrimitive());
		if ('activeIndex' in editor) {
			editor = editor.editors[editor.activeIndex];
		}

		if (editor.isPrimitive()) {
			let parent = editor.parent;

			// Walk up to skip switch wrappers
			while (parent && parent.switcher) {
				parent = parent.parent;
			}

			// this will rely on obj removeEmpty and removeFalse if enabled
			const value = parent.editors[editor.key].getValue();

			if (!value) {
				continue;
			}

			const cleanPath = path.replace(/^root\./, '');

			// Check if parent's parent is an array
			let isArrayValue = false;
			if (parent && parent.parent && parent.parent.schema?.type === 'array') {
				isArrayValue = true;
			}

			editors[cleanPath] = {
				value: value,
				schema: editor.schema,
				pathNoIndex: editor.pathNoIndex.replace(/^root\./, ''),
				isArrayValue,
			};

			vars[cleanPath] = editors[cleanPath].value;
		}
	}

	const outerEditor =
		this.outerEditor.getEditor('root.form.options') ||
		this.outerEditor.getEditor('root.form.form.options');

	if (this.formDescriptor.pagename_formula) {
		const template = this.outerEditor.compileTemplate(
			this.formDescriptor.pagename_formula,
		);


		this.formDescriptor.pagename_formula = this.outerEditor.getTemplateResult(
			template,
			vars,
		);
	}

	const formData = outerEditor.getValue();

	const data = {
		value: this.jsoneditor.getValue(),
		editors,
		formDescriptor: this.formDescriptor,
		formValue: formData,
		config: mw.config.get('jsonforms'),
	};

	console.log('data', data);

	var payload = {
		data: JSON.stringify(data),
		action: 'jsonforms-submit-form',
	};

	// console.log('payload', payload);
	return new Promise((resolve, reject) => {
		new mw.Api()
			.postWithToken('csrf', payload)
			.done(function (thisRes) {
				console.log('thisRes', thisRes);
				let result = thisRes[payload.action].result;
				result = JSON.parse(result);				
				if (result.errors.length) {
					const config = {
						htmlMessage: mw.msg(
							'jsonforms-jsmodule-return-errors',
							result.errors.join(' ,')
						),
					};
					const nonModalDialog = new NonModalDialog();
					nonModalDialog.open(config);
				
				} else if (result.returnUrl) {
					if (result.returnUrl === window.location.href) {
						window.location.reload();
					} else {
						window.location.href = result.returnUrl;
					}
				} else {
					const config = {
						htmlMessage: mw.msg(
							'jsonforms-jsmodule-return-message',
							result.targetTitle,
							result.targetUrl,
						),
					};
					const nonModalDialog = new NonModalDialog();
					nonModalDialog.open(config);
				}
			})
			.fail(function (thisRes) {
				// eslint-disable-next-line no-console
				console.error('jsonforms-submit-form', thisRes);
				reject(thisRes);
			});
	});
};

JsonForms.prototype.UIFormEditorOptions = function () {
	return {
		show_errors: 'interaction',
		max_depth: 16,
		use_default_values: true,
		remove_empty_properties: false,
		remove_false_properties: false,
		template: 'default',
		callbacks: {
			button: {
				outerFormNavButton: (editor) => {
					const formEditor =
						'root.form.form' in editor.jsoneditor.editors
							? editor.jsoneditor.editors['root.form.form']
							: editor.jsoneditor.editors['root.form'];
					const booklet = formEditor.editor_holder.layout;

					const optionsEditor =
						this.outerEditor.getEditor('root.form.options') ||
						this.outerEditor.getEditor('root.options');
					const validateButton =
						this.outerEditor.getEditor('root.form.buttons.validate') ||
						this.outerEditor.getEditor('root.buttons.validate');
					const submitButton =
						this.outerEditor.getEditor('root.form.buttons.submit') ||
						this.outerEditor.getEditor('root.buttons.submit');
					const gobackButton =
						this.outerEditor.getEditor('root.form.buttons.goback') ||
						this.outerEditor.getEditor('root.buttons.goback');

					switch (editor.key) {
						case 'submit':
							if (this.outerEditor.validation_results.length) {
								alert('there are errors');
							} else {
								// the inner editor
								if (this.jsoneditor.validation_results.length === 0) {
									this.submitForm();
								} else {
									alert('there are errors');
								}
							}
							break;
						case 'goback':
							booklet.setPage('main');

							validateButton.theme.toggle(validateButton.container, true);
							submitButton.theme.toggle(submitButton.container, false);
							gobackButton.theme.toggle(gobackButton.container, false);

							break;
						case 'validate':
							{
								const res = editor.jsoneditor.getValue();

								// the inner editor
								if (this.jsoneditor.validation_results.length === 0) {
									booklet.setPage('options');
									validateButton.theme.toggle(validateButton.container, false);
									submitButton.theme.toggle(submitButton.container, true);
									gobackButton.theme.toggle(gobackButton.container, true);
								} else {
									alert('there are errors');
								}
							}

							break;
					}
				},
			},
		},
	};
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

// @credits https://medium.com/javascript-inside/safely-accessing-deeply-nested-values-in-javascript-99bf72a0855a
JsonForms.prototype.getNestedProp = function (path, obj) {
	return path.reduce((xs, x) => (xs && xs[x] ? xs[x] : null), obj);
};

JsonForms.prototype.removeArrayItem = function (arr, value) {
	const index = arr.indexOf(value);
	if (index !== -1) {
		arr.splice(index, 1);
	}
};

JsonForms.prototype.adjustOptionsSchema = function (targetSchema) {
	const ret = structuredClone(targetSchema);

	// console.log('targetSchema', targetSchema);
	// console.log('this.formDescriptor', this.formDescriptor);
	const options = ret.properties.form.properties.options.properties;

	const required = ret.properties.form.properties.options.required;

	if (this.formDescriptor.pagename_formula || this.formDescriptor.edit_page) {
		delete options.title;
		this.removeArrayItem(required, 'title');
	} else {
		required.push('title');
	}

	// the key is the form descriptor field
	// the value is the target schema
	const keyMap = {
		edit_data_slot: 'data_slot',
		edit_main_slot_content_model: 'main_slot_content_model',
		edit_main_slot_content: 'main_slot_content',
		edit_categories: 'categories',
	};

	for (const key in keyMap) {
		if (!this.formDescriptor[key]) {
			delete options[keyMap[key]];
		} else {
			// required.push(keyMap[key]);
		}
	}

	if (
		!this.formDescriptor['edit_main_slot_content_model'] &&
		this.formDescriptor['data_slot'] !== 'main'
	) {
		delete options['summary'];
		delete options['minor'];
	}

	// console.log('ret', ret);
	return ret;
};

JsonForms.prototype.uniqueID = function () {
	return Math.random().toString(16).slice(2);
};

JsonForms.prototype.createEditor = function (config) {
	$(config.el).html('');

	const editorOptions = config.editorOptions ?? {};

	editorOptions.callbacks ??= {};
	editorOptions.callbacks.template = {
		...this.enumProviders,
		...(editorOptions?.callbacks?.template ?? {}),
	};
	editorOptions.callbacks.autocomplete = {
		...this.autocompleteProviders,
		...(editorOptions?.callbacks?.autocomplete ?? {}),
	};

	JSONEditor.defaults.options = editorOptions;

	const editor = new JSONEditor(config.el, {
		theme: 'oojs',
		schema: config.schema,
		schemaName: config.schemaName,
		uiSchema: config.uiSchema,
		startval: config.startVal,
		// partialSchema: 'options',
		// show_errors: 'change',
		ajax: true,
		ajaxUrl: function (ref, fileBase) {
			const mwBaseUrl = mw.config.get('wgServer') + mw.config.get('wgScript');

			// console.log(' ajaxUrl fileBase', fileBase);
			// console.log(' ajaxUrl mwBaseUrl', mwBaseUrl);

			if (fileBase.indexOf(mwBaseUrl) === -1) {
				return ref;
			}

			return `${mwBaseUrl}?title=${ref}&action=raw&uid=${this.uniqueID}`;
		},
	});

	const textarea = $('<textarea>', {
		class: 'form-control',
		id: 'value',
		rows: 12,
		style: 'font-size: 12px; font-family: monospace;',
	});

	$(config.el).append(textarea);

	editor.on('change', () => {
		textarea.val(JSON.stringify(editor.getValue(), null, 2));
	});

	editor.on('ready', () => {});

	return editor;
};

JsonForms.prototype.loadSchema = function (schemaName) {
	if (!schemaName) {
		return Promise.reject('No schema name provided');
	}
	if (schemaName === 'undefined') {
		return Promise.reject('invalid name');
	}

	return new Promise((resolve, reject) => {
		fetch(mw.util.getUrl(`JsonSchema:${schemaName}`, { action: 'raw' }), {
			cache: 'no-store',
		})
			.then((res) => res.text())
			.then((text) => {
				try {
					const json = JSON.parse(text);
					resolve(json);
				} catch (error) {
					console.log('schemaName', schemaName);
					console.log('text', text);
					console.error('Failed to parse schema JSON:', error);
					reject(error);
				}
			})
			.catch((fetchError) => {
				console.error('Failed to fetch schema:', fetchError);
				reject(fetchError);
			});
	});
};

JsonForms.prototype.reloadSchema = function () {
	let editor_ = this.outerEditor.getEditor('root.schema');
	const schemaName = editor_.getValue();

	// console.log('schemaName', schemaName);

	if (!schemaName) {
		console.log('no schemaName');
		return;
	}

	const placeholderEditor = this.outerEditor.getEditor(
		'root.form.form.placeholder',
	);
	// console.log('schemaEditor', schemaEditor);

	editor_ = this.outerEditor.getEditor('root.uischema');
	let uiSchemaName;

	if (editor_) {
		uiSchemaName = editor_.getValue();
	}

	if (!editor_ || !uiSchemaName) {
		this.loadSchema(schemaName).then((schema) => {
			this.jsoneditor = this.createEditor({
				schemaName,
				schema,
				// startVal,
				el: placeholderEditor.container,
				editorOptions: this.editorOptions,
			});
		});

		return;
	}

	this.loadSchema(uiSchemaName).then((uiSchema) => {
		this.loadSchema(schemaName).then((schema) => {
			this.jsoneditor = this.createEditor({
				schemaName,
				schema,
				uiSchema,
				// startVal,
				el: placeholderEditor.container,
				editorOptions: this.editorOptions,
			});
		});
	});
};

JsonForms.prototype.getUISchema = function () {
	const schema = {
		type: 'object',
		options: {
			compact: true,
			cssClass: 'jsonforms-outerform',
		},
		required: ['form', 'buttons'],
		properties: {
			form: {
				type: 'object',
				options: {
					compact: true,
					cssClass: 'jsonforms-innerform',
					layout: {
						name: 'booklet',
						config: {
							outlined: false,
						},
					},
				},
				properties: {
					placeholder: {
						type: 'null',
						format: 'info',
					},
					options: {
						type: 'object',
						options: {
							compact: true,
							cssClass: 'jsonforms-innerform-options',
						},
						properties: {
							title: {
								type: 'string',
								minLength: 1,
								options: { input: { name: 'title' } },
							},

							// target slot for json data
							data_slot: {
								type: 'string',
								title: 'data slot',
								description: 'Target slot for form data',
								default: this.formDescriptor.default_slot,

								enumSource: [
									{
										source: 'jsonSlotsSource',
									},
								],
							},
							// content model of main slot,
							// only if target slot is different than main
							// and wikitext is enabled
							main_slot_content_model: {
								title: 'content model of main slot',
								// description: 'content model of main slot',
								type: 'string',
								default: 'wikitext',

								enumSource: [
									{
										source: 'contentModelsSource',
									},
								],

								options: {
									dependencies: {
										data_slot: { op: '!=', value: 'main' },
									},
								},
							},
							main_slot_content: {
								type: 'string',
								format: 'textarea',
								options: {
									dependencies: {
										data_slot: { op: '!=', value: 'main' },
									},
								},
							},
							categories: {
								type: 'array',
								items: {
									type: 'string',
									options: { input: { name: 'categorymultiselect' } },
								},
							},
							summary: {
								type: 'string',
								options: {
									dependencies: {
										data_slot: { op: '!=', value: 'main' },
									},
								},
							},
							minor: {
								type: 'boolean',
								description:
									'This is a <a target="_blank" href="https://www.mediawiki.org/wiki/Help:Minor_edit">minor edit</a>',
								options: {
									input: {
										name: 'checkbox',
									},
									dependencies: {
										data_slot: { op: '!=', value: 'main' },
									},
								},
							},
						},
						required: [],
					},
				},
			},
			buttons: {
				options: {
					compact: true,
					cssClass: 'jsonforms-outerform-buttons',
				},
				properties: {
					validate: {
						type: 'null',
						format: 'button',
						options: {
							input: {
								config: { flags: ['primary', 'progressive'] },
							},
							button: {
								action: 'outerFormNavButton',
							},
						},
					},
					submit: {
						type: 'null',
						format: 'button',
						options: {
							input: {
								config: { flags: ['primary', 'progressive'] },
							},
							button: {
								action: 'outerFormNavButton',
							},
						},
					},
					goback: {
						type: 'null',
						format: 'button',
						options: {
							input: {
								config: { icon: 'arrowPrevious' },
							},
							config: {},
							button: {
								action: 'outerFormNavButton',
							},
						},
					},
				},
			},
		},
	};

	const ret = this.adjustOptionsSchema(schema);
	const options = ret.properties.form.properties.options.properties;
	if (!Object.keys(options).length) {
		delete ret.properties.buttons.properties.validate;
		delete ret.properties.buttons.properties.goback;
	}

	return ret;
};

JsonForms.prototype.getUISchemaSpecial = function () {
	return {
		type: 'object',
		options: {
			cssClass: 'jsonforms-outerform',
			compact: true,
		},
		properties: {
			schema: {
				type: 'string',
				default: '',

				enumSource: [
					{
						source: 'jsonSchemasSource',
						filter: 'jsonSchemasFilter',
						title: 'jsonSchemasTitle',
						value: 'jsonSchemasValue',
					},
				],
			},
			/*
		uischema: {
			type: 'string',
			enum: schemas,
			default: '',
		},
		*/
			form: this.getUISchema(),
		},
	};
};

JsonForms.prototype.init = async function (el, schemas) {
	const data = $(el).data();

	$(el).html('');

	// console.log('data', data);
	this.formDescriptor = data.formData.formDescriptor;
	const schema = data.formData.schema;
	const schemaName = data.formData.schemaName;
	const startVal = data.formData.data;
	const editorOptionsStr = data.formData.editorOptions;

	// console.log('editorOptionsStr', editorOptionsStr);

	this.editorOptions = await this.getModule(editorOptionsStr);

	// console.log('myModule', myModule);

	// console.log('formDescriptor', formDescriptor);
	// console.log('schema', schema);

	// const optionsHolder = $(el).append('<div>');
	// const schemaHolder = $(el).append('<div>');

	const outerschema = Object.keys(schema).length
		? this.getUISchema()
		: this.getUISchemaSpecial();

	this.outerEditor = this.createEditor({
		schemaName: 'Form',
		el,
		schema: outerschema,
		editorOptions: this.UIFormEditorOptions(),
	});

	if (schema && Object.keys(schema).length) {
		this.outerEditor.on('ready', () => {
			editor =
				this.outerEditor.getEditor('root.form.placeholder') ||
				this.outerEditor.getEditor('root.form.form.placeholder');

			if (editor) {
				this.jsoneditor = this.createEditor({
					schemaName,
					el: editor.container,
					schema,
					startVal,
					editorOptions: this.editorOptions,
				});
			}
		});

		// console.log('this.outerEditor', this.outerEditor);
		this.outerEditor.on('buildComplete', () => {
			// const formEditor = this.outerEditor.getEditor('root.form.form' ) || this.outerEditor.getEditor( 'root.form')
			// const booklet = formEditor.editor_holder.layout;

			// console.log('booklet', booklet);

			const optionsEditor =
				this.outerEditor.getEditor('root.form.options') ||
				this.outerEditor.getEditor('root.options');
			const validateButton =
				this.outerEditor.getEditor('root.form.buttons.validate') ||
				this.outerEditor.getEditor('root.buttons.validate');
			const submitButton =
				this.outerEditor.getEditor('root.form.buttons.submit') ||
				this.outerEditor.getEditor('root.buttons.submit');
			const gobackButton =
				this.outerEditor.getEditor('root.form.buttons.goback') ||
				this.outerEditor.getEditor('root.buttons.goback');

			// console.log('optionsEditor', optionsEditor);

			if (Object.keys(optionsEditor.getValue()).length) {
				if (submitButton) {
					submitButton.theme.toggle(submitButton.container, false);
				}

				if (gobackButton) {
					gobackButton.theme.toggle(gobackButton.container, false);
				}
			} else {
				if (validateButton) {
					validateButton.theme.toggle(validateButton.container, false);
				}
			}

			if (gobackButton) {
				gobackButton.theme.toggle(gobackButton.container, false);
			}

			// booklet.setPage('options');
		});

		return;
	}

	this.outerEditor.on('ready', () => {
		this.outerEditor.watch('root.schema', () => {
			this.reloadSchema();
		});

		this.outerEditor.watch('root.uischema', () => {
			this.reloadSchema();
		});
	});

	this.outerEditor.on('buildComplete', () => {
		const schemaEditor = this.outerEditor.getEditor('root.schema');

		if (schemaEditor && !schemaEditor.getValue()) {
			const formEditor = this.outerEditor.getEditor(
				'root.form.form' || 'root.form',
			);
			const booklet = formEditor.editor_holder.layout;

			// console.log('booklet', booklet);

			const validateButton = this.outerEditor.getEditor(
				'root.form.buttons.validate' || 'root.buttons.validate',
			);

			if (validateButton) {
				validateButton.theme.toggle(validateButton.container, false);
			}

			const gobackButton = this.outerEditor.getEditor(
				'root.form.buttons.goback' || 'root.buttons.goback',
			);
			if (gobackButton) {
				//	gobackButton.theme.toggle(gobackButton.container, false)
			}

			booklet.setPage('options');
		}
	});
};

$(function () {
	const schemas = mw.config.get('jsonforms-schemas');
	// console.log('schemas', schemas);
	// console.log(' mw.config', mw.config);

	$('.jsonforms-form-wrapper').each(function (index, el) {
		const jsonForms = new JsonForms();
		jsonForms.init(el, schemas);
	});
});

