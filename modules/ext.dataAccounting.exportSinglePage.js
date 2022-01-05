( function( mw ) {
	$( function() {
		$( '#ca-da-export' ).each( function() {
			$( this ).on( 'click', function( e ) {
				e.stopPropagation();
				e.preventDefault();

				var windowManager = new OO.ui.WindowManager();
				$( document.body ).append( windowManager.$element );

				var dialog = new da.ui.SinglePageExportDialog( { pageName: mw.config.get( 'wgPageName' ) } );
				windowManager.addWindows( [ dialog ] );
				windowManager.openWindow( dialog );
			} );
		} )
	} );
} )( mediaWiki );
