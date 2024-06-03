ALTER TABLE revision_verification
ADD COLUMN merge_hash VARCHAR(128) AFTER verification_hash;