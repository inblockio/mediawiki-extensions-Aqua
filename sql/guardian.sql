-- Tables for the Guardian (External Verifiction).

-- table which stores all API requests send to the Guardian
CREATE TABLE IF NOT EXISTS `guardian_request` (
	`verification_hash` VARCHAR(128) NOT NULL PRIMARY KEY,
	`page_title` VARCHAR(255),
	`domain_id` VARCHAR(128),
	`mediawiki_timestamp` TIMESTAMP,
	`request_hash` VARCHAR(128)
);

-- table to store reply results from the Guaridan for page_verification
CREATE TABLE IF NOT EXISTS `guardian_page_verification` (
	FOREIGN KEY(verification_hash) REFERENCES page_verification(verification_hash),
	`content` BOOLEAN,
	`metadata` BOOLEAN,
	`signature` BOOLEAN,
	`witness` BOOLEAN,
	`guardian_timestamp` TIMESTAMP,
	`elapsed_time` FLOAT,
	`guardian_id` VARCHAR(128),
	`request_hash` VARCHAR(128),
	`reply_hash` VARCHAR(128)
);

-- table which stores replys from the Guardian for the cached witness onchain lookups
CREATE TABLE IF NOT EXISTS `guardian_witness_events` (
	FOREIGN KEY(witness_event_verification_hash) REFERENCES witness_events(witness_event_verification_hash),
	-- indicates if the lookup failed or succeeded
	`status` BOOLEAN,
	`guardian_timestamp` TIMESTAMP,
	`guardian_id` VARCHAR(128),
	`request_hash` VARCHAR(128)
);
