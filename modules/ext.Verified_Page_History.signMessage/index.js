/**
 * Display a welcome message on the page.
 *
 * This file is part of the 'ext.Verified_Page_History.signMessage' module.
 *
 * It is enqueued for loading on all pages of the wiki,
 * from ExampleHooks::onBeforePageDisplay() in Example.hooks.php.
 */
;(function () {
  var signMessage

  signMessage = {
    init: function () {
      var $box, color

      $box = $(
        '<span id="wpSignWidget" aria-disabled="false" class="oo-ui-widget oo-ui-widget-enabled oo-ui-inputWidget oo-ui-buttonElement oo-ui-buttonElement-framed oo-ui-labelElement oo-ui-flaggedElement-progressive oo-ui-flaggedElement-primary oo-ui-buttonInputWidget" data-ooui="{&quot;_&quot;:&quot;OO.ui.ButtonInputWidget&quot;,&quot;useInputTag&quot;:true,&quot;type&quot;:&quot;submit&quot;,&quot;name&quot;:&quot;wpSign&quot;,&quot;inputId&quot;:&quot;wpSign&quot;,&quot;tabIndex&quot;:3,&quot;title&quot;:&quot;Sign your changes&quot;,&quot;accessKey&quot;:&quot;s&quot;,&quot;label&quot;:&quot;Sign changes&quot;,&quot;flags&quot;:[&quot;progressive&quot;,&quot;primary&quot;]}"><input type="button" tabindex="3" aria-disabled="false" title="Save your changes [ctrl-option-s]" accesskey="s" name="wpSign" id="wpSign" value="Sign changes" class="oo-ui-inputWidget-input oo-ui-buttonElement-button"></span>'
      )

      // Append the message about today's color, and the color icon itself.
      $box.css('borderColor', color).attr('data-welcome-color', color)

      $box.on('click', '#wpSign', function () {
				if (window.ethereum) {
					if (window.ethereum.isConnected() && window.ethereum.selectedAddress) {
            const content = document.body.innerHTML
            const hashsum = ethers.utils.id(content)
            window.ethereum.request({
              method: "personal_sign",
              params: [hashsum, window.ethereum.selectedAddress]
            }).then((data) => {console.log(`signed: ${JSON.stringify(data)}`)})
					} else {
            window.ethereum.request({ method: 'eth_requestAccounts' });
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
        $('#wpSaveWidget').after($box)
      })
    },

  }

  module.exports = signMessage

  mw.ExampleWelcome = signMessage

})()
