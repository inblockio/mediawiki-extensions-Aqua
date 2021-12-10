ALTER TABLE revision_verification
ADD COLUMN genesis_hash VARCHAR(128) AFTER domain_id;
