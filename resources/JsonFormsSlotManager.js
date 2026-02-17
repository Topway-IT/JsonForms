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
}

OO.inheritClass(JsonFormsSlotManager, JsonForms);

$(function () {
	// console.log(' mw.config', mw.config);
	const jsonformsConfig = mw.config.get('jsonforms');

	$('.jsonforms-form-wrapper').each(async function (index, el) {
		this.el = el;
		const data = $(el).data().formData;

		// console.log('data', data);

		const jsonForms = new JsonFormsSlotManager(el, data);
		await jsonForms.initialize();
		const editor = jsonForms.createDefaultEditor();

		const watchContentModel = (editor) => {
			const editorEditor = editor.parent.editors['editor'];

			// console.log('editorEditor', editorEditor);

			const contentModel = editor.getValue();
			// console.log('contentModel', contentModel);
			let options = ['source'];
			switch (contentModel) {
				case 'wikitext':
					options = ['source', 'VisualEditor'];
					break;

				case 'json':
					options = ['JsonForms', 'JSON Editor'];
					break;

				default:
					if (jsonformsConfig.jsonContentModels.includes(contentModel)) {
						options = ['JsonForms', 'JSON Editor'];
					}
			}

			editorEditor.setOptions(options);
		};

		const watchEditor = (editor) => {
			const contentEditor = editor.parent.editors['content'];
			
			if ( !contentEditor ) {
				return;
			}

			// console.log('contentEditor', contentEditor);

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
					};
					break;

				case 'source':
				default:
					options.format = 'textarea';
					options.input = {
						config: {
							autosize: true,
							rows: 6
						}
					};
			}
			
			// console.log('options', options);
			contentEditor.rebuildWithOptions(options);
		};

		editor.on('ready', async () => {
			const formEditor = editor.getEditor('root.form');
			// console.log('formEditor', formEditor);

			// *** do something with the child editor if needed
			const innerEditor = await formEditor.input.getEditor();

			innerEditor.watch('root.content_model', (editor) => {
				watchContentModel(editor);
			});

			// pathNoIndex
			innerEditor.watch('root.additional_slots.content_model', (editor) => {
				watchContentModel(editor);
			});

			innerEditor.watch('root.editor', (editor) => {
				watchEditor(editor);
			});

			// pathNoIndex
			innerEditor.watch('root.additional_slots.editor', (editor) => {
				watchEditor(editor);
			});
		});
	});
});
