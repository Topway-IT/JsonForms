// use IIFE, this ensure name is scoped
(function () {
	function UISchemaConverters() {
		this.converters = {};
	}

	UISchemaConverters.prototype.initConverters = function () {
		this.converters = {
			newPropertyNoSchema: new this.constructor.NewPropertyNoSchema(),
			survey: new this.constructor.Survey(),
			field: new this.constructor.Field(),
			subitem: new this.constructor.Subitem(),
			geolocation: new this.constructor.Geolocation(),
		};
    
		return this;
	};


	// attach instance
	JsonForms.UISchemaConverters = UISchemaConverters;
})();

