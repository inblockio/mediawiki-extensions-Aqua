#!/usr/bin/env bash

# Drop all tables created by the Data accounting extension.
docker exec -it pkc_database_1 mysql -u wikiuser -p my_wiki --execute="DROP TABLE IF EXISTS page_verification,witness_events,witness_page,witness_merkle_tree;"
docker exec pkc_mediawiki_1 php /var/www/html/maintenance/update.php
