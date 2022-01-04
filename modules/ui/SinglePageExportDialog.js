
da = window.da || {};
da.ui = window.da.ui || {};

da.ui.SinglePageExportDialog = function ( config ) {
	da.ui.SinglePageExportDialog.super.call( this, config );
	this.pageName = config.pageName;
}
OO.inheritClass( da.ui.SinglePageExportDialog, OO.ui.ProcessDialog );

da.ui.SinglePageExportDialog.static.name = 'daSinglePageExport';
da.ui.SinglePageExportDialog.static.title = 'Verified export';
da.ui.SinglePageExportDialog.static.actions = [
	{ action: 'export', label: 'Export', flags: 'primary' },
	{ label: 'Cancel', flags: 'safe' }
];

da.ui.SinglePageExportDialog.prototype.initialize = function () {
	da.ui.SinglePageExportDialog.super.prototype.initialize.apply( this, arguments );
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );
	this.includeTrans = new OO.ui.ToggleSwitchWidget();
	this.depth = new OO.ui.NumberInputWidget( { min: 1, max: 10, value: 1 } );
	this.onlyLatest = new OO.ui.ToggleSwitchWidget;

	this.includeTrans.connect( this, {
		change: function( value ) {
			if ( value ) {
				depthLayout.$element.show();
			} else {
				depthLayout.$element.hide();
			}
			this.updateSize();
		}
	} );

	var includeTransLayout = new OO.ui.FieldLayout( this.includeTrans, {
		label: 'Include transcluded resources',
		align: 'top'
	} );
	var depthLayout = new OO.ui.FieldLayout( this.depth,  {
		label: 'Max depth for transcluded resources',
		align: 'top'
	} );
	depthLayout.$element.hide();
	var latestLayout = new OO.ui.FieldLayout( this.onlyLatest, {
		label: 'Include only latest revision of the page. If checked, only referenced revisions of transcluded resources will be exported',
		align: 'top'
	} );

	this.content.$element.append(
		includeTransLayout.$element, depthLayout.$element, latestLayout.$element
	);
	this.$body.append( this.content.$element );
};

da.ui.SinglePageExportDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'export' ) {
		return new OO.ui.Process( function () {
			console.log( this.pageName );
			window.location.href =
				mw.util.getUrl( 'Special:VerifiedExport/export', {
					titles: this.pageName,
					transclusions: this.includeTrans.getValue() ? 1 : 0,
					latest: this.onlyLatest.getValue() ? 1 : 0,
					depth: this.depth.getValue()
				} );
			this.close();
		}.bind( this ) );
	}
	return da.ui.SinglePageExportDialog.super.prototype.getActionProcess.call( this, action );
};
