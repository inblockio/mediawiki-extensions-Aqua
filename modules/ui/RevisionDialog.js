da = window.da || {};
da.ui = window.da.ui || {};

da.ui.RevisionDialog = function ( config ) {
	da.ui.RevisionDialog.super.call( this, config );
	this.pageName = config.pageName;
	this.availableRevisions = [];
	this.selectedRevisions = [];
};

OO.inheritClass( da.ui.RevisionDialog, OO.ui.ProcessDialog );

da.ui.RevisionDialog.prototype.initialize = function () {
	da.ui.RevisionDialog.super.prototype.initialize.apply( this, arguments );
	this.setPrimaryAbility( false );
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.appendLayout();
	this.$body.append( this.content.$element );
};

da.ui.RevisionDialog.prototype.getBodyHeight = function () {
	return this.content.$element.outerHeight( true ) + 25;
};

da.ui.RevisionDialog.prototype.getPrimaryAction = function () {
	return '';
};

da.ui.RevisionDialog.prototype.getPrimaryActionProcess = function ( ) {
	return new OO.ui.Process( function () {} );
};

da.ui.RevisionDialog.prototype.setPrimaryAbility = function ( enabled ) {
	var actions = {};
	actions[this.getPrimaryAction()] = enabled;
	this.actions.setAbilities( actions );
};

da.ui.RevisionDialog.prototype.getReadyProcess = function ( data ) {
	return da.ui.RevisionDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			this.pushPending();
			da.api.getAllPageRevisions( this.pageName, { full_entities: true } )
			.done( function( revisions ) {
				this.popPending();
				this.availableRevisions = revisions;
				if ( this.availableRevisions.length < 2 ) {
					this.content.$element.append( new OO.ui.MessageWidget( {
						type: 'error',
						label: mw.message( 'da-ui-' + this.name + '-no-revisions' ).text()
					} ).$element );
					this.updateSize();
					return;
				}
				this.configureInput();
			}.bind( this ) );
		}, this );
};

da.ui.RevisionDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( action === this.getPrimaryAction() ) {
		return this.getPrimaryActionProcess();
	}
	if ( action === 'cancel' ) {
		return new OO.ui.Process( function () {
			dialog.close();
		} );
	}
	return da.ui.RevisionDialog.super.prototype.getActionProcess.call( this, action );
};

da.ui.RevisionDialog.prototype.appendLayout = function () {
	// Number of revisions to delete
	this.revisionCounter = new OO.ui.NumberInputWidget();
	this.revisionCounter.connect( this, {
		change: 'onRevisionCounterChange'
	} );
	this.revisionCounter.setDisabled( true );

	var layout = new OO.ui.FieldLayout( this.revisionCounter, {
		label: mw.message( 'da-ui-' + this.name + '-number' ).text(),
	} );
	layout.$element.find( '.oo-ui-fieldLayout-header' ).css( 'width', '70%' );
	layout.$element.find( '.oo-ui-fieldLayout-field' ).css( 'width', '30%' );
	layout.$element.css( 'margin-bottom', '20px' );
	this.content.$element.append( layout.$element );
};

da.ui.RevisionDialog.prototype.configureInput = function () {
	return true;
};

da.ui.RevisionDialog.prototype.onRevisionCounterChange = function ( value ) {
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
	if ( this.notice ) {
		this.notice.$element.remove();
		this.notice = null;
	}
	if ( hasWitnessed && !this.witnessWarning ) {
		this.witnessWarning = new OO.ui.MessageWidget( {
			type: 'warning',
			label: mw.message( 'da-ui-' + this.name + '-witness-warning' ).text()
		} );
		this.content.$element.prepend( this.witnessWarning.$element );
	} else if ( !hasWitnessed ) {
		if ( this.witnessWarning ) {
			this.witnessWarning.$element.remove();
			this.witnessWarning = null;
		}
		this.makeNotice();
		this.content.$element.append( this.notice.$element );
	}

	this.setPrimaryAbility( !hasWitnessed );
	this.updateSize();
};

da.ui.RevisionDialog.prototype.makeNotice = function () {
	var $message = $( '<div>' ).html( mw.message( 'da-ui-' + this.name + '-notice' ).text() );
	for( var i = 0; i < this.selectedRevisions.length; i++ ) {
		var url = mw.Title.newFromText( 'Special:Permalink/' + this.selectedRevisions[i].rev_id ).getUrl();
		$message.append(
			$( '<a>' )
			.css( 'margin-left', '10px' )
			.attr( 'href', url )
			.text( this.selectedRevisions[i].rev_id )
		);
	}
	this.notice = new OO.ui.MessageWidget( {
		type: 'notice',
		label: new OO.ui.HtmlSnippet( $message.html() )
	} );
};