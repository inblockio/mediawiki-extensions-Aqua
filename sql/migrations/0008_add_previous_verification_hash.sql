ALTER TABLE revision_verification
ADD COLUMN previous_verification_hash VARCHAR(128) AFTER verification_hash;
