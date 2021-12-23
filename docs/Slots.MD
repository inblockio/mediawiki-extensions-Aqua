# Multi content revisions in PKC

We register three different slots:

## `signature` slot
This slot holds all data about wallets that signed the page in JSON format.

## `transclusion-hashes` slot
This slot holds all data about which resource are included on the page, and the state of the resources.
Data is also held in JSON format, but its visually represented in a table that shows the current state of the
resource. Raw data can be accessed by adding `debug=true` flag to the URL

Resource table will, in case there a newer version of the included resource, allow user to update the hash,
and therefore the included version on the page.

## `file-verfication` slot
This slot only applies to pages in `NS_FILE`. Once a file is uploaded it will hash `base64` value of the fiel
content and put that information in this slot. This can later be used to verify that actual file content was
not tempered with.
