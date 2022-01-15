ALTER TABLE witness_merkle_tree RENAME COLUMN INDEX to id;
ALTER TABLE witness_merkle_tree ADD COLUMN witness_event_verification_hash VARCHAR(128) AFTER witness_event_id;
