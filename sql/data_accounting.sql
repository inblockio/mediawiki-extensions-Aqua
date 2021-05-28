--
-- Tables for the DataAccounting extension
--

-- Page verification table
CREATE TABLE IF NOT EXISTS `page_verification` (
	`page_verification_id` INT(32) NOT NULL AUTO_INCREMENT, 
	`page_title` VARCHAR (128), 
	`page_id` INT COMMENT 'from page table',
	`rev_id` INT COMMENT 'from revision table',
	`hash_content` VARCHAR(128) COMMENT 'Hashing the page content of the current version',
    `time_stamp` VARCHAR(128) COMMENT 'write the timestamp of the revision in to the DB',
	`hash_metadata` VARCHAR(128) COMMENT 'Hashing all values of related revision_id tuble entry in 
revision table',
	`hash_verification` VARCHAR(128) COMMENT 'Combined metadata and content hash',
	`signature` VARCHAR(256),
	`public_key` VARCHAR(256),
	`wallet_address` VARCHAR(128),
        `debug` VARCHAR(1000),
	PRIMARY KEY (`page_verification_id`)
);

CREATE TABLE IF NOT EXISTS `page_witness` (
	`page_witness_id` INT NOT NULL AUTO_INCREMENT,
	`page_id` INT COMMENT 'from page table',
	-- From rev_id table
	`rev_id` INT,
	`page_verification_id` INT COMMENT 'from page_verification table',
	`witness_network` VARCHAR(18) DEFAULT 'Ethereum',
	`network_transaction_id` VARCHAR(128),
	`merkle_root` VARCHAR(128) COMMENT 'Merkle Root Hash',
	PRIMARY KEY (`page_witness_id`)
);

-- TODO create INDEX later for performance.
