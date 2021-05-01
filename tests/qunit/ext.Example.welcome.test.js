QUnit.module( 'ext.Verified_Page_History.signMessage', {
	beforeEach: function () {
		this.conf = mw.config.values;
		mw.config.values = {
			wgExampleWelcomeColorDays: {
				tuesday: 'pink'
			},
			wgExampleWelcomeColorDefault: '#ccc'
		};
	},
	afterEach: function () {
		mw.config.values = this.conf;
	}
} );

QUnit.test( 'getColorByDate()', function ( assert ) {
	var welcome = require( 'ext.Verified_Page_History.signMessage' );
	assert.strictEqual( welcome.getColorByDate( 'monday' ), '#ccc', 'default' );
	assert.strictEqual( welcome.getColorByDate( 'tuesday' ), 'pink', 'custom' );
} );
