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
            fetch('http://localhost:9352/rest.php/data_accounting/v1/request_hash/' + revId, { method: 'GET' })
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
                      fetch(
                        'http://localhost:9352/rest.php/data_accounting/v1/data_input/' +
                          revId +
                          '/' +
                          signature +
                          '/' +
                          window.ethereum.selectedAddress,
                        { method: 'GET' }
                      )
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
