da = window.da || {};
da.ui = window.da.ui || {};

da.ui.TreeNode = function ( hash, data ) {
	this.hash = hash;
	this.nodeData = data;

	da.ui.TreeNode.super.call( this, {} );
	this.$element = $( '<div>' ).addClass( 'da-compare-node' );

	this.initialize();
};

OO.inheritClass( da.ui.TreeNode, OO.ui.HorizontalLayout );

da.ui.TreeNode.prototype.initialize = function () {
	this.makeGraphPart();
	this.makeLabel();
};

da.ui.TreeNode.prototype.makeGraphPart = function () {
	this.makeRelevantNode();
	if ( this.getType() === 'local' ) {
		this.$element.append( this.$relevantNode );
		this.$element.append( $( '<span>' ).addClass( 'da-compare-node-graph da-compare-node-graph-placeholder' ) );
	}
	if ( this.getType() === 'remote' ) {
		this.$element.append( $( '<span>' ).addClass( 'da-compare-node-graph da-compare-node-graph-placeholder' ) );
		this.$element.append( this.$relevantNode );
	}
};

da.ui.TreeNode.prototype.makeRelevantNode = function () {
	var classes = [ 'da-compare-node-graph', 'da-compare-node-graph-' + this.getType() ];
	this.$relevantNode = $( '<span>' ).addClass( classes.join( ' ' ) )
		.attr( 'hash', this.hash )
		.attr( 'revisions', this.nodeData.revisions )
		.attr( 'parent', this.nodeData.parent );
}

da.ui.TreeNode.prototype.makeLabel = function () {
	// Get first 5 chars and last 5 chars of hash
	var hash = this.hash;
	hash = hash.substr( 0, 5 ) + '...' + hash.substr( -5 );
	this.$element.append(
		new OO.ui.HorizontalLayout( {
			classes: [ 'da-compare-node-label' ],
			items: [
				new OO.ui.LabelWidget( { label: hash } )
			]
		} ).$element
	);
};

da.ui.TreeNode.prototype.getType = function () {
	return this.nodeData.source;
};

da.ui.TreeNode.prototype.getHash = function () {
	return this.hash;
};

da.ui.TreeNode.prototype.getRelevantNode = function () {
	return this.$relevantNode || null;
};