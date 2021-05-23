#!/usr/bin/env node

const http = require( 'http' )
const sha3 = require('js-sha3')

let title = 'TP1'

http.get(`http://localhost:9352/api.php?action=query&prop=info&titles=${title}&format=json`, (resp) => {
  resp.on('data', (data) => {
    let obj = JSON.parse(data.toString()).query.pages
    // Get the first value of the object, because we are querying only one page
    // after all.
    obj = Object.values(obj)[0]
    let pageid = obj.pageid;
    let touched = obj.touched
    let lastrevid = obj.lastrevid
    console.log(obj)
    console.log(sha3.sha3_512(touched))
  })
}).on("error", (err) => {
  console.log("Error: " + err.message);
})

function formatMwTimestamp(ts) {
  // Format timestamp into the timestamp format found in Mediawiki outputs
  return ts.replace(/-/g, '').replace(/:/g, '').replace('T', '').replace('Z', '')
}

function getHashSum(content) {
  return sha3.sha3_512(content)
}

function calculateVerificationHash(contentHash, metadataHash) {
  return getHashSum(contentHash + metadataHash)
}

function calculateMetadataHash(timestamp, previousVerificationHash = "", signature = "", publicKey = "") {
	return getHashSum(timestamp + previousVerificationHash + signature + publicKey)
}

async function getBackendVerificationHash(revid) {
  http.get(`http://localhost:9352/rest.php/data_accounting/v1/request_hash/${revid}`, (resp) => {
    resp.on('data', (data) => {
      obj = JSON.parse(data.toString()).value
      console.log('backend', revid, obj)
    })
  })
}

function verifyPage(title) {
  http.get(`http://localhost:9352/api.php?action=query&prop=revisions&titles=${title}&rvlimit=555&rvprop=ids%7Ctimestamp%7Ccontent&rvdir=newer&format=json`, (resp) => {
    resp.on('data', (data) => {
      // Get the first value of the object, because we are querying only one page
      // after all.
      let obj = JSON.parse(data.toString()).query.pages
      obj = Object.values(obj)[0]
      let revisions = obj.revisions
      let previousVerificationHash = ''
      for (let i = 0; i < revisions.length; i++) {
        let rev = revisions[i]
        let revid = rev.revid
        let parentid = rev.parentid
        let timestamp = formatMwTimestamp(rev.timestamp)
        let content = rev['*']

        let contentHash = getHashSum(content)
        let metadataHash = calculateMetadataHash(timestamp, previousVerificationHash)
        let verificationHash = calculateVerificationHash(contentHash, metadataHash)
        previousVerificationHash = verificationHash
        console.log(revid, verificationHash)
        getBackendVerificationHash(revid)
      }
    })
  }).on("error", (err) => {
    console.log("Error: " + err.message);
  })
}

verifyPage(title)
