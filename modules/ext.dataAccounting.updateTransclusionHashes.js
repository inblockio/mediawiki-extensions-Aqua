( function( mw ) {
	$( function() {
		$( '#transclusionResourceTable' ).find( '.da-included-resource-update' ).each( function() {
			$( this ).on( 'click', function( e ) {
				e.stopPropagation();
				e.preventDefault();

				const resourceKey = $( this ).attr( 'data-resource-key' );
				if ( !resourceKey ) {
					return;
				}
				const title = mw.config.get( 'wgPageName' )

				$.ajax( {
					method: 'POST',
					url: mw.util.wikiScript( 'rest' ) + '/data_accounting/transclusion/update_hash',
					data: {page_title: title, resource: resourceKey},
					contentType: "application/json",
					dataType: 'json'
				} ).done( function( response ) {
					console.log( response );
					window.location.reload();
				} ).fail( function( jgXHR, type, status ) {
					if ( type === 'error' ) {
						console.error( jgXHR.responseJSON || jgXHR.responseText );
					}
					console.error( "Could not update included resource" );
				} );
			} );
		} )
	} );
} )( mediaWiki );
