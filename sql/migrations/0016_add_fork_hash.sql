ALTER TABLE revision_verification
ADD COLUMN fork_hash VARCHAR(128) AFTER verification_hash;