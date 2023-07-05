da = window.da || {};
da.ui = window.da.ui || {};

da.ui.ComparePanel = function ( config, $form ) {
	config = config || {};
	this.changetype = config.tree[ 'change-type' ];
	this.tree = config.tree.tree || {};
	this.$form = $form;
	da.ui.ComparePanel.super.call( this, $.extend( { expanded: false }, config ) );
};

OO.inheritClass( da.ui.ComparePanel, OO.ui.PanelLayout );

da.ui.ComparePanel.prototype.initialize = function () {
	this.treePanel = new da.ui.TreePanel( {
		tree: this.tree
	} );
	this.$element.append( this.treePanel.$element );
	this.treePanel.initialize();

	this.setFormData();
};

da.ui.ComparePanel.prototype.setFormData = function () {
	this.$form.find( '#mw-input-wpaction' ).val( 'import-remote' );
	// Prompt before submitting the form
	var canSubmit = false;
	this.$form.on( 'submit', function ( e ) {
		if ( canSubmit ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		OO.ui.confirm( mw.message( 'da-specialimport-confirm' ).text() ).done( function ( confirmed ) {
			if ( confirmed ) {
				canSubmit = true;
				this.$form.submit();
			}
		}.bind( this ) );
	}.bind( this ) );


	if ( this.changetype === 'both' ) {
		var importSubmitButton = this.$form.find( 'button[name=import]' );
		this.$form.find( '#mw-input-wpaction' ).val( 'import-merge' );
		importSubmitButton.prop( 'disabled', true );

		var option1 = new OO.ui.ButtonOptionWidget( {
				data: 'local',
				label: 'Use local version (discard remote)',
				flags: [ 'progressive' ]
			} ),
			option2 = new OO.ui.ButtonOptionWidget( {
				data: 'remote',
				label: 'Use remote version (discard local)',
				flags: [ 'progressive' ]
			} ),
			option3 = new OO.ui.ButtonOptionWidget( {
				data: 'merge',
				label: 'Resolve conflict manually and import combined version',
				flags: [ 'progressive' ]
			} ),
			buttonSelect = new OO.ui.ButtonSelectWidget( {
				classes: [ 'da-compare-button-select' ],
				items: [ option1, option2, option3 ]
			} );

		buttonSelect.connect( this, {
			select: function( item ) {
				if ( item.getData() === 'remote' ) {
					this.treePanel.selectBranch( 'remote' );
				} else if ( item.getData() === 'local' ) {
					this.treePanel.selectBranch( 'local' );
				} else {
					this.treePanel.deselectBranches();
				}
			}
		} );

		var panel = new OO.ui.PanelLayout( {
			expanded: false,
			padded: false,
			classes: [ 'da-compare-button-select-panel' ]
		} );
		panel.$element.append( new OO.ui.LabelWidget( {
			label: 'Select conflict resolution method:'
		} ).$element );
		panel.$element.append( buttonSelect.$element );
		panel.$element.insertBefore( this.$form );
	}
};