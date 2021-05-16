--
-- Tables for the DataAccounting extension
--

-- Page verification table
CREATE TABLE IF NOT EXISTS `page_verification` (
	`page_verification_id` INT(32) NOT NULL AUTO_INCREMENT, 
	`rev_id` INT COMMENT 'from revision table',
	`page_id` INT COMMENT 'from page table',
	`hash_content` INT(128) COMMENT 'Hashing the page content of the current version',
	`hash_metadata` INT(128) COMMENT 'Hashing all values of related revision_id tuble entry in 
revision table',
	`hash_verification` INT(128) COMMENT 'Combined metadata and content hash',
	`signature` INT(128),
	`public_key` INT(128),
	`hash_prev_verification` INT(128) COMMENT 'Hashing together from last page revision: 
verification hash, signature and public key',
	PRIMARY KEY (`page_verification_id`)
);

CREATE TABLE IF NOT EXISTS `page_witness` (
	`page_witness_id` INT NOT NULL AUTO_INCREMENT,
	`page_id` INT COMMENT 'from page table',
	`page_verification_id` INT COMMENT 'from page_verification table',
	`witness_network` VARCHAR(18) DEFAULT 'Ethereum',
	`network_transaction_id` INT(128),
	`merkle_root` INT COMMENT 'Merkle Root Hash',
	PRIMARY KEY (`page_witness_id`)
);

-- TODO create INDEX later for performance.
