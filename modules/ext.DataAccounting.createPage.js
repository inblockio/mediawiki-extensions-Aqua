( function( mw ) {
	$( function() {
		var dialog = null;
		$( '#aqua-new-page' ).each( function() {
			$( this ).on( 'click', function( e ) {
				e.stopPropagation();
				e.preventDefault();

				if ( dialog ) {
					return;
				}
				var windowManager = new OO.ui.WindowManager();
				$( document.body ).append( windowManager.$element );

				dialog = new da.ui.CreatePageDialog();
				windowManager.addWindows( [ dialog ] );
				windowManager.openWindow( dialog ).closed.then( function() {
					dialog = null;
				} );
			} );
		} )
	} );
} )( mediaWiki );
