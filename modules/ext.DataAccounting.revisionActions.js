$( function() {
	function openDialog( dialog ) {
		var windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );

		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );
	}
	$( '#ca-da-delete-revisions' ).on( 'click', function() {
		openDialog(
			new da.ui.DeleteRevisionsDialog( { pageName: mw.config.get( 'wgPageName' ) } )
		);
	} );
	$( '#ca-da-squash-revisions' ).on( 'click', function() {
		openDialog(
			new da.ui.SquashRevisionsDialog( { pageName: mw.config.get( 'wgPageName' ) } )
		);
	} );
	$( '#ca-da-fork-page' ).on( 'click', function() {
		openDialog(
			new da.ui.ForkPageDialog( {
				revision: mw.config.get( 'wgCurRevisionId' ),
				source: mw.config.get( 'wgPageName' )
			} )
		);
	} );
} );