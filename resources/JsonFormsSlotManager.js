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

function JsonFormsSlotManager(el, data) {
	JsonFormsSlotManager.super.call(this, el, data);

	this.editPage = data.editPage;
}

OO.inheritClass(JsonFormsSlotManager, JsonForms);

JsonFormsSlotManager.prototype.onFormButton = function (editor) {
	const innerformEditor = this.editor.getEditor('root.form');
	const innerEditor = innerformEditor.input.editor;

	switch (editor.key) {
		case 'submit':
			console.log(
				'innerEditor.validation_results',
				innerEditor.validation_results,
			);
			if (innerEditor.validation_results.length) {
				alert('there are errors');
			} else {
				this.submitForm();
			}
			break;
	}
};

// ***redefine enum provider and callbacks
JsonFormsSlotManager.prototype.initialize = async function () {
	await JsonFormsSlotManager.super.prototype.initialize.call(this);

	let roles = mw.config.get('jsonforms')['slotRoles'];
	roles = JFUtilities.removeArrayItem(roles, 'main');
	roles = JFUtilities.removeArrayItem(roles, 'jsonforms-metadata');

	this.enumProviders['slotRolesSource'] = () => roles;

	this.defaultOptions.callbacks.button = {
		...(this.defaultOptions?.callbacks?.button ?? {}),

		...{
			submitButton: (editor) => {
				this.onFormButton(editor);
			},
		},
	};
};

JsonFormsSlotManager.prototype.submitForm = function () {
	const formEditor = this.editor.getEditor('root.form');
	const innerEditor = formEditor.input.editor;
	// console.log('innerEditor', innerEditor);

	const vars = {};
	const structuredValue = innerEditor.getStructuredValue();
	// console.log('structuredValue', structuredValue);

	for (const path in structuredValue) {
		vars[path] = structuredValue[path].value;
	}

	const optionsEditor = this.editor.getEditor('root.form.options');

	// *** submission data are arbitrary and depend on the
	// SubmitProcessor
	const data = {
		value: innerEditor.getValue(),
		structuredValue,
		options: {
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
				if (result.errors.length) {
					const config = {
						htmlMessage: mw.msg(
							'jsonforms-jsmodule-return-errors',
							result.errors.join(' ,'),
						),
						type: 'error',
					};
					const nonModalDialog = new NonModalDialog();
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
	const jsonformsConfig = mw.config.get('jsonforms');

	$('.jsonforms-form-wrapper').each(async function (index, el) {
		this.el = el;
		const data = $(el).data().formData;

		console.log('data', data);

		const metadata = data.metadata;

		const jsonForms = new JsonFormsSlotManager(el, data);

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

		const watchContentModel = (editor, editorEditor) => {
			// console.log('editorEditor', editorEditor);

			const contentModel = editor.getValue();
			// console.log('contentModel', contentModel);
			let options = ['source'];
			switch (contentModel) {
				case 'wikitext':
					options = ['source', 'VisualEditor'];
					break;

				case 'json':
					options = ['JSON Editor', 'JsonForms'];
					break;

				default:
					if (jsonformsConfig.jsonContentModels.includes(contentModel)) {
						options = ['JSON Editor', 'JsonForms'];
					}
			}

			// console.log('options', options);
			editorEditor.setOptions(options);
		};

		const watchEditor = (role, editor, contentEditor) => {
			if (!contentEditor) {
				return;
			}

			let previousSchemaName = metadata?.slots?.[role]?.schema ?? null;

			if (typeof contentEditor.input?.editor?.getSchemaName === 'function') {
				previousSchemaName = contentEditor.input.editor.getSchemaName();
			}

			// without perefix
			if (typeof previousSchemaName === 'string') {
				previousSchemaName = previousSchemaName.includes(':')
					? previousSchemaName.split(':').slice(1).join(':')
					: previousSchemaName;
			}

			// console.log('previousSchemaName', previousSchemaName);

			const options = { compact: true };
			switch (editor.getValue()) {
				case 'VisualEditor':
					options.format = 'textarea';
					options.input = {
						name: 'visualeditor',
					};
					break;

				case 'JSON Editor':
					options.format = 'json';
					options.input = {
						name: 'JsonEditor',
					};
					break;

				case 'JsonForms':
					options.format = 'json';
					options.input = {
						name: 'JsonForms',
						config: {
							showSchemaSelector: true,
							schemaSelectorDefault: previousSchemaName,
						},
					};
					break;

				case 'source':
				default:
					options.format = 'textarea';
					options.input = {
						config: {
							autosize: true,
							rows: 6,
						},
					};
			}

			// console.log('options', options);
			contentEditor.rebuildWithOptions(options);
		};

		const watching = [];
		editor.on('ready', async () => {
			const formEditor = editor.getEditor('root.form');
			// console.log('formEditor', formEditor);

			// *** do something with the child editor if needed
			const innerEditor = await formEditor.input.getEditor();
			// console.log('innerEditor', innerEditor);

			const slotRoles = mw.config.get('jsonforms')['slotRoles'];

			innerEditor.on('change', async () => {
				const editors = innerEditor.getEditors();

				// assign watchers to new slots
				for (const path in editors) {
					// maybe role
					const role = path.replace(/^root\./, '');

					// on slot creation
					if (slotRoles.includes(role) && !watching.includes(path)) {
						// set role to hidden property

						const roleEditor = innerEditor.getEditor(`${path}.role`);
						roleEditor.setValue(role);

						innerEditor.watch(`${path}.content_model`, (editor) => {
							const editorEditor = innerEditor.getEditor(`${path}.editor`);
							watchContentModel(editor, editorEditor);
						});

						innerEditor.watch(`${path}.editor`, (editor) => {
							const contentEditor = innerEditor.getEditor(`${path}.content`);
							watchEditor(role, editor, contentEditor);
						});

						watching.push(path);
					}
				}
			});

			innerEditor.watch('root.content_model', (editor) => {
				const editorEditor = innerEditor.getEditor('root.editor');
				watchContentModel(editor, editorEditor);
			});

			// pathNoIndex
			// innerEditor.watch('root.additional_slots.content_model', (editor) => {
			// 	watchContentModel(editor);
			// });

			innerEditor.watch('root.editor', (editor) => {
				const contentEditor = innerEditor.getEditor('root.content');
				watchEditor('main', editor, contentEditor);
			});

			// pathNoIndex
			// innerEditor.watch('root.additional_slots.editor', (editor) => {
			// 	watchEditor(editor);
			// });
		});
	});
});

