# DataAccounting
This is a code extension for MediaWiki to implement the basic principles of DataAccounting. This is done by using the following modules:
* Create Verified Page History
* Verify Page History
* Export Verified Page History
* Page Witnessing

All Modules interface with the javascript frontend and the maria DB backend. To ensure that we have a modular approach to be able to easy maintain the extention we use Model-View-Controller to seperate functionalities clearly.

## Installation

Follow the documentation of mediawiki to install the extension.
https://www.mediawiki.org/wiki/Manual:Extensions#Installing_an_extension

Requirements:

* MediaWiki 1.35 or later
* PHP 7.4 or later. (with [Composer](https://getcomposer.org/))
* [Node.js](https://nodejs.org/en/) 10, or later. (with [npm](https://nodejs.org/en/download/package-manager/))

## Testing

This extension implements the **[recommended entry points](https://www.mediawiki.org/wiki/Continuous_integration/Entry_points)** of Wikimedia CI for PHP and Front-end projects.

TODO: document or remove need to edit a page

Run `composer update` in the `extensions/DataAccounting/` directory. This pulls in code analysis tools.
You might want to mark `extensions/DataAccounting/vendor/` as excluded in your IDE or tools to avoid
them loading certain libraries twice.

### Running tests and CI checks

You can use the `Makefile` by running make commands in the `DataAccounting` directory.

* `make ci`: Run everything - TODO: include front-end tests
* `make test`: Run all tests - TODO: include front-end tests
* `make cs`: Run all style checks and static analysis

See the `Makefile` contents for all commands and how to run them without Make.

### Front-end

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
