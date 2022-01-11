( function( mw ) {
	$( function() {
		$( '#n-newpage' ).each( function() {
			$( this ).on( 'click', function( e ) {
				e.stopPropagation();
				e.preventDefault();

				var windowManager = new OO.ui.WindowManager();
				$( document.body ).append( windowManager.$element );

				var dialog = new da.ui.CreatePageDialog();
				windowManager.addWindows( [ dialog ] );
				windowManager.openWindow( dialog );
			} );
		} )
	} );
} )( mediaWiki );
