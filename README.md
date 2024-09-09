# Aqua - Mediawiki Extension
This is a code extension for MediaWiki to implement the Aqua Protocol. 

This is done by using the following modules:
* Create a verified revision of pages and files which are entangled into future revisions (hash-chain)
* Cryptographically sign a verified revision via a wallet
* Export verified revisions
* Import verified revisions
* Witness verified revisions on a witness network (e.g. Ethereum)

Revisions can be interlinked by immutable links. This leads to links which are pointing to a revision verification hash. 
This allows to verify if the data which was linked is exactly the data which is seen from the user or machine visiting the link.

## Installation

Follow the documentation of mediawiki to install the extension.
https://www.mediawiki.org/wiki/Manual:Extensions#Installing_an_extension

Requirements:

* MediaWiki 1.37 or later
* PHP 7.4 or later versions of PHP 7. (with [Composer](https://getcomposer.org/))
* [Node.js](https://nodejs.org/en/) 16.x , or later. (with [npm](https://nodejs.org/en/download/package-manager/))

`git clone https://github.com/inblockio/mediawiki-extensions-Aqua` into the mediawiki-extenions folder

Add `wfLoadExtension( 'DataAccounting' );` to the Localsettings.php to load the extension.
This is subject to change https://github.com/inblockio/mediawiki-extensions-Aqua/issues/343.
Add `wfLoadExtension( 'mediawiki-extensions-Aqua' );` if it's already the new directory path.

## Testing

This extension implements the **[recommended entry points](https://www.mediawiki.org/wiki/Continuous_integration/Entry_points)** of Wikimedia CI for PHP and Front-end projects.

Run `composer update` in the `extensions/mediawiki-extensions-Aqua/` directory. This pulls in code analysis tools.
You might want to mark `extensions/mediawiki-extensions-Aqua/vendor/` as excluded in your IDE or tools to avoid
them loading certain libraries twice.

### Running tests and CI checks

You can use the `Makefile` by running make commands in the `mediawiki-extensions-Aqua` directory.

* `make ci`: Run everything - TODO: include front-end tests
* `make test`: Run all tests - TODO: include front-end tests
* `make cs`: Run all style checks and static analysis

See the `Makefile` contents for all commands and how to run them without Make.

### Front-end development

To run the checks for JavaScript, JSON, and CSS:

* Run `npm install`

This will install testing software to `node_modules/` in the current directory/

Now, run `npm test` to run the automated front-end code checks.

## Helpful comments
Login to docker
'docker exec -it pkc_database_1 bash' to enter the docker commandline.
Access MYSQL Database:
Inside the bash prompt: 'mysql -u wikiuser -p my_wiki' and enter your password which you can find in the dockercompose.yml file.
SHOW DATABASES;
USE my_wiki;
SELECT * FROM revision_verification;
SELECT * FROM page_witness;

If the extension is running and working, you will see entries in revision_verification after doing your first page edits with the extension activated.

2. cp -r DataAccounting PKC/mountPoint/extensions
3. docker exec pkc_mediawiki_1 php /var/www/html/maintenance/update.php
*if you manually install DataAccounting extension you need to run the maintenance script to load the extension and update the sql database schemas

If you wan't to test a branch by using our current deployment pipe-line (GitHub autobuild actions) use  the following steps:
* you can mount the mediawiki-extension-aqua repo via a volume to develop directly against it (see https://github.com/inblockio/aqua-pkc
* if you want to test a specific branch, you can change the branch you are using in the compose files from :master to the branch you want to test. There should be an image available with that branch.


Known Limitations:
* [Witnessing] There is a limitation, that you need to witness at least two pages for the Witnessing to work, if only one page is witnessed the structed merkle tree will be missing and the verification will fail https://github.com/inblockio/mediawiki-extensions-Aqua/issues/423
* [Import] Files being imported by the import API are not handled by the Inbox https://github.com/inblockio/mediawiki-extensions-Aqua/issues/422
* [Inbox and Export] When exporting an Aqua-Chain which is present with the container and within the Inbox they are both exported when using the export function, exporting also all forks and revisions of one genesis hash https://github.com/inblockio/mediawiki-extensions-Aqua/issues/400
* [API] has to be passed the english page name. If you pass Vorlage:Test to an english PKC it has no way of figuring out what this is, no matter what we do. https://github.com/inblockio/mediawiki-extensions-Aqua/issues/335
* [Signing] Can't sign PDF file pages: Metamask not loaded on URL's with *.pdf in the end https://github.com/inblockio/mediawiki-extensions-Aqua/issues/239
