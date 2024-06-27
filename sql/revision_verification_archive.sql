CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/revision_verification_archive (
	`domain_id` VARCHAR(128),
	`genesis_hash` VARCHAR(128),
	`page_title` VARCHAR(255),
	`rev_id` INT UNIQUE,
	`verification_hash` VARCHAR(128),
	`witness_event_id` INT(32),
    `timestamp` VARCHAR(14)
);
