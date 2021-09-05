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

  function extractPageTitle(urlObj) {
    // If you update this function, make sure to sync with the same function in
    // the VerifyPage repo, in src/verifier.ts file.
    if (!urlObj) {
      return ''
    }
    const title = urlObj.pathname.split('/').pop();
    return title ? title.replace(/_/g, ' ') : '';
  }

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
      var $daButton, color

      $daButton = $('#ca-daact a:first')
      // TODO add hover text "Signs this revision with a private key. It allows adding a reason in the summary."

      $daButton.on('click', function (event) {
        event.preventDefault()
        const urlObj = new URL(window.location.href)
        const pageTitle = extractPageTitle(urlObj)
        console.log(pageTitle)
        if (window.ethereum) {
          if (window.ethereum.isConnected() && window.ethereum.selectedAddress) {
            fetch('http://localhost:9352/rest.php/data_accounting/v1/standard/get_page_last_rev?var1=' + pageTitle)
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
              fetch(
                'http://localhost:9352/rest.php/data_accounting/v1/write/store_signed_tx' +
                '?var1=' + revId +
                  '&var2=' + signature +
                  '&var3=' + public_key +
                  '&var4=' + window.ethereum.selectedAddress,
                { method: 'GET' }
              )
              .then((data) => {
                showConfirmation()
              })
            }

            function next(revId) {
              console.log("Rev ID:", revId)
              fetch('http://localhost:9352/rest.php/data_accounting/v1/standard/request_hash?var1=' + revId, { method: 'GET' })
              .then((resp) => {
                if (!resp.ok) {
                  resp.text().then(parsed => alert(parsed))
                  return
                }
                resp.json().then(parsed => signContent(parsed, revId))
              })
              .catch(() => console.log('error)'))
            }
          } else {
            window.ethereum.request({ method: 'eth_requestAccounts' })
          }
          console.log('hasEth')
        } else {
          alert('Please install metamask')
          console.log('Please install metamask')
        }
      })
    },
  }

  module.exports = signMessage

  mw.ExampleWelcome = signMessage
})()
