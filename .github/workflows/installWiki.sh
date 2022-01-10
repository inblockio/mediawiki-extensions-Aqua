#! /bin/bash

set -ex

# Taken from https://github.com/ProfessionalWiki/Maps/blob/master/.github/workflows/installMediaWiki.sh
# Modifications:
# 1. Set wgScriptPath to ""

MW_BRANCH=$1
EXTENSION_NAME=$2

wget "https://github.com/wikimedia/mediawiki/archive/$MW_BRANCH.tar.gz" -nv

tar -zxf $MW_BRANCH.tar.gz
mv mediawiki-$MW_BRANCH mediawiki

cd mediawiki

# TODO hack to address https://github.com/inblockio/DataAccounting/issues/244.
# Remove this once MediaWiki has made a patch release.
RUN sed -i 's/$this->package->setProvides( \[ $link \] );/$this->package->setProvides( \[ self::MEDIAWIKI_PACKAGE_NAME => $link \] );/' ./includes/composer/ComposerPackageModifier.php

composer install --no-progress --no-interaction --prefer-dist --optimize-autoloader

php maintenance/install.php \
	--dbtype sqlite \
	--dbuser root \
	--dbname mw \
	--dbpath "$(pwd)" \
	--scriptpath="" \
	--pass AdminPassword WikiName AdminUser

echo 'error_reporting(E_ALL| E_STRICT);' >> LocalSettings.php
echo 'ini_set("display_errors", 1);' >> LocalSettings.php
echo '$wgShowExceptionDetails = true;' >> LocalSettings.php
echo '$wgShowDBErrorBacktrace = true;' >> LocalSettings.php
echo '$wgDevelopmentWarnings = true;' >> LocalSettings.php

echo 'wfLoadExtension( "'$EXTENSION_NAME'" );' >> LocalSettings.php

cat <<EOT >> composer.local.json
{
  "require": {
  },
	"extra": {
		"merge-plugin": {
			"merge-dev": true,
			"include": [
				"extensions/$EXTENSION_NAME/composer.json"
			]
		}
	}
}
EOT
