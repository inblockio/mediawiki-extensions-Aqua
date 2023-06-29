da = window.da || {};
da.ui = window.da.ui || {};

da.ui.ComparePanel = function ( config ) {
	config = config || {};
	this.changetype = config.tree[ 'change-type' ];
	this.tree = config.tree.tree || {};
	this.localId = config.target;
	this.remoteId = config.draft;
	da.ui.ComparePanel.super.call( this, $.extend( { expanded: false }, config ) );
};

OO.inheritClass( da.ui.ComparePanel, OO.ui.PanelLayout );

da.ui.ComparePanel.prototype.initialize = function () {
	this.treePanel = new da.ui.TreePanel( {
		tree: this.tree
	} );
	this.$element.append( this.treePanel.$element );
	this.treePanel.initialize();
};