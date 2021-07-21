;(function () {
  var publishDomainManifest

  publishDomainManifest = {
    init: function () {
      $("#publish-domain-manifest").click(
        function() {
          alert("SUP")
        }
      )
    },
  }

  module.exports = publishDomainManifest

  mw.publishDomainManifest = publishDomainManifest
})()
