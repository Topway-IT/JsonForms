// use IIFE, this ensure name is scoped
(function () {
	function EnumProviders() {
	
/*
		this.providers = {
			jsonSchemas: this.jsonSchemas,
			contentModels: this.contentModels,
			jsonSlots: this.jsonSlots,
			slotRoles: this.slotRoles,
			contentModelByRole: this.contentModelByRole,
			metaSchemaFormatToInput: this.metaSchemaFormatToInput
		};
*/
	}

	EnumProviders.prototype.getTitlesInNamespace = function (nsId) {
		let cache = null;
		let pending = null;

		return async () => {
			if (cache) return cache;
			if (pending) return pending;

			const api = new mw.Api();

			pending = api
				.get({
					action: 'query',
					list: 'allpages',
					apnamespace: nsId,
					aplimit: 'max',
					formatversion: 2,
				})
				.then((res) => {
					cache = res.query.allpages.map((page) => {
						const titleObj = new mw.Title(page.title);
						const baseTitle = titleObj.getMainText();
						return {
							text: baseTitle,
							value: baseTitle,
						};
					});
					pending = null;
					return cache;
				});

			return pending;
		};
	};

	EnumProviders.prototype.metaSchemaFormatToInput = function () {
		return {
			source: (jseditor, { item, watched }) => {
				const format = watched['x-format'] || watched['format'];
				// console.log('watched', watched);

				switch (format) {
					case 'autocomplete':
						return ['autocomplete', 'LookupElement'];

					case 'captcha':
						return ['captcha'];

					case 'color':
						return ['ColorPicker'];

					case 'date-time':
						return ['mw.widgets.DateTimeInputWidget'];

					case 'time':
						return ['mw.widgets.DateTimeInputWidget'];

					case 'date':
						return ['mw.widgets.DateInputWidget'];

					case 'json':
						return ['JsonEditor', 'jsonForms'];

					case 'hidden':
						return ['OO.ui.HiddenInputWidget'];

					case 'month':
						return ['month'];

					case 'rating':
						return ['RatingWidget'];

					case 'stripe':
						return ['stripe'];

					case 'tel':
						return ['intl-tel-input'];

					case 'text':
						return [
							'mw.widgets.TitleInputWidget',
							'mw.widgets.UserInputWidget',
							'OO.ui.ButtonSelectWidget',
							'OO.ui.ComboBoxInputWidget',
							'OO.ui.DropdownInputWidget',
							'OO.ui.RadioSelectInputWidget',
							'OO.ui.TextInputWidget',
						];
	
					case 'email':
					case 'idn-email':
					case 'hostname':
					case 'idn-hostname':
					case 'ipv4':
					case 'ipv6':
					case 'uri':
					case 'uri-reference':
					case 'iri':
					case 'uri-template':
					case 'json-pointer':
					case 'relative-json-pointer':
					case 'regex':
						return [
							'OO.ui.TextInputWidget',
						];

					case 'textarea':
						return ['OO.ui.MultilineTextInputWidget'];

					case 'url':
						return ['OO.ui.TextInputWidget'];

					case 'textarea':
						return ['OO.ui.MultilineTextInputWidget'];
					case 'uuid':
						return ['uuid'];
					case 'week':
						return ['week'];

					case 'wikitext':
						return ['VisualEditor', 'WikiEditor'];
				}
			},
			filter: (jseditor, { item, watched }) => {
				return true;
			},
			title: (jseditor, { item, watched }) => item.text,
			value: (jseditor, { item, watched }) => item.value,
		};
	};

	EnumProviders.prototype.jsonSchemas = function () {
		return {
			source: async () => {
				const fetchFn = this.getTitlesInNamespace(2100);
				return await fetchFn();
			},
			filter: (jseditor, { item, watched }) => {
				return true;
			},
			title: (jseditor, { item, watched }) => item.text,
			value: (jseditor, { item, watched }) => item.value,
		};
	};

	EnumProviders.prototype.contentModelByRole = function () {
		const contentModels = mw.config.get('jsonforms')['contentModels'];
		const roleContentModelMap =
			mw.config.get('jsonforms')['roleContentModelMap'];
		// const roles = mw.config.get('jsonforms')['slotRoles'];

		// key/value object, this is also supported
		return {
			source: (jseditor, { item, watched }) => {
				const role = watched?.role || 'main';

				switch (role) {
					case 'main':
						return contentModels;

					default:
						const contentModel = roleContentModelMap[role];
						return { [contentModel]: contentModels[contentModel] };
				}
			},
		};
	};

	EnumProviders.prototype.slotRoles = function () {
		const roles = mw.config.get('jsonforms')['slotRoles'];

		return {
			source: () => roles,
		};
	};

	EnumProviders.prototype.jsonSlots = function () {
		const slots = ['main', ...mw.config.get('jsonforms')['jsonSlots']];

		return {
			source: () => slots,
		};
	};

	EnumProviders.prototype.contentModels = function () {
		const contentModels = mw.config.get('jsonforms')['contentModels'];

		// key/value object, this is also supported
		return {
			source: () => {
				return contentModels;
			},
		};
	};

	// attach instance
	JsonForms.enumProviders = new EnumProviders();
})();

