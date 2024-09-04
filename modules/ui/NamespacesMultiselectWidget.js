da = window.da || {};
da.ui = window.da.ui || {};

da.ui.NamespacesMultiselectWidget = function ( config ) {
	da.ui.NamespacesMultiselectWidget.parent.call( this, config );
}

OO.inheritClass( da.ui.NamespacesMultiselectWidget,  mw.widgets.NamespacesMultiselectWidget );

/**
 * Add options to the menu, ensuring that they are unique by data.
 *
 * @param {Object[]} menuOptions Object defining options
 */
da.ui.NamespacesMultiselectWidget.prototype.addOptions = function ( menuOptions ) {
	var excludedNamespaces = this.getExcludedNamespaces();

	// Filter out excluded namespaces
	menuOptions = menuOptions.filter( function ( obj ) {
		return excludedNamespaces.indexOf( parseInt(obj.data) ) === -1;
	}, this );

	var widget = this,
		optionsData = [],
		items = [];

	menuOptions.forEach( function ( obj ) {
		if ( optionsData.indexOf( obj.data ) === -1 ) {
			optionsData.push( obj.data );
			items.push(
				widget.createMenuOptionWidget( obj.data, obj.label, obj.icon )
			);
		}
	} );

	this.menu.addItems( items );
};

/**
 * Get the namespaces that are excluded from the selector.
 * - DataAccounting 6942
 * - Form 106
 * - Gadget 2300
 * - Gadget definition 2302
 * - Special -1
 * - Template 10
 * - User 2
 * - Inbox 6900
 * - Category 14
 * - MediaWiki 8
 * - Module 828
 * - Help 12
 * and all talk namespaces (odd numbers)
 *
 * @return {number[]} Excluded namespace IDs
 */
da.ui.NamespacesMultiselectWidget.prototype.getExcludedNamespaces = function () {
	return [6942, 106, 2300, 2302, -1, 10, 2, 6900, 14, 8, 828, 12]
		.concat( Object.values( mw.config.get( 'wgNamespaceIds' ) ).filter( function ( ns ) {
			return ns % 2 === 1;
		} ) );
}
