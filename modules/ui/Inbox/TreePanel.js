da = window.da || {};
da.ui = window.da.ui || {};

da.ui.TreePanel = function ( config ) {
	config = config || {};
	this.tree = config.tree || {};
	this.nodes = {};
	this.lastParents = { 'local': null, 'remote': null };
	da.ui.TreePanel.super.call( this, $.extend( { expanded: false }, config ) );
};

OO.inheritClass( da.ui.TreePanel, OO.ui.PanelLayout );

da.ui.TreePanel.prototype.initialize = function () {
	this.lastParents = { 'local': null, 'remote': null };
	for ( var hash in this.tree ) {
		if ( !this.tree.hasOwnProperty( hash ) ) {
			continue;
		}
		var node = this.makeNode( this.tree[ hash ], hash );
		if ( node.getType() === 'local' ) {
			this.lastParents.local = node.getHash();
		}
		if ( node.getType() === 'remote' ) {
			this.lastParents.remote = node.getHash();
		}
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
	for ( var hash in this.nodes ) {
		if ( !this.nodes.hasOwnProperty( hash ) ) {
			continue;
		}
		var node = this.nodes[hash];
		var $relevantNode = node.getRelevantNode();
		if ( !$relevantNode ) {
			continue;
		}
		var parent = $relevantNode.attr( 'parent' ),
			parents = parent ? parent.split( ',' ) : [];
		for ( var i = 0; i < parents.length; i++ ) {
			parent = parents[i];
			if ( !parent ) {
				continue;
			}
			if ( this.nodes.hasOwnProperty( parent ) && this.nodes[parent].getRelevantNode() ) {
				this.drawConnection( this.nodes[parent].getRelevantNode(), $relevantNode );
			}
		}
	}
};

da.ui.TreePanel.prototype.getPositionForChild = function( $node1, $node2 ) {
	return {
		start: {
			x: $node1.position().left + $node1.outerWidth( true ) / 2,
			y: $node1.position().top + $node1.outerHeight( true ) - parseInt( $node1.css( 'marginBottom' ) )
		},
		end: {
			x: $node2.position().left + $node2.outerWidth( true ) / 2,
			y: $node2.position().top + parseInt( $node2.css( 'marginTop' ) )
		}
	}
};


da.ui.TreePanel.prototype.drawConnection = function ( $node1, $node2 ) {
	var pos = this.getPositionForChild( $node1, $node2 );

	var type1 = $node1.hasClass( 'da-compare-node-graph-local' ) ? 'local' : 'remote',
		type2 = $node2.hasClass( 'da-compare-node-graph-local' ) ? 'local' : 'remote',
		isIgnored = $node1.hasClass( 'da-compare-node-graph-ignored' ) || $node2.hasClass( 'da-compare-node-graph-ignored' );

	var dStr = '';
	if ( type1 === 'local' && type2 === 'local' ) {
		// M 100, 100, L 200, 100
		dStr =
			"M " + (pos.start.x ) + "," + (pos.start.y) + " " +
			"L " +  (pos.end.x ) + "," + (pos.end.y);
	} else {
		// M 100,100, C100 115, 200 100, 200 130
		dStr =
			"M " + (pos.start.x ) + "," + (pos.start.y) + " " +
			"C " + pos.start.x  + " " + ( pos.start.y + 20 ) + "," + pos.end.x + ' ' + pos.start.y + ',' +
			pos.end.x + "," + pos.end.y;
	}

	var $connector = document.createElementNS("http://www.w3.org/2000/svg", "svg");
	$connector.setAttribute( 'width', this.$element.outerWidth() + 'px' );
	$connector.setAttribute( 'height', this.$element.outerHeight() + 'px' );
	$connector.setAttribute( 'style', 'position: absolute;top:0;left:0;z-index:-1' );

	$connector.setAttribute( 'class', 'da-branch-connector' );
	var $path = document.createElementNS("http://www.w3.org/2000/svg","path");
	$path.setAttributeNS(null, "d", dStr );
	$path.setAttributeNS(null, "stroke", isIgnored ? "#d9dbe5" : "#61636b");
	$path.setAttributeNS(null, "stroke-width", 5);
	$path.setAttributeNS(null, "opacity", 1);
	$path.setAttributeNS(null, "fill", "none");
	$connector.appendChild( $path );

	this.$element.append( $connector );
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

da.ui.TreePanel.prototype.showCombinedNode = function ( show, type ) {
	this.deselectBranches( false );
	if ( show ) {
		var parents = type === 'combined' ?
			this.lastParents : { local: null, remote: this.lastParents.remote };
		this.addNode( new da.ui.ResolutionNode( parents ) );
	} else {
		if ( this.nodes.hasOwnProperty( '-1' ) ) {
			this.nodes[ '-1' ].$element.remove();
			delete this.nodes[ '-1' ];
		}
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
