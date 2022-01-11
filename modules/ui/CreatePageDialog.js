
da = window.da || {};
da.ui = window.da.ui || {};

da.ui.CreatePageDialog = function ( config ) {
	da.ui.CreatePageDialog.super.call( this, config );
}
OO.inheritClass( da.ui.CreatePageDialog, OO.ui.ProcessDialog );

da.ui.CreatePageDialog.static.name = 'daCreatePage';
da.ui.CreatePageDialog.static.title = mw.message( 'da-ui-create-page-title' ).text();
da.ui.CreatePageDialog.static.actions = [
	{ action: 'create', label: mw.message( 'da-ui-create-page-create' ).text(), flags: 'primary' }
];

da.ui.CreatePageDialog.prototype.initialize = function () {
	da.ui.CreatePageDialog.super.prototype.initialize.apply( this, arguments );
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.pageSearch = new mw.widgets.TitleInputWidget( {
		$overlay: true
	} );

	var pagePickerLayout = new OO.ui.FieldLayout( this.pageSearch, {
		label: mw.message( 'da-ui-create-page-picker' ).text(),
		align: 'top'
	} );

	this.content.$element.append(
		pagePickerLayout.$element
	);
	this.$body.append( this.content.$element );
};

da.ui.CreatePageDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'create' ) {
		return new OO.ui.Process( function () {
			var pageName = this.pageSearch.getValue();
			if ( !pageName ) {
				return;
			}
			var title = new mw.Title( pageName );
			window.location.href = title.getUrl( { action: 'edit' } );
		}.bind( this ) );
	}
	return da.ui.CreatePageDialog.super.prototype.getActionProcess.call( this, action );
};
