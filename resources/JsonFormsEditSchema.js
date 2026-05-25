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

function JsonFormsEditSchema(el, data) {
	JsonFormsEditSchema.super.call(this, el, data);

	this.metadata = data.metadata;
	this.jsonformsConfig = mw.config.get('jsonforms');
	this.editTitle = data.editTitle;
}

OO.inheritClass(JsonFormsEditSchema, JsonForms);

JsonFormsEditSchema.prototype.onFormButton = function (action, editor) {
	switch (action) {
		case 'cancel':
			const url = mw.config
				.get('wgArticlePath')
				.replace('$1', mw.config.get('wgPageName'));

			window.location.href = url;
			break;

		case 'submit':
			const validationResults = this.editor.validate();
			console.log('validationResults', validationResults);

			if (validationResults.length) {
				JsonForms.Alert('there are errors');
				return;
			} else {
				this.submitForm().catch((err) => console.error('API error:', err));
			}
			break;
	}
};

// ***redefine enum provider and callbacks
JsonFormsEditSchema.prototype.initialize = async function () {
	await JsonFormsEditSchema.super.prototype.initialize.call(this);

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

	// this.defaultOptions.callbacks.template = {
	// 	...this.defaultOptions.callbacks.template,
	// 	...this.enumProviders,
	// };
};

JsonFormsEditSchema.prototype.submitForm = function () {
	const editorValue = this.editor.getValue();

	// console.log('editorValue', editorValue);

	const options = { title: this.editTitle };
	let value;
	const metadata = { ...editorValue.form.options };
	
	if ( editorValue.form.schema.selectedSchema ) {
		value = editorValue.form.schema.selectedSchema.editor;
		metadata.schemaName = editorValue.form.schema.selectedSchema.schemaName;
	}

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value,
		options,
		metadata,
		config: mw.config.get('jsonforms'),

		//submit processor
		processor: 'EditSchema',
	};

	console.log('data', data);

	const payload = {
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
					resolve(result);
					const nonModalDialog = new JsonForms.NonModalDialog();
					nonModalDialog.open(config);
				} else {
					if (result.returnUrl === window.location.href) {
						window.location.reload();
					} else {
						window.location.href = result.returnUrl;
					}
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

		const jsonForms = new JsonFormsEditSchema(el, data);

		await jsonForms.initialize();

		const editor = jsonForms.createDefaultEditor();

/*
		const textarea = $('<textarea>', {
			class: 'form-control',
			id: 'value',
			rows: 12,
			style: 'font-size: 12px; font-family: monospace;',
		});

		$(el).append(textarea);

		editor.on('change', () => {
			textarea.val(JSON.stringify(editor.getValue(), null, 2));
		});
*/
	});
});

