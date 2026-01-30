SimpleDialog = function (config) {
	this.htmlMessage = config.htmlMessage || '';
	SimpleDialog.super.call(this, config);
};

OO.inheritClass(SimpleDialog, OO.ui.Dialog);
SimpleDialog.static.title = 'Non modal dialog';

SimpleDialog.prototype.initialize = function () {
	SimpleDialog.super.prototype.initialize.apply(this, arguments);
	this.content = new OO.ui.PanelLayout({ padded: true, expanded: false });

	// const message = new OO.ui.HtmlSnippet(this.htmlMessage )
	// this.content.$element.append(message.toString());
	this.content.$element.append($('<p>').html(this.htmlMessage));

	const closeButton = new OO.ui.ButtonWidget({
		label: OO.ui.msg('ooui-dialog-process-dismiss'),
	});

	closeButton.on('click', () => {
		this.close();
	});

	this.content.$element.append(closeButton.$element);
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

