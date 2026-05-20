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

function JsonFormsNewArticle(el, data) {
	JsonFormsNewArticle.super.call(this, el, data);

	this.jsonformsConfig = mw.config.get('jsonforms');
	this.editPage = data.editPage;
}

OO.inheritClass(JsonFormsNewArticle, JsonForms);

JsonFormsNewArticle.prototype.onFormButton = function (action, editor) {};

JsonFormsNewArticle.prototype.editorByContentModelSource = function (
	editor,
	{ item, watched },
) {
	// console.log('editorByContentModelSource',editor)
	// console.log('watched',watched)

	if (!('content_model' in watched)) {
		console.warn('contentModelProperty not set in watch', watched);
		return;
	}

	const jsonformsConfig = mw.config.get('jsonforms');

	const contentModel = watched?.content_model || 'wikitext';

	let options = ['source'];
	switch (contentModel) {
		case 'wikitext':
			options = { wikieditor: 'WikiEditor', visualeditor: 'VisualEditor' };
			break;

		default:
			if (jsonformsConfig.jsonContentModels.includes(contentModel)) {
				// , 'JsonForms',  codeeditor: 'codeEditor',
				options = { jsoneditor: 'JSON Editor' };
			}
	}

	// @TODO complete codeEditor widget
	//delete options.codeeditor;

	// console.log('options', options);
	return options;
};

// ***redefine enum provider and callbacks
JsonFormsNewArticle.prototype.initialize = async function () {
	await JsonFormsNewArticle.super.prototype.initialize.call(this);

	let roles = mw.config.get('jsonforms')['slotRoles'];
	roles = JsonForms.Utilities.removeArrayItem(roles, 'main');
	roles = JsonForms.Utilities.removeArrayItem(roles, 'jsonforms-metadata');

	this.enumProviders.slotRoles = () => {
		return {
			source: () => roles,
		};
	};

	this.enumProviders.editorByContentModel = () => {
		return {
			source: this.editorByContentModelSource,
		};
	};

	this.defaultOptions.callbacks.button = {
		...(this.defaultOptions?.callbacks?.button ?? {}),
		...{
			submitButton: (editor) => {
				this.onFormButton('submit', editor);
			},
		},
	};
};

JsonFormsNewArticle.prototype.submitForm = function () {
	const formEditor = this.editor.getEditor('root.editor');
	const innerEditor = formEditor.input.editor;
	// console.log('innerEditor', innerEditor);

	const vars = {};
	const structuredValue = innerEditor.getStructuredValue();
	// console.log('structuredValue', structuredValue);

	for (const path in structuredValue) {
		vars[path] = structuredValue[path].value;
	}

	const optionsEditor = this.editor.getEditor('root.editor.options');

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value: innerEditor.getValue(),
		structuredValue,
		options: {
			...this.editor.getEditor('root.footer').getValue(),
			editPage: this.editPage,
		},
		config: mw.config.get('jsonforms'),
		processor: 'SlotManager', //submit processor
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
	$('.jsonforms-form-wrapper').each(async function (index, el) {
		this.el = el;
		const data = $(el).data().formData;

		// console.log('data', data);

		const jsonForms = new JsonFormsNewArticle(el, data);

		await jsonForms.initialize();

		const editor = jsonForms.createDefaultEditor();

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
	});
});
// console.log(' mw.config', mw.config);

