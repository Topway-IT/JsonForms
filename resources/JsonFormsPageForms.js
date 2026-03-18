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
 * @copyright Copyright ©2026, https://wikisphere.org
 */

function JsonFormsPageForm(el, data) {
	JsonFormsPageForm.super.call(this, el, data);

	this.pageFormUI = mw.config.get('jsonforms').pageFormUI;

	this.formDescriptor = data.formDescriptor;
	// console.log('this.schema', this.schema);

	this.isPopup = this.formDescriptor.view === 'popup';
}

OO.inheritClass(JsonFormsPageForm, JsonForms);

// ***redefine enum provider and callbacks
JsonFormsPageForm.prototype.initialize = async function () {
	await JsonFormsPageForm.super.prototype.initialize.call(this);

	const defaultOptions = this.defaultOptions || {};

	this.defaultOptions = {
		...defaultOptions,
		callbacks: {
			...(defaultOptions.callbacks || {}),
			button: {
				...(defaultOptions.callbacks?.button || {}),
				outerFormNavButton: (editor) => {
					this.onNavButton(editor);
				},
			},
		},
	};

	this.schema = this.adjustFormSchema();
};

// adjust form schema based on form descriptor
JsonFormsPageForm.prototype.adjustFormSchema = function () {
	const formDescriptor = this.formDescriptor;
	const ret = structuredClone(this.schema);
	/*
default form descriptor
{
	"@type": "JsonForms default schema",
	"name": "Create/edit form",
	"schema": "CreatePageForm",
	"uischema": "",
	"edit_categories": false,
	"default_categories": [],
	"default_data_slot": "main",
	"edit_data_slot_role": false,
	"edit_main_slot_content_model": true,
	"edit_main_slot_content": false,
	"default_main_slot_content_model": "wikitext",
	"edit_page": "",
	"pagename_formula": "JsonForm:{{name}}",
	"create_only_fields": [
		"name",
		"edit_page"
	],
	"overwrite_existing_article_on_create": false,
	"view": "inline",
	"callback": "",
	"preload": "",
	"preload_data": "",
	"preload_data_separator": "",
	"return_page": "",
	"return_url": "",
	"start_path": "",
	"popup_size": "medium",
	"css_class": "",
	"editor_options": "MediaWiki:DefaultEditorOptions",
	"editor_script": "MediaWiki:DefaultEditorScript",
	"width": "800px",
	"captcha": true
}
*/
	// console.log('targetSchema', targetSchema);
	// console.log('formDescriptor', formDescriptor);
	const options = ret.properties.form.properties.options.properties;
	const required = ret.properties.form.properties.options.required;

	if (formDescriptor.pagename_formula || formDescriptor.edit_page) {
		delete options.title;
		JFUtilities.removeArrayItem(required, 'title');
	} else {
		required.push('title');
	}

	if (!formDescriptor.captcha) {
		delete ret.properties.captcha;
	} else {
		ret.properties.captcha.options.siteKey =
			mw.config.get('jsonforms').captchaSiteKey;
	}

	// the key is the form descriptor field
	// the value is the target schema
	const keyMap = {
		edit_data_slot_role: 'data_slot_role',
		edit_main_slot_content_model: 'main_slot_content_model',
		edit_main_slot_content: 'main_slot_content',
		edit_categories: 'categories',
	};

	if (formDescriptor['edit_main_slot_content_model']) {
		formDescriptor['edit_main_slot_content'] = true;
	}

	for (const key in keyMap) {
		if (!formDescriptor[key]) {
			delete options[keyMap[key]];
		} else {
			// required.push(keyMap[key]);
		}
	}

	if (!formDescriptor['edit_main_slot_content']) {
		delete options['summary'];
		delete options['minor'];
	}

	if (!Object.keys(options).length) {
		delete ret.properties.buttons.properties.validate;
		delete ret.properties.buttons.properties.goback;
	}

	this.hasOptions = Object.keys(options).length;
	return ret;
};

JsonForms.prototype.createDefaultEditor = async function (config = {}) {
	config = {
		...config,
		schema: this.schema,
		schemaName: this.schemaName,
		startval: this.startval,

		// the user-defined start_path is declared inside
		// the config object in the jsonform widget from php
		start_path: !this.isPopup ? '' : 'form.form',
	};
	if (!this.isPopup) {
		// this is returned as resolved promise
		// return JsonFormsPageForm.super.prototype.createDefaultEditor.call(this);
		return this.createEditor(this.el, config);
	}

	return await this.createDialog(config);
};

