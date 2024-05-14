da = window.da || {};
da.ui = window.da.ui || {};

da.ui.ConflictResolution = function ( config, $diff ) {
	config = config || {};

	da.ui.ConflictResolution.super.call( this, $.extend( { expanded: false }, config ) );

	this.$diff = $diff;
	this.diffData = this.$diff.data( 'diff' );
	this.neededResolutions = this.getNumberOfResolutionsNeeded();
	this.allResolved = false;
	this.resolved = {};
	this.$element.html( this.$diff );
	this.setVisibility( false );
	this.finalText = '';
	this.$element.addClass( 'da-conflict-resolution' );
	this.initialize();
};

OO.inheritClass( da.ui.ConflictResolution, OO.ui.PanelLayout );

da.ui.ConflictResolution.prototype.initialize = function () {
	this.$element.prepend( new OO.ui.LabelWidget( {
		label: 'Merge diverging versions of the page',
		classes: [ 'da-conflict-resolution-title' ]
	} ).$element );

	this.$diff.children( '#da-diff' ).children( '.da-diff-add, .da-diff-delete, .da-diff-change' )
		.each( function ( index, element ) {
			var $element = $( element ),
				id = $element.data( 'diff-id' ),
				type = $element.data( 'diff' );
			if ( this.diffData.hasOwnProperty( id ) ) {
				this.addButtons( $element, id, type );
			}
	}.bind( this ) );
};

da.ui.ConflictResolution.prototype.setVisibility = function ( visibile ) {
	if ( visibile ) {
		this.$element.show();
	} else {
		this.$element.hide();
	}
};

da.ui.ConflictResolution.prototype.addButtons = function ( $element, id, type ) {
	var changeSelector;
	if ( type === 'change' ) {
		changeSelector = new OO.ui.ButtonSelectWidget( {
			classes: [ 'da-conflict-resolution-buttons' ],
			items: [
				new OO.ui.ButtonOptionWidget( {
					label: 'use ours',
					framed: false,
					data: { change: id, type: 'local' },
				} ),
				new OO.ui.ButtonOptionWidget( {
					label: 'use theirs',
					framed: false,
					data: { change: id, type: 'remote' },
				} ),
				new OO.ui.ButtonOptionWidget( {
					label: 'use both',
					framed: false,
					data: { change: id, type: 'both' },
				} ),
				new OO.ui.ButtonOptionWidget( {
					label: 'use neither',
					framed: false,
					data: { change: id, type: 'neither' },
				} )
			]
		} );
	} else {
		changeSelector = new OO.ui.ButtonSelectWidget( {
			classes: [ 'da-conflict-resolution-buttons' ],
			items: [
				new OO.ui.ButtonOptionWidget( {
					label: 'accept',
					framed: false,
					data: { change: id, type: 'accept' },
				} ),
				new OO.ui.ButtonOptionWidget( {
					label: 'reject',
					framed: false,
					data: { change: id, type: 'reject' },
				} )
			]
		} );
	}

	changeSelector.connect( this, {
		select: 'onSelect'
	} );
	$element.append( changeSelector.$element );
};

da.ui.ConflictResolution.prototype.onSelect = function ( item ) {
	if ( !item ) {
		return;
	}
	var $block = this.$diff.find( '[data-diff-id=' + item.data.change + ']' );
	if ( !$block.length ) {
		return;
	}
	$block.addClass( 'da-diff-resolved' );
	$block.removeClass( function( index, className ) {
		return (className.match(/(^|\s)da-diff-resolution-\S+/g) || []).join(' ');
	} );

	$block.addClass( 'da-diff-resolution-' + item.data.type );

	this.resolved[item.data.change] = item.data.type;
	if ( Object.keys( this.resolved ).length === this.neededResolutions ) {
		this.allResolved = true;
		this.emit( 'allResolved' );
	}
};

da.ui.ConflictResolution.prototype.getNumberOfResolutionsNeeded = function () {
	var needed = 0;
	for ( var id in this.diffData ) {
		if ( !this.diffData.hasOwnProperty( id ) ) {
			continue;
		}
		if ( this.diffData[id].type !== 'copy' ) {
			needed++;
		}
	}
	return needed;
};

da.ui.ConflictResolution.prototype.getFinalText = function () {
	if ( !this.allResolved ) {
		throw new Error( 'Not all resolutions have been made' );
	}
	var finalText = [];
	console.log( this.diffData, this.resolved );
	for ( var id in this.diffData ) {
		if ( !this.diffData.hasOwnProperty( id ) ) {
			continue;
		}
		if ( this.diffData[id].type === 'copy' ) {
			finalText.push( this.diffData[id].old );
		}
		if ( this.diffData[id].type === 'change' ) {
			if ( this.resolved.hasOwnProperty( id ) ) {
				if ( this.resolved[id] === 'local' ) {
					finalText.push( this.diffData[id].old );
				}
				if ( this.resolved[id] === 'remote' ) {
					finalText.push( this.diffData[id].new );
				}
				if ( this.resolved[id] === 'both' ) {
					finalText.push( this.diffData[id].old )
					finalText.push( this.diffData[id].new );
				}
			}
		}
		if ( this.diffData[id].type === 'add' ) {
			if ( this.resolved.hasOwnProperty( id ) ) {
				if ( this.resolved[id] === 'accept' ) {
					finalText.push( this.diffData[id].new );
				}
			}
		}
		if ( this.diffData[id].type === 'delete' ) {
			if ( this.resolved.hasOwnProperty( id ) ) {
				if ( this.resolved[id] === 'reject' ) {
					finalText.push( this.diffData[id].old );
				}
			}
		}
	}

	// Filter out empty strings
	finalText = finalText.filter( function( text ) {
		return text !== '';
	} );
	return finalText.join( '\n' );
};
