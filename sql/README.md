To do DB migration, run e.g.
docker exec -i micro-pkc-database mysql -u wikiuser -pexample my_wiki < sql/migrations/0001_rename_hash_verification.sql
