da = window.da || {};
da.ui = window.da.ui || {};

da.ui.TreePanel = function ( config ) {
	config = config || {};
	this.tree = config.tree || {};
	this.nodes = {};
	da.ui.TreePanel.super.call( this, $.extend( { expanded: false }, config ) );
};

OO.inheritClass( da.ui.TreePanel, OO.ui.PanelLayout );

da.ui.TreePanel.prototype.initialize = function () {
	for ( var hash in this.tree ) {
		if ( !this.tree.hasOwnProperty( hash ) ) {
			continue;
		}
		var node = this.makeNode( this.tree[ hash ], hash );
		this.addNode( node );
	}

	this.connect();
};

da.ui.TreePanel.prototype.makeNode = function ( node, hash ) {
	return new da.ui.TreeNode( hash, node );
};

da.ui.TreePanel.prototype.addNode = function ( node ) {
	this.nodes[node.getHash()] = node;
	this.$element.append( node.$element );
};

da.ui.TreePanel.prototype.connect = function () {
	da.ui.revisionTree.connectFromNodes( this.nodes, this.$element );
};

da.ui.TreePanel.prototype.selectBranch = function ( branch ) {
	this.deselectBranches( false );
	if ( branch === 'remote' ) {
		this.$element.find( '.da-compare-node-graph-local[diff="true"]' ).addClass( 'da-compare-node-graph-ignored' );
	} else if ( branch === 'local' ) {
		this.$element.find( '.da-compare-node-graph-remote' ).addClass( 'da-compare-node-graph-ignored' );
	}
	this.connect();
};

da.ui.TreePanel.prototype.deselectBranches = function ( redraw ) {
	redraw = typeof redraw === 'undefined' ? true : redraw;
	this.$element.find( '.da-branch-connector' ).remove();
	this.$element.find( '.da-compare-node-graph-ignored' ).removeClass( 'da-compare-node-graph-ignored' );
	if ( redraw ) {
		this.connect();
	}
};
