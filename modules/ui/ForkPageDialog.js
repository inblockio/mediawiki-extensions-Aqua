da = window.da || {};
da.ui = window.da.ui || {};

da.ui.ForkPageDialog = function ( config ) {
	config = config || {};
	config.size = 'small';
	da.ui.ForkPageDialog.super.call( this, config );
	this.revisionId = config.revision;
	this.source = config.source;
};

OO.inheritClass( da.ui.ForkPageDialog, OO.ui.ProcessDialog );

da.ui.ForkPageDialog.static.name = 'daForkPageDialog';
da.ui.ForkPageDialog.static.title = mw.message( 'da-ui-forkpage-title' ).plain();
da.ui.ForkPageDialog.static.actions = [ {
	action: 'fork',
	label: mw.message( 'da-ui-singlepageexport-fork-label' ).plain(),
	flags: [ 'primary', 'progressive' ]
}, {
	label: mw.message( 'da-ui-singlepageexport-cancel-label' ).plain(),
	flags: 'safe'
} ];

da.ui.ForkPageDialog.prototype.initialize = function () {
	da.ui.ForkPageDialog.super.prototype.initialize.apply( this, arguments );
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.pageTitle = new OO.ui.TextInputWidget( {
		required: true,
		validate: function ( value ) {
			if ( value === '' ) {
				return false;
			}
			var title = mw.Title.newFromText( value );
			return !!title;
		}
	} );
	var layout = new OO.ui.FieldLayout( this.pageTitle, {
		label: mw.message( 'da-ui-forkpage-newpage-title' ).plain(),
		align: 'top'
	} );

	this.content.$element.append(
		layout.$element
	);
	this.$body.append( this.content.$element );
};

da.ui.ForkPageDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'fork' ) {
		return new OO.ui.Process( function () {
			var dfd = $.Deferred();
			this.pushPending();
			var targetTitle = mw.Title.newFromText( this.pageTitle.getValue() );
			this.pageTitle.getValidity().done( function () {
				da.api.forkPage( this.source, targetTitle.getPrefixedDb(), this.revisionId ).done( function () {
					window.location.href = targetTitle.getUrl();
				}.bind( this ) ).fail( function ( error ) {
					var errMsg = mw.message( 'da-ui-forkpage-error' ).plain();
					if ( error && error.error.message ) {
						errMsg = error.error.message;
					}
					this.popPending();
					dfd.reject(
						new OO.ui.Error( errMsg, { recoverable: false } )
					);
				}.bind( this ) );
			}.bind( this ) ).fail( function () {
				this.popPending();
				this.pageTitle.focus();
				this.pageTitle.setValidityFlag( false );
				dfd.reject();
			}.bind( this ) );
			return dfd.promise();
		}.bind( this ) );
	}
	return da.ui.ForkPageDialog.super.prototype.getActionProcess.call( this, action );
};

da.ui.ForkPageDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[0].scrollHeight;
	}
	return 100;
};

da.ui.ForkPageDialog.prototype.showErrors = function( errors ) {
	da.ui.ForkPageDialog.parent.prototype.showErrors.call( this, errors );
	this.updateSize();
};

da.ui.ForkPageDialog.prototype.hideErrors = function() {
	da.ui.ForkPageDialog.parent.prototype.hideErrors.call( this );
	this.close();
};
