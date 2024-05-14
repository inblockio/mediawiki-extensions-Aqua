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
  // We no longer use this function for now.
  // We no longer show the SIGNED! text because the current theme we use,
  // Tweeki, is not compatible with this solution.
	function showConfirmation( response ) {
    let text = 'SIGNED!'
    if (!response.ok) {
      text = response.statusText
    }
		var $container, $popup, $content, timeoutId;

		function fadeOutConfirmation() {
			setTimeout( function () {
				$container.remove()
        // Refresh the page after success
        location.reload()
			}, 250 )
		}

		$content = $( '<div>' ).addClass( 'da-sign-content' )
    $content.text( text )

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
      var $daButton, color

      $daButton = $('#ca-daact a:first')
      if ($daButton.length === 0) {
        // Workaround for Tweeki theme because the #ca-daact is now tied
        // directly to an <a>.
        $daButton = $('#ca-daact')
      }
      // TODO add hover text "Signs this revision with a private key. It allows adding a reason in the summary."

      $daButton.on('click', function (event) {
        event.preventDefault()
        const urlObj = new URL(window.location.href)
        // We use wgPageName instead of wgTitle because the former includes localized namespace.
        const pageName = mw.config.get( 'wgPageName' );
        if (pageName.endsWith(".pdf")) {
          alert("Can't sign because MetaMask is disabled for PDF URL.")
          return
        }
        const server = window.location.protocol + '//' + window.location.host
        if (window.ethereum) {
          function doSignProcess() {
            fetch(server + '/rest.php/data_accounting/get_page_last_rev?page_title=' + pageName)
            .then((resp) => {
              if (!resp.ok) {
                resp.text().then(parsed => alert(parsed))
                return
              }
              resp.json().then(parsed => {
                if (!parsed.rev_id) {
                  alert("No verified revision is found")
                  return
                }
                next(parsed.rev_id)
              })
            })

            function signContent(parsed, revId) {
              console.log(parsed.value)
              window.ethereum
              .request({
                method: 'personal_sign',
                params: [parsed.value, window.ethereum.selectedAddress],
              })
              .then(signature => {storeSignature(parsed, signature, revId)})
            }

            function storeSignature(parsed, signature, revId) {
              // Store the signature in the DB.
              console.log(`signed: ${JSON.stringify(signature)}`);
              console.log(`digest: ${parsed.value}`);
              // console.log(`arrayify: ${ethers.utils.arrayify(parsed.value)}`);
              let public_key = ethers.utils.recoverPublicKey(ethers.utils.hashMessage(parsed.value), signature);
              let recAddress = ethers.utils.recoverAddress(ethers.utils.hashMessage(parsed.value), signature);
              console.log(`public key ${public_key}`);
              console.log(`original ${window.ethereum.selectedAddress}, recovered ${recAddress}`);
              const payload = {
                rev_id: revId,
                signature: signature,
                public_key: public_key,
                wallet_address: recAddress,
              }
              fetch(
                server + '/rest.php/data_accounting/write/store_signed_tx',
                { method: 'POST',
                  cache: 'no-cache',
                  headers: {
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify(payload)
                }
              )
              .then((response) => {
                if (!response.ok) {
                  console.log("store_signed_tx not ok: ", response.status)
                  return
                }
                // Refresh the page after success.
                setTimeout(
                  () => {location.reload()},
                  500
                )
              })
            }

            function next(revId) {
              console.log("Rev ID:", revId)
              fetch(server + '/rest.php/data_accounting/request_hash/' + revId, { method: 'GET' })
              .then((resp) => {
                if (!resp.ok) {
                  resp.text().then(parsed => alert(parsed))
                  return
                }
                resp.json().then(parsed => signContent(parsed, revId))
              })
              .catch(() => console.log('error)'))
            }
          }

          if (window.ethereum.isConnected() && window.ethereum.selectedAddress) {
            doSignProcess()
          } else {
            window.ethereum.request({ method: 'eth_requestAccounts' })
              .then(doSignProcess)
              .catch((error) => {
                console.error(error);
                alert(error.message);
              })
          }
          console.log('hasEth')
        } else {
          alert('Please install MetaMask')
          console.log('Please install MetaMask')
        }
      })
    },
  }

  module.exports = signMessage

  mw.ExampleWelcome = signMessage
})()
