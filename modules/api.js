da = window.da || {};
da.api = {
	getAllPageRevisions: function( pagename, params ) {
		params = params || {};
		return da.api._internal.get( 'get_page_all_revs/' + pagename, params );
	},
	getHashChainInfo: function( identifier_type, identifier ) {
		return da.api._internal.get( 'get_hash_chain_info/' + identifier_type, { identifier: identifier } );
	},
	deleteRevisions: function( ids ) {
		return da.api._internal.post( 'delete_revisions', JSON.stringify( { ids: ids } ) );
	},
	_internal: {
		get: function( path, params ) {
			return da.api._internal._ajax( path, params );
		},
		post: function( path, params ) {
			return da.api._internal._ajax( path, params, 'POST' );
		},
		_requests: {},
		_ajax: function( path, data, method ) {
			data = data || {};
			var dfd = $.Deferred();

			da.api._internal._requests[path] = $.ajax( {
				method: method,
				url: mw.util.wikiScript( 'rest' ) + '/data_accounting/' + path,
				data: data,
				contentType: "application/json",
				dataType: 'json',
				beforeSend: function() {
					if ( da.api._internal._requests.hasOwnProperty( path ) ) {
						da.api._internal._requests[path].abort();
					}
				}.bind( this )
			} ).done( function( response ) {
				delete( da.api._internal._requests[path] );
				dfd.resolve( response );
			}.bind( this ) ).fail( function( jgXHR, type, status ) {
				delete( da.api._internal._requests[path] );
				if ( type === 'error' ) {
					dfd.reject( {
						error: jgXHR.responseJSON || jgXHR.responseText
					} );
				}
				dfd.reject( { type: type, status: status } );
			}.bind( this ) );

			return dfd.promise();
		}
	},
}