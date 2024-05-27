da = window.da || {};
da.ui = window.da.ui || {};

da.ui.revisionTree = {
	drawConnection: function( $node1, $node2, $element ) {
		var pos = da.ui.revisionTree.getPositionForChild( $node1, $node2 );

		var type1 = da.ui.revisionTree.getTypeForNode( $node1 ),
			type2 = da.ui.revisionTree.getTypeForNode( $node2 ),
			isIgnored = $node1.hasClass( 'da-compare-node-graph-ignored' ) || $node2.hasClass( 'da-compare-node-graph-ignored' );

		var dStr = '';
		if ( type1 === type2 ) {
			// M 100, 100, L 200, 100
			dStr =
				"M " + (pos.start.x ) + "," + (pos.start.y) + " " +
				"L " +  (pos.end.x ) + "," + (pos.end.y);
		} else {
			// M 100,100, C100 115, 200 100, 200 130
			dStr =
				"M " + (pos.start.x ) + "," + (pos.start.y ) + " " +
				"C " + pos.start.x  + " " + ( pos.start.y - 20 ) + "," + ( pos.end.x ) + ' ' + pos.start.y + ',' +
				pos.end.x + "," + pos.end.y;
		}
		console.log( pos, dStr );

		var $connector = document.createElementNS("http://www.w3.org/2000/svg", "svg");
		$connector.setAttribute( 'width', $element.outerWidth() + 'px' );
		$connector.setAttribute( 'height', $element.outerHeight() + 'px' );
		$connector.setAttribute( 'style', 'position: absolute;top:0;left:0;z-index:-1' );

		$connector.setAttribute( 'class', 'da-branch-connector' );
		var $path = document.createElementNS("http://www.w3.org/2000/svg","path");
		$path.setAttributeNS(null, "d", dStr );
		$path.setAttributeNS(null, "stroke", isIgnored ? "#d9dbe5" : "#61636b");
		$path.setAttributeNS(null, "stroke-width", 5);
		$path.setAttributeNS(null, "opacity", 1);
		$path.setAttributeNS(null, "fill", "none");
		$connector.appendChild( $path );

		$element.append( $connector );
	},
	getPositionForChild: function( $node1, $node2 ) {
		return {
			start: {
				x: $node1.position().left + $node1.outerWidth( true ) / 2,
				y: $node1.position().top + $node1.outerHeight( true ) - parseInt( $node1.css( 'marginBottom' ) ) - 10
			},
			end: {
				x: $node2.position().left + $node2.outerWidth( true ) / 2,
				y: $node2.position().top + parseInt( $node2.css( 'marginTop' ) ) + 10
			}
		};
	},
	connectFromNodes: function( nodes, $container ) {
		for ( var hash in nodes ) {
			if ( !nodes.hasOwnProperty( hash ) ) {
				continue;
			}
			var node = nodes[hash];
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
				if ( nodes.hasOwnProperty( parent ) && nodes[parent].getRelevantNode() ) {
					da.ui.revisionTree.drawConnection( nodes[parent].getRelevantNode(), $relevantNode, $container );
				}
			}
		}
	},
	connectFromRawContainer: function( $cnt ) {
		var $element = $cnt;
		$element.find( '.da-compare-node' ).each( function() {
			// Node that has one class but not the other
			var $node = $( this ),
				$relevant = $node.find( '.da-compare-node-graph' ).not( '.da-compare-node-graph-placeholder' );
			if ( $relevant.length === 0 ) {
				return;
			}
			var parent =  $relevant.data( 'parent' ),
				parents = parent ? parent.split( ',' ) : [];
			for ( var i = 0; i < parents.length; i++ ) {
				parent = parents[i];
				if ( !parent ) {
					continue;
				}
				var $parent = $element.find( '.da-compare-node-graph[data-hash="' + parent + '"]' );
				if ( $parent.length ) {
					da.ui.revisionTree.drawConnection( $parent, $relevant, $element );
				}
			}
		} );
	},
	getTypeForNode: function( $node ) {
		return $node.hasClass( 'da-compare-node-graph-remote' ) ? 'remote' : 'local';
	}
};