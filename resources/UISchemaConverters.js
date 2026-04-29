// use IIFE, this ensure name is scoped
(function () {
	function UISchemaConverters() {
		this.converters = {
			NewProperty: new JsonForms.UISchemaConverters.NewProperty(),
			survey: new JsonForms.UISchemaConverters.Survey(),
			field: new JsonForms.UISchemaConverters.Field(),
			subitem: new JsonForms.UISchemaConverters.Subitem(),
			geolocation: new JsonForms.UISchemaConverters.Geolocation(),
		};
	}

	JsonForms.UISchemaConverters = UISchemaConverters;
})();

