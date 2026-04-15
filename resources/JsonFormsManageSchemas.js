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

function JsonFormsManageSchemas(el, data) {
	JsonFormsManageSchemas.super.call(this, el, data);

	this.formDescriptor = data.formDescriptor;
	// console.log('this.schema', this.schema);
	// console.log('data', data);
	// console.log('this.schemaName', this.schemaName);
}

OO.inheritClass(JsonFormsManageSchemas, JsonForms);

// ***redefine enum provider and callbacks
JsonFormsManageSchemas.prototype.initialize = async function () {
	await JsonFormsManageSchemas.super.prototype.initialize.call(this);

	this.defaultOptions.callbacks.button = {
		...(this.defaultOptions?.callbacks?.button ?? {}),

		...{
			submitButton: (editor) => {
				this.onFormButton('submit', editor);
			},
			cancelButton: (editor) => {
				this.onFormButton('cancel', editor);
			},
		},
	};

	this.defaultOptions.callbacks.template = {
		...this.defaultOptions.callbacks.template,
		...this.enumProviders,
	};

	// console.log('this.defaultOptions', this.defaultOptions);

	this.schema = this.adjustFormSchema();
};

JsonFormsManageSchemas.prototype.onFormButton = function (action, editor) {
	const innerformEditor = this.editor.getEditor('root.editor');
	const innerEditor = innerformEditor.input.editor;

	switch (action) {
		case 'submit':
	/*
			console.log(
				'innerEditor.validation_results',
				innerEditor.validation_results,
			);
	*/
			if (innerEditor.validation_results.length) {
				alert('there are errors');
			}

			if ( innerEditor.getValue()['x-name'] !== innerEditor.getSchemaName() ) {
				if ( !confirm('This will rename the schema, ok ?' ) ) {
					return
				}			
			}
			this.submitForm(innerEditor).catch((err) =>
				console.error('API error:', err),
			);
			break;

		case 'cancel':
			alert('cancel');
			break;
	}
};

// adjust form schema based on form descriptor
JsonFormsManageSchemas.prototype.adjustFormSchema = function () {
	const formDescriptor = this.formDescriptor;
	const ret = structuredClone(this.schema);

	delete ret.properties.footer.properties.minor;
	delete ret.properties.footer.properties.summary;
	delete ret.properties.footer['x-css-class'];
	delete ret.properties.footer.properties.buttons['x-css-class'];

	return ret;
};

JsonFormsManageSchemas.prototype.submitForm = function (innerEditor) {
	// console.log('innerEditor', innerEditor);

	// console.log('innerEditor', innerEditor);

	const vars = {};
	const structuredValue = innerEditor.getStructuredValue();
	// console.log('structuredValue', structuredValue);

	for (const path in structuredValue) {
		vars[path] = structuredValue[path].value;
	}

	// console.log('vars', vars);

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
		// *** not necessary, but add more options here if needed
		// options: {
		// 	main_slot_content_model: 'json'
		// },
		structuredValue,
		formDescriptor: this.formDescriptor,
		config: mw.config.get('jsonforms'),

		//submit processor
		processor: 'ManageSchemas',
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

		const jsonForms = new JsonFormsManageSchemas(el, data);
		await jsonForms.initialize();

		const editor = await jsonForms.createDefaultEditor(editorConfig);

		// console.log('editor', editor);
		// console.log('editor.editors', editor.editors);

		const textarea = $('<textarea>', {
			class: 'form-control',
			id: 'value',
			rows: 12,
			style: 'font-size: 12px; font-family: monospace;',
		});
		$(el).append(textarea);

		const textareaB = $('<textarea>', {
			class: 'form-control',
			id: 'value',
			rows: 12,
			style: 'font-size: 12px; font-family: monospace;',
		});
		$(el).append(textareaB);

		editor.on('ready', async (editor_) => {
			// console.log('editor_', editor_);

			const formEditor = editor.getEditor('root.editor');
			// *** do something with the child editor if needed
			const innerEditor = await formEditor.input.getEditor();

			innerEditor.on('ready', () => {});

			innerEditor.on('change', () => {
				textarea.val(JSON.stringify(innerEditor.getValue(), null, 2));
				textareaB.val(
					JSON.stringify(Object.keys(innerEditor.editors), null, 2),
				);
			});
			innerEditor.on('ready', () => {
				textarea.val(JSON.stringify(innerEditor.getValue(), null, 2));
				textareaB.val(
					JSON.stringify(Object.keys(innerEditor.editors), null, 2),
				);
			});
		});
	});
});
