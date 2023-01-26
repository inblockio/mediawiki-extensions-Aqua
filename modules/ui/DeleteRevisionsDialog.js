da = window.da || {};
da.ui = window.da.ui || {};

da.ui.DeleteRevisionsDialog = function ( config ) {
	this.name = 'delete-revisions';
	da.ui.DeleteRevisionsDialog.super.call( this, config );
};

OO.inheritClass( da.ui.DeleteRevisionsDialog, da.ui.RevisionDialog );

da.ui.DeleteRevisionsDialog.static.name = 'delete-revisions';
da.ui.DeleteRevisionsDialog.static.title = mw.message( 'da-ui-delete-revisions-title' ).text();
da.ui.DeleteRevisionsDialog.static.actions = [
	{ action: 'cancel', label: mw.message( 'da-ui-button-cancel' ).text(), flags: 'safe' },
	{
		action: 'delete', disabled: true,
		label: mw.message( 'da-ui-delete-revisions-delete' ).text(),
		flags: [ 'primary', 'destructive' ]
	}
];

da.ui.DeleteRevisionsDialog.prototype.configureInput = function () {
	this.revisionCounter.setRange( 1, this.availableRevisions.length - 1 );
	this.revisionCounter.setDisabled( false );
	this.revisionCounter.setValue( 1 );
	this.setPrimaryAbility( true );
	return true;
};

da.ui.DeleteRevisionsDialog.prototype.getPrimaryAction = function () {
	return 'delete';
};

da.ui.DeleteRevisionsDialog.prototype.getPrimaryActionProcess = function ( ) {
	var idsToDelete = this.selectedRevisions.map( function ( rev ) {
		return rev.rev_id;
	} );
	return new OO.ui.Process( function () {
		this.pushPending();
		da.api.deleteRevisions( idsToDelete )
		.done( function () {
			this.popPending();
			window.location.reload();
		}.bind( this ) );
	}.bind( this ) );
};
