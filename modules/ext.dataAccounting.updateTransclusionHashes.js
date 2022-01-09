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

        const payload = {page_title: title, resource: resourceKey}
        fetch(
          mw.util.wikiScript( 'rest' ) + '/data_accounting/transclusion/update_hash',
          {
            method: "POST",
            cache: 'no-cache',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
          }
        ).then((response) => {
            if (!response.ok) {
              console.log("update_hash not ok: ", response.status)
              return
            }
            window.location.reload()
        })
			} );
		} )
	} );
} )( mediaWiki );
