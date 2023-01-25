da = window.da || {};
da.ui = window.da.ui || {};

da.ui.DeleteRevisionsDialog = function ( config ) {
	da.ui.DeleteRevisionsDialog.super.call( this, config );
	this.pageName = config.pageName;
	this.availableRevisions = [];
	this.selectedRevisions = [];
};

OO.inheritClass( da.ui.DeleteRevisionsDialog, OO.ui.ProcessDialog );

da.ui.DeleteRevisionsDialog.static.name = 'daDeleteRevisions';
da.ui.DeleteRevisionsDialog.static.title = mw.message( 'da-ui-delete-revisions-title' ).text();
da.ui.DeleteRevisionsDialog.static.actions = [
	{ action: 'cancel', label: mw.message( 'da-ui-delete-revisions-cancel' ).text(), flags: 'safe' },
	{
		action: 'delete',
		label: mw.message( 'da-ui-delete-revisions-delete' ).text(),
		flags: [ 'primary', 'destructive' ]
	}
];

da.ui.DeleteRevisionsDialog.prototype.initialize = function () {
	da.ui.DeleteRevisionsDialog.super.prototype.initialize.apply( this, arguments );
	this.actions.setAbilities( { delete: false } );
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );

	this.appendLayout();
	this.$body.append( this.content.$element );
};

da.ui.DeleteRevisionsDialog.prototype.getBodyHeight = function () {
	return this.content.$element.outerHeight( true ) + 25;
};

da.ui.DeleteRevisionsDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( action === 'delete' ) {
		var idsToDelete = this.selectedRevisions.map( function ( rev ) {
			return rev.rev_id;
		} );
		return new OO.ui.Process( function () {
			dialog.pushPending();
			da.api.deleteRevisions( idsToDelete )
				.done( function () {
					dialog.popPending();
					window.location.reload();
				} );
		} );
	}
	if ( action === 'cancel' ) {
		return new OO.ui.Process( function () {
			dialog.close();
		} );
	}
	return da.ui.DeleteRevisionsDialog.super.prototype.getActionProcess.call( this, action );
};

da.ui.DeleteRevisionsDialog.prototype.appendLayout = function () {
	// Number of revisions to delete
	this.revisionsToDelete = new OO.ui.NumberInputWidget();
	this.revisionsToDelete.connect( this, {
		change: 'onRevisionsToDeleteChange'
	} );
	this.revisionsToDelete.setDisabled( true );

	this.content.$element.append(
		new OO.ui.FieldLayout( this.revisionsToDelete, {
			label: mw.message( 'da-ui-delete-revisions-number' ).text(),
		} ).$element
	);

	// Load available revisions
	this.pushPending();
	da.api.getAllPageRevisions( this.pageName, { full_entities: true } )
		.done( function( revisions ) {
			this.popPending();
			this.availableRevisions = revisions;
			if ( this.availableRevisions.length === 0 ) {
				return;
			}
			// Set limits, always leave at least one revision
			this.revisionsToDelete.setRange( 1, revisions.length - 1 );
			this.revisionsToDelete.setDisabled( false );
			this.revisionsToDelete.setValue( 1 );
		}.bind( this ) );
}

da.ui.DeleteRevisionsDialog.prototype.onRevisionsToDeleteChange = function ( value ) {
	value = parseInt( value );
	if ( value > this.availableRevisions.length ) {
		value = this.availableRevisions.length;
	}

	this.selectedRevisions = this.availableRevisions.slice( -value );

	var hasWitnessed = false;
	for ( var i = 0; i < this.selectedRevisions.length; i++ ) {
		if ( this.selectedRevisions[i].witness_event_id !== null ) {
			hasWitnessed = true;
		}
	}
	if ( this.deletionNotice ) {
		this.deletionNotice.$element.remove();
		this.deletionNotice = null;
	}
	if ( hasWitnessed && !this.witnessWarning ) {
		this.witnessWarning = new OO.ui.MessageWidget( {
			type: 'warning',
			label: mw.message( 'da-ui-delete-revisions-witness-warning' ).text()
		} );
		this.content.$element.prepend( this.witnessWarning.$element );
	} else if ( !hasWitnessed ) {
		if ( this.witnessWarning ) {
			this.witnessWarning.$element.remove();
			this.witnessWarning = null;
		}
		this.makeDeletionNotice();
		this.content.$element.append( this.deletionNotice.$element );
	}

	this.actions.setAbilities( { delete: !hasWitnessed } );
	this.updateSize();
};

da.ui.DeleteRevisionsDialog.prototype.makeDeletionNotice = function () {
	var $message = $( '<div>' ).html( mw.message( 'da-ui-delete-revisions-notice' ).text() );
	for( var i = 0; i < this.selectedRevisions.length; i++ ) {
		var url = mw.Title.newFromText( 'Special:Permalink/' + this.selectedRevisions[i].rev_id ).getUrl();
		$message.append(
			$( '<a>' )
				.css( 'margin-left', '10px' )
				.attr( 'href', url )
				.text( this.selectedRevisions[i].rev_id )
		);
	}
	this.deletionNotice = new OO.ui.MessageWidget( {
		type: 'notice',
		label: new OO.ui.HtmlSnippet( $message.html() )
	} );
};