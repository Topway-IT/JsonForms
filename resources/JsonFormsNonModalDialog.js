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
 
SimpleDialog = function (config) {
	this.config = config;
	SimpleDialog.super.call(this, config);
};

OO.inheritClass(SimpleDialog, OO.ui.Dialog);
SimpleDialog.static.title = 'Non modal dialog';

SimpleDialog.prototype.initialize = function () {
	SimpleDialog.super.prototype.initialize.apply(this, arguments);
	this.content = new OO.ui.PanelLayout({ padded: false, expanded: false });

	const messageWidget = new OO.ui.MessageWidget({
		type: this.config.type,
		label: new OO.ui.HtmlSnippet(this.config.htmlMessage),
		classes: [],
		showClose: true,
	});

	this.content.$element.append(messageWidget.$element);

	messageWidget.on('toggle', (visible) => {
		if (!visible) {
			this.close();
		}
	});

	// this.content.$element.append($('<p>').html(this.config.htmlMessage));

	// const closeButton = new OO.ui.ButtonWidget({
	// 	label: OO.ui.msg('ooui-dialog-process-dismiss'),
	// });

	// closeButton.on('click', () => {
	// 	this.close();
	// });

	// this.content.$element.append($('<p>'));
	// this.content.$element.append(closeButton.$element);
	this.$body.append(this.content.$element);
};

/*
SimpleDialog.prototype.getSetupProcess = function (data) {
	
	return SimpleDialog.super.prototype.getSetupProcess.call( this, data )
		.next( () => {
			// this.content.$element.empty();
			this.content.$element.append( data.html );
		} );
};
*/

SimpleDialog.prototype.getBodyHeight = function () {
	return this.content.$element.outerHeight(true);
};

NonModalDialog = function (config) {};

NonModalDialog.prototype.open = function (dialogConfig) {
	const manager = new OO.ui.WindowManager({
		modal: false,
		forceTrapFocus: true,
		classes: ['jsonforms-dialogs-non-modal'],
	});

	$(document.body).append(manager.$element);

	// const dialogConfig = {
	//	size: 'large'
	// };

	const name = 'window_nonmodaldialog';
	const windows = {};
	windows[name] = new SimpleDialog(dialogConfig);

	manager.addWindows(windows);

	manager.openWindow(name);
};

