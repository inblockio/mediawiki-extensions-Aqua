ALTER TABLE revision_verification
ADD COLUMN previous_verification_hash VARCHAR(128) DEFAULT '' AFTER verification_hash;
