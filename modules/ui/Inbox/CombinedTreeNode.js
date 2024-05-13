da = window.da || {};
da.ui = window.da.ui || {};

da.ui.CombinedTreeNode = function ( data ) {
	da.ui.CombinedTreeNode.super.call( this, '-1', { source: 'combined', parents: { local: data.local, remote: data.remote } } );
};

OO.inheritClass( da.ui.CombinedTreeNode, da.ui.TreeNode );

da.ui.CombinedTreeNode.prototype.makeGraphPart = function () {
	this.makeRelevantNode();
	this.$element.append( this.$relevantNode );
	this.$element.append( $( '<span>' ).addClass( 'da-compare-node-graph da-compare-node-graph-placeholder' ) );
};

da.ui.CombinedTreeNode.prototype.makeRelevantNode = function () {
	var parents = '';
	console.log( this.nodeData );
	if ( this.nodeData.parents.local ) {
		parents += this.nodeData.parents.local;
	}
	if ( this.nodeData.parents.remote ) {
		parents += ',' + this.nodeData.parents.remote;
	}
	var classes = [ 'da-compare-node-graph', 'da-compare-node-graph-' + this.getType() ];
	this.$relevantNode = $( '<span>' ).addClass( classes.join( ' ' ) )
		.attr( 'parent', parents );
};

da.ui.CombinedTreeNode.prototype.makeLabel = function () {
	// NOOP
};

da.ui.CombinedTreeNode.prototype.getType = function () {
	return 'combined';
};