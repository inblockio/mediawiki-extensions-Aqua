$( function() {
	$( '#ca-da-delete-revisions' ).on( 'click', function() {
		var windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );

		var dialog = new da.ui.DeleteRevisionsDialog( { pageName: mw.config.get( 'wgPageName' ) } );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );
	} );
} );