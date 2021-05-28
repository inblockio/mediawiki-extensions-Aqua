#!/usr/bin/env node

const http = require( 'http' )
const sha3 = require('js-sha3')

// utilities for verifying signatures
const ethers = require('ethers')

let title = 'Main Page'
//title = 'AI/Machine_Learning'

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
    })
  })
}

async function verifyRevision(revid) {
  http.get(`http://localhost:9352/rest.php/data_accounting/v1/standard/verify_page/${revid}///`, (resp) => {
    resp.on('data', (data) => {
      let dataStr = data.toString()
      if (dataStr === '[]') {
        console.log(`${revid} doesn't have verification hash`)
        return
      }
      let obj = JSON.parse(dataStr)
      console.log('backend', revid, obj)
      const paddedMessage = 'I sign the following page verification_hash: [0x' + obj.verification_hash + ']'
      const recoveredAddress = ethers.utils.recoverAddress(ethers.utils.hashMessage(paddedMessage), obj.signature)
      console.log(recoveredAddress.toLowerCase(), obj.wallet_address.toLowerCase())
      if (recoveredAddress === obj.wallet_address) {
        console.log(`${revid} is valid`)
      }
    })
  })
}

function verifyPage(title) {
  http.get(`http://localhost:9352/rest.php/data_accounting/v1/standard/page_all_rev/${title}///`, (resp) => {
    let body = ""
    resp.on('data', (chunk) => {
      body += chunk
    })
    resp.on('end', async () => {
      allRevInfo = JSON.parse(body)
      verifiedRevIds = allRevInfo.map(x => x.rev_id)
      console.log('verified ids', verifiedRevIds)

      let previousVerificationHash = ''
      for (const idx in verifiedRevIds) {
        const revid = verifiedRevIds[idx]
        // TODO make sure this http.get call finishes properly before the next http.get
        await http.get(`http://localhost:9352/api.php?action=parse&oldid=${revid}&prop=wikitext&formatversion=2&format=json`, (respRevid) => {
          let bodyRevid = ""
          respRevid.on('data', (chunk) => {
            bodyRevid += chunk
          })
          respRevid.on('end', () => {
            let content = JSON.parse(bodyRevid).parse.wikitext
            //let timestamp = formatMwTimestamp(rev.timestamp)
            let timestamp = "AAA"
            let contentHash = getHashSum(content)
            let metadataHash = calculateMetadataHash(timestamp, previousVerificationHash)
            let verificationHash = calculateVerificationHash(contentHash, metadataHash)
            console.log(revid, verificationHash, previousVerificationHash)
            previousVerificationHash = verificationHash
            //verifyRevision(revid)
          })
        })
      }
    })
  }).on("error", (err) => {
    console.log("Error: " + err.message);
  })
}

//verifyRevision(328)
verifyPage(title)
