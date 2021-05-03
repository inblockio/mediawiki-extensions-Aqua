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

      $box = $('<div class="mw-welcome-bar"></div>').html(
        '<input id="sign_button" type="button" class="sign_message" value="Sign">'
      )

      // Append the message about today's color, and the color icon itself.
      $box.css('borderColor', color).attr('data-welcome-color', color)

      $box.on('click', '.sign_message', function () {
				if (window.ethereum) {
					if (window.ethereum.isConnected() && window.ethereum.selectedAddress) {
            window.ethereum.request({
              method: "personal_sign",
              params: ["0x1", window.ethereum.selectedAddress]
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
        $('h1').first().after($box)
      })
    },

  }

  module.exports = signMessage

  mw.ExampleWelcome = signMessage
})()
