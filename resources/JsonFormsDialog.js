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

function JsonFormsDialog(config, callbacks, editor) {
	config = Object.assign({ size: 'large', classes: [ 'jsonforms-form-dialog' ] }, config);
	this.config = config;
	this.callbacks = callbacks;
	this.editor = editor;
	
	this.title = config.title

	JsonFormsDialog.super.call(this, config);

	this.open();
}

OO.inheritClass(JsonFormsDialog, OO.ui.ProcessDialog);
JsonFormsDialog.static.name = 'myDialog';
JsonFormsDialog.static.actions = [
	{
		action: 'done',
		flags: ['primary', 'progressive'],
		label: 'done',
		modes: ['done'],
	},
	{
		action: 'save',
		flags: ['primary', 'progressive'],
		label: 'save',
		modes: ['save'],
	},

	// https://gerrit.wikimedia.org/r/plugins/gitiles/oojs/ui/+/refs/heads/master/demos/classes/BookletDialog.js
	{
		action: 'cancel',
		label: 'Cancel',
		flags: ['safe', 'close'],
		modes: ['done', 'save'],
	},
];

// Customize the initialize() function to add content and layouts:
JsonFormsDialog.prototype.initialize = function () {
	JsonFormsDialog.super.prototype.initialize.call(this);
	this.callbacks.initialize(this);
};

JsonFormsDialog.prototype.getBodyHeight = function () {
	return this.content.$element.outerHeight(true);
};

JsonFormsDialog.prototype.getSetupProcess = function (data) {
	data = data || {};
	const self = this;
	
    
    
	return JsonFormsDialog.super.prototype.getSetupProcess
		.call(this, data)
		.next(function () {
			self.callbacks.setupProcess(this, data);
		}, this);
};

// Specify processes to handle the actions.
JsonFormsDialog.prototype.getActionProcess = function (action) {
	const ret = this.callbacks.actionProcess(
		this,
		JsonFormsDialog.super.prototype.getActionProcess,
		action,
	);
	if (ret) {
		return ret;
	}

	return JsonFormsDialog.super.prototype.getActionProcess.call(this, action);
};


JsonFormsDialog.prototype.getTeardownProcess = function (data) {
	return JsonFormsDialog.super.prototype.getTeardownProcess
		.call(this, data)
		.first(function () {
			// Perform any cleanup as needed
		}, this);
};

JsonFormsDialog.prototype.open = function () {
	// const windowManager = createWindowManager();
	const windowManager = new OO.ui.WindowManager();
	$(document.body).append(windowManager.$element);
	
	const myDialog = this;
	const title = this.title
	windowManager.addWindows([myDialog]);
	windowManager.openWindow(myDialog, { title }).opening.then((promise) => {
		this.callbacks.onOpen(this, promise);
	});
};
