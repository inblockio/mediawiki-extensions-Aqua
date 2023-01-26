da = window.da || {};
da.ui = window.da.ui || {};

da.ui.SquashRevisionsDialog = function ( config ) {
	this.name = 'squash-revisions';
	da.ui.SquashRevisionsDialog.super.call( this, config );
};

OO.inheritClass( da.ui.SquashRevisionsDialog, da.ui.RevisionDialog );

da.ui.SquashRevisionsDialog.static.name = 'squash-revisions';
da.ui.SquashRevisionsDialog.static.title = mw.message( 'da-ui-squash-revisions-title' ).text();
da.ui.SquashRevisionsDialog.static.actions = [
	{ action: 'cancel', label: mw.message( 'da-ui-button-cancel' ).text(), flags: 'safe' },
	{
		action: 'squash', disabled: true,
		label: mw.message( 'da-ui-squash-revisions-squash' ).text(),
		flags: [ 'primary', 'destructive' ]
	}
];

da.ui.SquashRevisionsDialog.prototype.getPrimaryAction = function () {
	return 'squash';
};

da.ui.SquashRevisionsDialog.prototype.configureInput = function () {
	this.revisionCounter.setRange( 2, this.availableRevisions.length );
	this.revisionCounter.setDisabled( false );
	this.revisionCounter.setValue( 2 );
};

da.ui.SquashRevisionsDialog.prototype.getPrimaryActionProcess = function ( ) {
	var idsToSquash = this.selectedRevisions.map( function ( rev ) {
		return rev.rev_id;
	} );
	return new OO.ui.Process( function () {
		this.pushPending();
		da.api.squashRevisions( idsToSquash )
		.done( function () {
			this.popPending();
			window.location.reload();
		}.bind( this ) );
	}.bind( this ) );
};