JsonForms.prototype.createDialog = async function (config) {
	let _resolveEditorReady = null;

	const callbacks = {
		initialize: async (dialog) => {
			const panelA = new OO.ui.PanelLayout({
				expanded: false,
				padded: false,
				framed: false,
				data: { name: 'editor' },
			});

			const el = document.createElement('div');
			const editor = this.createEditor(el, config);
			panelA.$element.append(el);

			const panelB = new OO.ui.PanelLayout({
				expanded: false,
				padded: false,
				framed: false,
				data: { name: 'options' },
			});

			// do not use this.createEditor to not mess with the editor
			const elOptions = document.createElement('div');
			const jsonForms = new JsonForms(elOptions, {
				schema: config.schema,
				startval: null,
				name: null,
			});
			dialog.optionsEditor = jsonForms.createEditor(elOptions, {
				start_path: 'form.options',
				schema: config.schema,
			});
			panelB.$element.append(elOptions);

			// expanded false is necessary to make getBodyHeight work
			const layout = new OO.ui.StackLayout({
				items: [panelA, panelB],
				expanded: false,
				continuous: false,
				padded: false,
				// The following classes are used here:
				// * PanelPropertiesStack
				// * PanelPropertiesStack-empty
				// classes: classes
			});

			dialog.content = dialog.layout = layout;

			dialog.$body.append(layout.$element);

			_resolveEditorReady(editor);
		},
		setupProcess: (dialog) => {
			const hasData = JFUtilities.getNestedProp(
				['form', 'form'],
				this.startval,
			);

			const mode =
				(this.hasOptions ? 'validate' : 'submit-single') +
				(!this.hasData ? '' : '-delete');

			dialog.actions.setMode(mode);
		},
		onOpen: () => {},
		actionProcess: (dialog, getActionProcess, action) => {
			const panels = dialog.layout.getItems();

			switch (action) {
				case 'back':
					dialog.layout.setItem(panels[0]);
					dialog.actions.setMode('validate' + (!this.hasData ? '' : '-delete'));
					return;

				case 'validate':
					{
						const innerformEditor = this.editor.getEditor('root');
						const innerEditor = innerformEditor.input.editor;

						if (innerEditor.validation_results.length) {
							alert('there are errors');
						} else {
							dialog.layout.setItem(panels[1]);
							dialog.setSize('medium');
							dialog.actions.setMode(
								'submit' + (!this.hasData ? '' : '-delete'),
							);
						}
					}
					return;

				case 'validate&submit':
				case 'submit':
				case 'delete': {
					const innerformEditor = this.editor.getEditor('root');
					const innerEditor = innerformEditor.input.editor;

					if (innerEditor.validation_results.length) {
						alert('there are errors');
					} else {
						return getActionProcess.call(this, action).next(() => {
							// return promise
							return this.submitForm(innerEditor, dialog.optionsEditor).then(
								(res) => {
									dialog.close({ action });
								},
							);
						});
					}
				}
			}
		},
	};

	const button = new OO.ui.ButtonWidget({
		label: this.formDescriptor.name,
		icon: 'edit',
		flags: [],
		classes: [],
	});

	button.on('click', () => {
		new JsonFormsDialog(
			{ size: this.formDescriptor.popup_size, title: this.formDescriptor.name },
			callbacks,
			this,
		);
	});

	$(this.el).empty().append(button.$element);

	return new Promise((resolve) => {
		_resolveEditorReady = (value) => {
			resolve(value);
		};
	});
};

