# DataAccounting
This is a code extension for MediaWiki to implement the basic principles of DataAccounting. This is done by using the following modules:
* Create Verified Page History
* Verify Page History
* Export Verified Page History
* Page Witnessing

All Modules interface with the javascript frontend and the maria DB backend. To ensure that we have a modular approach to be able to easy maintain the extention we use Model-View-Controller to seperate functionalities clearly.

# Frontend with Metamask as our default Wallet

# We use example extension for MediaWiki

The Examples extension is a collection of small example features that implement
common extension interfaces in MediaWiki.

The basic structure of this repository is based on the BoilerPlate extension.

## Usage

This repository is for reading, and contains verbose guidances and comments
along the way. You can freely copy snippets from here. To start your own
extension, it is recommended to copy the BoilerPlate extension instead.

## Testing

This extension implements the **[recommended entry points](https://www.mediawiki.org/wiki/Continuous_integration/Entry_points)** of Wikimedia CI for PHP and Front-end projects.

Before you can test and build code locally, you need:

* PHP 7.1, or later. (with [Composer](https://getcomposer.org/))
* [Node.js](https://nodejs.org/en/) 10, or later. (with [npm](https://nodejs.org/en/download/package-manager/))

### PHP

To run the PHP code checks and unit tests:

* Run `composer update`

This will install testing software to `vendor/` in the current directory.

Now, run `compose test` whenever you want to run the automated checks and tests.

### Front-end

To run the checks for JavaScript, JSON, and CSS:

* Run `npm install`

This will intall testing software to `node_modules/` in the current directory/

Now, run `npm test` to run the automated front-end code checks..

## Contributing

```bash
git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/examples.git
```
## Helpful comments
Login to docker
'docker exec -it pkc_database_1 bash' to enter the docker commandline.
Access MYSQL Database:
Inside the bash prompt: 'mysql -u wikiuser -p my_wiki' and enter your password which you can find in the dockercompose.yml file.
SHOW DATABASES;
USE my_wiki;
SELECT * FROM page_verification;
SELECT * FROM page_witness;

2. cp -r DataAccounting PKC/mountPoint/extensions
3. docker exec pkc_mediawiki_1 php /var/www/html/maintenance/update.php
