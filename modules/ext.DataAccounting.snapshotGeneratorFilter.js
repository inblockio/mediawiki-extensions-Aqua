$( function() {
	var $div = $( '#daDomainSnapshotFilters' );
	if ( !$div.length ) {
		return;
	}
	var $form = $( 'form#daDomainSnapshot' );
	if ( !$form.length ) {
		return;
	}
	var $btn = $form.find( 'button[type="submit"]' );
	if ( !$btn.length ) {
		return;
	}
	var submitButton = new OO.ui.ButtonInputWidget( {
		type: 'submit',
		label: $btn.text(),
		framed: false
	} );
	submitButton.$element.find( 'a' ).css( {
		color: 'white',
	} ).addClass( 'btn btn-primary btn-lg btn-block' );

	var panel = new da.ui.SnapshotGeneratorFilterPanel( $form, submitButton );
	$div.html( panel.$element );
	$btn.replaceWith( submitButton.$element );
} );