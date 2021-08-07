/**
 * Display a welcome message on the page.
 *
 * This file is part of the 'ext.DataAccounting.signMessage' module.
 *
 * It is enqueued for loading on all pages of the wiki,
 * from ExampleHooks::onBeforePageDisplay() in Example.hooks.php.
 */
;(function () {
  var signMessage

  // TODO: Maybe replace with Bootstrap
	function showConfirmation( data ) {
		var $container, $popup, $content, timeoutId;

		function fadeOutConfirmation() {
			setTimeout( function () {
				$container.remove()
			}, 250 )
		}

		data = data || {}

		if ( data.message === undefined ) {
			data.message = 'SIGNED!'
		}

		$content = $( '<div>' ).addClass( 'da-sign-content' )
    $content.text( data.message )

		$popup = $( '<div>' ).addClass( 'da-sign mw-notification' ).append( $content )
			.on( 'click', function () {
				clearTimeout( timeoutId )
				fadeOutConfirmation()
			} )

		$container = $( '<div>' ).addClass( 'da-sign-container' ).append( $popup )
		timeoutId = setTimeout( fadeOutConfirmation, 3000 )

		$( document.body ).prepend( $container )
	}

  signMessage = {
    init: function () {
      var $box, color

      // $box = $(
      //   '<span id="wpSignWidget" aria-disabled="false" class="oo-ui-widget oo-ui-widget-enabled oo-ui-inputWidget oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-labelElement oo-ui-flaggedElement-progressive oo-ui-flaggedElement-primary oo-ui-buttonInputWidget" data-ooui="{&quot;_&quot;:&quot;OO.ui.ButtonInputWidget&quot;,&quot;useInputTag&quot;:true,&quot;type&quot;:&quot;submit&quot;,&quot;name&quot;:&quot;wpSign&quot;,&quot;inputId&quot;:&quot;wpSign&quot;,&quot;tabIndex&quot;:3,&quot;title&quot;:&quot;Sign your changes&quot;,&quot;accessKey&quot;:&quot;s&quot;,&quot;label&quot;:&quot;Sign changes&quot;,&quot;flags&quot;:[&quot;progressive&quot;,&quot;primary&quot;]}"><input type="button" tabindex="3" aria-disabled="false" title="Save your changes [ctrl-option-s]" accesskey="s" name="wpSign" id="wpSign" value="Sign changes" class="oo-ui-inputWidget-input oo-ui-buttonElement-button"></span>'
      // )

      $box = $(
        '<span class="mw-changeslist-separator"></span><span class="mw-changeslist-links"><span><span class="mw-history-sign"><a id="sign" title="&quot;Sign&quot; Signs this revision with a private key. It allows adding a reason in the summary.">sign</a></span></span></span>'
      )

      // Append the message about today's color, and the color icon itself.
      $box.css('borderColor', color).attr('data-welcome-color', color)

      $box.on('click', '#sign', function () {
        const revId = $('ul#pagehistory li').first().attr('data-mw-revid')
        console.log({ revId })
        if (window.ethereum) {
          if (window.ethereum.isConnected() && window.ethereum.selectedAddress) {
            fetch('http://localhost:9352/rest.php/data_accounting/v1/standard/request_hash?var1=' + revId, { method: 'GET' })
              .then((data) => {
                data.json().then((parsed) => {
                  console.log(parsed.value)
                  window.ethereum
                    .request({
                      method: 'personal_sign',
                      params: [parsed.value, window.ethereum.selectedAddress],
                    })
                    .then((signature) => {
                      console.log(`signed: ${JSON.stringify(signature)}`);
                        console.log(`digest: ${parsed.value}`);
                        // console.log(`arrayify: ${ethers.utils.arrayify(parsed.value)}`);
                        let public_key = ethers.utils.recoverPublicKey(ethers.utils.hashMessage(parsed.value), signature);
                        let recAddress = ethers.utils.recoverAddress(ethers.utils.hashMessage(parsed.value), signature);
                        console.log(`public key ${public_key}`);
                        console.log(`original ${window.ethereum.selectedAddress}, recovered ${recAddress}`);
                      fetch(
                        'http://localhost:9352/rest.php/data_accounting/v1/standard/store_signed_tx' +
                          '?var1=' + revId +
                          '&var2=' + signature +
                          '&var3=' + public_key +
                          '&var4=' + window.ethereum.selectedAddress,
                        { method: 'GET' }
                      )
                      .then((data) => {
                        showConfirmation()
                      })
                    })
                })
              })
              .catch(() => console.log('error)'))
          } else {
            window.ethereum.request({ method: 'eth_requestAccounts' })
          }
          console.log('hasEth')
        } else {
          alert('Please install metamask')
          console.log('Please install metamask')
        }
      })

      // Ask jQuery to invoke this callback function once the page is ready.
      // See also <https://api.jquery.com/jQuery>.
      $(function () {
        // $('#wpSaveWidget').after($box)
        $('ul#pagehistory li').first().append($box)
      })
    },
  }

  module.exports = signMessage

  mw.ExampleWelcome = signMessage
})()
