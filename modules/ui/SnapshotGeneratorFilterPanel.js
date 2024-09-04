da = window.da || {};
da.ui = window.da.ui || {};

da.ui.SnapshotGeneratorFilterPanel = function( $form, submitButton ) {
	da.ui.SnapshotGeneratorFilterPanel.super.call( this, {
		expanded: false,
		padded: true
	} );
	this.$form = $form;
	this.submitButton = submitButton;
	var selector = this.addTypeSelector();
	this.$selectorPanel = $( '<div>' ).css( { padding: '1em 0' } );
	this.$element.append( this.$selectorPanel );
	this.$element.css( 'padding-left', 0 );
	this.selectedType = 'all';
	selector.selectItemByData( this.selectedType );

	this.submitButton.$element.on( 'click', this.onSubmit.bind( this ) );
};

OO.inheritClass( da.ui.SnapshotGeneratorFilterPanel, OO.ui.PanelLayout );

da.ui.SnapshotGeneratorFilterPanel.prototype.addTypeSelector = function() {
	var selector = new OO.ui.ButtonSelectWidget( {
		items: [
			new OO.ui.ButtonOptionWidget( {
				data: 'page_id',
				label: 'Individual pages'
			} ),
			new OO.ui.ButtonOptionWidget( {
				data: 'page_namespace',
				label: 'Namespace'
			} ),
			new OO.ui.ButtonOptionWidget( {
				data: 'all',
				label: 'All pages'
			} )
		]
	} );
	selector.connect( this, {
		select: function( item ) {
			if ( !item ) {
				return;
			}
			this.selectType( item.getData() );
		}
	} );
	this.$element.append( selector.$element );
	return selector;
};

da.ui.SnapshotGeneratorFilterPanel.prototype.selectType = function( type ) {
	this.$selectorPanel.empty();
	this.selectedType = type;
	this.selector = null;

	if ( type === 'page_id' ) {
		this.addPageSelector();
	}
	if ( type === 'page_namespace' ) {
		this.addNamespaceSelector();
	}
	if ( this.selector ) {
		this.$selectorPanel.append( this.selector.$element );
		this.selector.focus();
	}
};

da.ui.SnapshotGeneratorFilterPanel.prototype.addPageSelector = function() {
	this.selector = new mw.widgets.TitlesMultiselectWidget( {
		autocomplete: false,
		excludeDynamicNamespaces: true,
		validateTitle: true,
		allowArbitrary: false,
		placeholder: mw.msg( 'da-snapshotgenerator-filter-page-placeholder' )
	} );
};

da.ui.SnapshotGeneratorFilterPanel.prototype.addNamespaceSelector = function() {
	this.selector = new da.ui.NamespacesMultiselectWidget( {
		placeholder: mw.msg( 'da-snapshotgenerator-filter-namespace-placeholder' )
	} );
};

da.ui.SnapshotGeneratorFilterPanel.prototype.onSubmit = function( e ) {
	e.preventDefault();
	this.$form.find( 'input[name="filterType"]' ).val( this.selectedType );
	if ( this.selector ) {
		this.$form.find( 'input[name="filterValue"]' ).val( this.selector.getValue().join( '|' ) );
	}
	this.$form.submit();
};
