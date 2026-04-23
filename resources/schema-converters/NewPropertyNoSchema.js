// use IIFE, this ensure name is scoped
(function () {
	function NewPropertyNoSchema(el, data) {
		JsonForms.UISchemaConverters.call(this);
	}

	OO.inheritClass(NewPropertyNoSchema, JsonForms.UISchemaConverters);

	NewPropertyNoSchema.prototype.onBeforeCreateItem = function (uiSchemaValue, UISchema) {
		return { key: uiSchemaValue.name, schema: UISchema, value: uiSchemaValue};
	};

	NewPropertyNoSchema.prototype.convertFrom = function (key, value) {
		return value;
	};
	
	NewPropertyNoSchema.prototype.convertTo = function (key, value) {
		return value;
	};

	NewPropertyNoSchema.prototype.schemaFromPseudoType = function (
		type,
		multiple,
		options,
	) {
		if (multiple) {
			let inputName = null;
			switch (type) {
				case 'text':
					inputName = 'tagmultiselect';
					break;
			}

			const options = { 'x-input': inputName };
			return {
				type: 'array',
				items: this.schemaFromPseudoType(type, false, options),
			};
		}

		switch (type) {
			case 'time':
			case 'email':
			case 'date':
				return { type: 'string', format: type, ...options };

			case 'text':
			case 'textarea':
			case 'tel':
			case 'url':
			case 'color':
			case 'datetime-local':
			case 'json':
				return { type: 'string', 'x-format': type, ...options };

			case 'number':
			case 'range':
				return { type: 'number' };

			case 'integer':
				return { type: 'integer' };

			case 'boolean':
				return { type: 'boolean' };

			case 'object':
			case 'subitem':
				return { type: 'object', additionalProperties: true };

			default:
				throw new Error(`Unsupported type: ${type}`);
		}
	};

	// attach to constructor
	JsonForms.UISchemaConverters.NewPropertyNoSchema = NewPropertyNoSchema;
})();
