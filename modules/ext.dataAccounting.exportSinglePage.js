( function( mw ) {
	$( function() {
		$( '#ca-da-export' ).each( function() {
			$( this ).on( 'click', function( e ) {
				e.stopPropagation();
				e.preventDefault();

				new mw.Api().get( {
					action: 'da-export-page',
					page: mw.config.get( 'wgTitle' )
				} ).done( function( response ) {
					if ( !response.hasOwnProperty( 'da-export-page' ) ) {
						console.error( "Invalid API response" );
					}

				} ).fail( function() {

				} );
			} );
		} )
	} );
} )( mediaWiki );
