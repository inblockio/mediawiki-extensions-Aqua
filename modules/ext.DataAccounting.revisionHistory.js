$( function() {
	var $cnt = $( '#da-revision-history' );
	if ( $cnt.length === 0 ) {
		return;
	}
	$cnt.css( 'position', 'relative' );
	da.ui.revisionTree.connectFromRawContainer( $cnt );

	var checkedRevs = [];
	var compareBtn = OO.ui.infuse( $( '#da-compare-button' ) );
	compareBtn.connect( this, {
		click: function() {
			var url = mw.util.getUrl( mw.config.get( 'wgPageName' ), {
				type: 'revision',
				diffmode: 'source',
				oldid: Math.min(...checkedRevs),
				diff: Math.max(...checkedRevs)
			} );
			window.location.href = url;
		}
	} );
	$cnt.find( 'input[name="da-revision-checkbox"]' ).prop( 'checked', false );
	$cnt.find( 'input[name="da-revision-checkbox"]' ).on( 'change', function( e ) {
		var $this = $( this ),
			isChecked = $this.prop( 'checked' );

		if ( isChecked ) {
			checkedRevs.push( $this.data( 'rev-id' ) );
		} else {
			checkedRevs = checkedRevs.filter( function( revId ) {
				return revId !== $this.data( 'rev-id' );
			} );
		}
		compareBtn.setDisabled( checkedRevs.length < 2 );
		if ( checkedRevs.length === 2 ) {
			// Disable all non-checked checkboxes
			$cnt.find( 'input[name="da-revision-checkbox"]' ).each( function() {
				var $checkbox = $( this );
				if ( checkedRevs.indexOf( $checkbox.data( 'rev-id' ) ) === -1 ) {
					$checkbox.prop( 'disabled', true );
				}
			} );
		} else {
			// enable all checkboxes
			$cnt.find( 'input[name="da-revision-checkbox"]' ).prop( 'disabled', false );
		}
	} );
} );