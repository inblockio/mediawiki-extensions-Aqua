da = window.da || {};
da.ui = window.da.ui || {};

da.ui.ResolutionNode = function ( data ) {
	da.ui.ResolutionNode.super.call( this, '-1', { source: 'combined', parents: { local: data.local, remote: data.remote } } );
};

OO.inheritClass( da.ui.ResolutionNode, da.ui.TreeNode );

da.ui.ResolutionNode.prototype.makeGraphPart = function () {
	this.makeRelevantNode();
	this.$element.append( this.$relevantNode );
	this.$element.append( $( '<span>' ).addClass( 'da-compare-node-graph da-compare-node-graph-placeholder' ) );
};

da.ui.ResolutionNode.prototype.makeRelevantNode = function () {
	var parents = [];
	if ( this.nodeData.parents.local ) {
		parents.push( this.nodeData.parents.local );
	}
	if ( this.nodeData.parents.remote ) {
		parents.push( this.nodeData.parents.remote );
	}
	var classes = [ 'da-compare-node-graph', 'da-compare-node-graph-' + this.getType() ];
	this.$relevantNode = $( '<span>' ).addClass( classes.join( ' ' ) )
		.attr( 'parent', parents.join( ',' ) );
};

da.ui.ResolutionNode.prototype.makeLabel = function () {
	// NOOP
};

da.ui.ResolutionNode.prototype.getType = function () {
	return 'resolution';
};