$( function () {
		var $cnt = $( '#da-specialinbox-compare' );
		if ( !$cnt.length ) {
			return;
		}
		var panel = new da.ui.ComparePanel( $cnt.data() );
		$cnt.append( panel.$element	);
		panel.initialize();
} );