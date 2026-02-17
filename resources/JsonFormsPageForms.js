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

	this.formDescriptor = data.formDescriptor;
}

OO.inheritClass(JsonFormsPageForm, JsonForms);

JsonFormsPageForm.prototype.onNavButton = function (editor) {
	const jsonEditor = editor.jsoneditor;
	const formEditor = jsonEditor.getEditor('root.form');
	const booklet = formEditor.editor_holder.layout;

	const validateButton = jsonEditor.getEditor('root.buttons.validate');
	const submitButton = jsonEditor.getEditor('root.buttons.submit');
	const gobackButton = jsonEditor.getEditor('root.buttons.goback');

	const innerformEditor = this.editor.getEditor('root.form.form');
	const innerEditor = innerformEditor.input.editor;
	
	switch (editor.key) {
		case 'submit':
			if (jsonEditor.validation_results.length || innerEditor.validation_results.length) {
				alert('there are errors');
			} else {
				this.submitForm();
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
JsonFormsPageForm.prototype.submitForm = function () {
	const formEditor = this.editor.getEditor('root.form.form');
	const innerEditor = formEditor.input.editor;
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

	const optionsEditor = this.editor.getEditor('root.form.options');

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value: innerEditor.getValue(),
		options: optionsEditor.getValue(),
		structuredValue,
		formDescriptor: this.formDescriptor,
		config: mw.config.get('jsonforms'),
		processor: 'PageForms'	//submit processor
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
				if (result.errors.length) {
					const config = {
						htmlMessage: mw.msg(
							'jsonforms-jsmodule-return-errors',
							result.errors.join(' ,'),
						),
						type: 'error'
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
						type: 'success'
					};
					const nonModalDialog = new NonModalDialog();
					nonModalDialog.open(config);
					this.editor.destroy();
					this.createDefaultEditor();
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

		console.log('data', data);

		const formDescriptor = data.formDescriptor;

		console.log('formDescriptor', formDescriptor);

		const adjustFormSchema = function (schema, formDescriptor) {
			const ret = structuredClone(schema);
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
	"popup_size": "medium",
	"css_class": "",
	"editor_options": "MediaWiki:DefaultEditorOptions",
	"editor_script": "MediaWiki:DefaultEditorScript",
	"width": "800px"
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

			// console.log('ret', ret);
			return ret;
		};

		// adjust form schema based on form descriptor
		data.schema = adjustFormSchema(data.schema, formDescriptor);

		console.log('data.schema', data.schema);

		const jsonForms = new JsonFormsPageForm(el, data);
		await jsonForms.initialize();

		jsonForms.defaultOptions.callbacks.button = {
			...(jsonForms.defaultOptions?.callbacks?.button ?? {}),

			...{
				outerFormNavButton: (editor) => {
					jsonForms.onNavButton(editor);
				},
			},
		};

		const editor = jsonForms.createDefaultEditor();

		console.log('editor', editor);
		console.log('editor.editors', editor.editors);

		editor.on('ready', async () => {
			const formEditor = editor.getEditor('root.form.form');
			console.log('formEditor', formEditor);

			// *** do something with the child editor if needed
			// const innerEditor = await formEditor.input.getEditor();
		});

		editor.on('buildComplete', () => {
			const optionsEditor = editor.getEditor('root.form.options');
			const validateButton = editor.getEditor('root.buttons.validate');
			const submitButton = editor.getEditor('root.buttons.submit');
			const gobackButton = editor.getEditor('root.buttons.goback');

			console.log('optionsEditor.schema.properties', optionsEditor.schema.properties);

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
	});
});

