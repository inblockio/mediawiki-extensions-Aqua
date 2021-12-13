ALTER TABLE revision_verification
ADD COLUMN verification_context VARCHAR(128) AFTER rev_id;