JsonFormsPageForm.prototype.onNavButton = function (editor) {
	const jsonEditor = editor.jsoneditor;

	// console.log('this',this)
	// console.log('editor',editor)
	// console.log('jsonEditor',jsonEditor)

	const formEditor = jsonEditor.getEditor('root.form');
	const booklet = formEditor.editor_holder.layout;

	const validateButton = jsonEditor.getEditor('root.buttons.validate');
	const submitButton = jsonEditor.getEditor('root.buttons.submit');
	const gobackButton = jsonEditor.getEditor('root.buttons.goback');

	const innerformEditor = this.editor.getEditor('root.form.form');
	
	const innerEditor = innerformEditor.input.editor;

	switch (editor.key) {
		case 'submit':
			if (
				jsonEditor.validation_results.length ||
				innerEditor.validation_results.length
			) {
				// console.log('jsonEditor.validation_results',jsonEditor.validation_results)
				// console.log('innerEditor.validation_results',innerEditor.validation_results)
				alert('there are errors');
			} else {
				const optionsEditor = this.editor.getEditor('root.form.options');
				this.submitForm(innerEditor, optionsEditor).catch((err) =>
					console.error('API error:', err),
				);
			}
			break;
		case 'goback':
			booklet.setPage('main');
			validateButton.theme.toggle(validateButton.container, true);
			submitButton.theme.toggle(submitButton.container, false);
			gobackButton.theme.toggle(gobackButton.container, false);
			break;

		case 'validate': {
			// the inner editor
			if (innerEditor.validation_results.length === 0) {
				booklet.setPage('options');
				validateButton.theme.toggle(validateButton.container, false);
				submitButton.theme.toggle(submitButton.container, true);
				gobackButton.theme.toggle(gobackButton.container, true);
			} else {
				alert('there are errors');
			}
		}
	}
};

JsonFormsPageForm.prototype.submitForm = function (innerEditor, optionsEditor) {
	// console.log('innerEditor', innerEditor);

	const vars = {};
	const structuredValue = innerEditor.getStructuredValue();
	// console.log('structuredValue', structuredValue);

	for (const path in structuredValue) {
		vars[path] = structuredValue[path].value;
	}

	if (this.formDescriptor.pagename_formula) {
		const template = this.editor.compileTemplate(
			this.formDescriptor.pagename_formula,
		);

		this.formDescriptor.pagename_formula = this.editor.getTemplateResult(
			template,
			vars,
		);
	}

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value: innerEditor.getValue(),
		options: {
			...optionsEditor.getValue(),
			captcha: this.editor.getEditor('root.form.captcha'),
		},
		structuredValue,
		formDescriptor: this.formDescriptor,
		config: mw.config.get('jsonforms'),
		processor: 'PageForms', //submit processor
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
			.done((thisRes) => {
				console.log('thisRes', thisRes);
				let result = thisRes[payload.action].result;
				result = JSON.parse(result);
				if (result.errors && result.errors.length) {
					const config = {
						htmlMessage: mw.msg(
							'jsonforms-jsmodule-return-errors',
							result.errors.join(' ,'),
						),
						type: 'error',
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
						htmlMessage: result.message,
						type: 'success',
					};
					const nonModalDialog = new NonModalDialog();
					nonModalDialog.open(config);
					resolve(result);
					this.editor.destroy();
					this.createDefaultEditor().then((editor) => {});
				}
			})
			.fail(function (thisRes) {
				// eslint-disable-next-line no-console
				console.error('jsonforms-submit-form', thisRes);
				reject(thisRes);
			});
	});
};

$(function () {
	// console.log(' mw.config', mw.config);

	$('.jsonforms-form-wrapper').each(async function (index, el) {
		this.el = el;
		const data = $(el).data().formData;
		const editorConfig = data.editorConfig || {};
		console.log('data', data);

		const formDescriptor = data.formDescriptor;
		// console.log('formDescriptor', formDescriptor);

		// console.log('data.schema', data.schema);

		const jsonForms = new JsonFormsPageForm(el, data);
		await jsonForms.initialize();

		const editor = await jsonForms.createDefaultEditor(editorConfig);

		// console.log('editor', editor);
		// console.log('editor.editors', editor.editors);

		editor.on('ready', async () => {
			const formEditor = editor.getEditor('root.form.form');
			// console.log('formEditor', formEditor);

			// *** do something with the child editor if needed
			// const innerEditor = await formEditor.input.getEditor();
		});

		const isPopup = formDescriptor.view === 'popup';

		if (!isPopup) {
			editor.on('buildComplete', () => {
				const optionsEditor = editor.getEditor('root.form.options');
				const validateButton = editor.getEditor('root.buttons.validate');
				const submitButton = editor.getEditor('root.buttons.submit');
				const gobackButton = editor.getEditor('root.buttons.goback');

				/*
				console.log(
					'optionsEditor.schema.properties',
					optionsEditor.schema.properties,
				);
*/
				if (Object.keys(optionsEditor.schema.properties).length) {
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
		} else {
		}
	});
});

