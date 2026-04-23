// use IIFE, this ensure name is scoped

(function () {
	function Field() {
		JsonForms.UISchemaConverters.call(this);
	}

	OO.inheritClass(Field, JsonForms.UISchemaConverters);

	Field.prototype.onBeforeCreateItem = function (uiSchemaValue, UISchema) {
		return { key: uiSchemaValue.name, schema: UISchema, value: uiSchemaValue };
	};

	// value is a schema
	Field.prototype.convertFrom = function (key, value) {
		console.log('convertFrom');
		console.log('value', value);
		return {
			type: value.properties.type,
		};
	};

	Field.prototype.convertTo = function (key, value) {
		console.log('convertTo', value, this.UISchema);
		if (value.multiple) {
			// ...
		}

		return {
			type: 'object',
			'x-ui-name': 'field',
			properties: {
				type: {
					name: value.name,
					type: value.type,
				},
			},
		};
		/*
input: "text"
​multiple: false
​name: "aa"
​type: "string"
​visibility: "visible"
*/
	};

	// attach to constructor
	JsonForms.UISchemaConverters.Field = Field;
})();

