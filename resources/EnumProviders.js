function EnumProviders() {
	this.providers = {
		jsonSchemas: this.jsonSchemas,
		contentModels: this.contentModels,
		jsonSlots: this.jsonSlots,
		slotRoles: this.slotRoles,
		contentModelByRole: this.contentModelByRole,
	};
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
	const roleContentModelMap = mw.config.get('jsonforms')['roleContentModelMap'];

	// key/value object, this is also supported
	return {
		source: (jseditor, { item, watched }) => {
			const roles = mw.config.get('jsonforms')['slotRoles'];
			const role = watched?.roleProperty || 'main';

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

