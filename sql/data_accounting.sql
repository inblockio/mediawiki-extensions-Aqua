--
-- Tables for the DataAccounting extension
--

-- Page verification table
CREATE TABLE IF NOT EXISTS `page_verification` (
	`page_verification_id` INT(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	`domain_id` VARCHAR(128),
	`page_title` VARCHAR(255),
	`page_id` INT COMMENT 'from page table',
	`rev_id` INT UNIQUE COMMENT 'from revision table',
	`hash_content` VARCHAR(128) DEFAULT '' COMMENT 'Hashing the page content of the current version',
    `time_stamp` VARCHAR(128) COMMENT 'write the timestamp of the revision in to the DB',
	`hash_metadata` VARCHAR(128) DEFAULT '' COMMENT 'Hashing all values of related revision_id tuble entry in revision table',
	`verification_hash` VARCHAR(128) COMMENT 'Combined metadata and content hash',
    `signature_hash` VARCHAR(128) DEFAULT '' COMMENT 'Hash of signature data (signature + public_key)',
	`signature` VARCHAR(256) DEFAULT '',
	`public_key` VARCHAR(256) DEFAULT '',
	`wallet_address` VARCHAR(128) DEFAULT '',
	`witness_event_id` INT(32) COMMENT 'Shows if revision was witnessed, an Index for witness_events table',
	`source` VARCHAR(128) COMMENT 'possible values are "imported", "default"'
);

CREATE TABLE IF NOT EXISTS `witness_events` (
        `witness_event_id` INT(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `domain_id` VARCHAR(128) COMMENT 'to make page_title unique',
        `domain_manifest_title` VARCHAR(255) COMMENT 'from page_verification',
        `witness_hash` VARCHAR(128) COMMENT 'Hashes together domain_manifest_verification hash + merkle_root + witness_network + smart contract address',
        `domain_manifest_verification_hash` VARCHAR(128) COMMENT 'from page_verification table',
        `merkle_root` VARCHAR(128) COMMENT 'Merkle Root Hash',
        `witness_event_verification_hash` VARCHAR(128) COMMENT 'XOR of domain_manifest_verification_hash and merkle_root - populated when witness event is triggered',
        `witness_network` VARCHAR(128) DEFAULT 'PLEASE SET THE VARIABLE IN THE SPECIALPAGE:WITNESS' COMMENT 'populated by SpecialPage:Witness configuration input fields',
        `smart_contract_address` VARCHAR(128) DEFAULT 'PLEASE SET THE VARIABLE IN THE SPECIALPAGE:WITNESS' COMMENT 'populated by SpecialPage:Witness configuration input fields',
        `witness_event_transaction_hash` VARCHAR(128) DEFAULT 'PUBLISH WITNESS HASH TO BLOCKCHAIN TO POPULATE',
        `sender_account_address` VARCHAR(128) DEFAULT 'PUBLISH WITNESS HASH TO BLOCKCHAIN TO POPULATE' COMMENT 'is populated after witness_event has been executed via RESTAPI',
        `source` VARCHAR(128) Comment 'possible values are "imported", "default"'
    );

CREATE TABLE IF NOT EXISTS `witness_page` (
 `id` INT(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
 `witness_event_id` INT(32) COMMENT 'ID of the related Witness_Event',
 `domain_id` VARCHAR(128) COMMENT 'to make page_title unique',
 `page_title` VARCHAR(255) COMMENT 'from page_verification',
 `rev_id` VARCHAR(128) COMMENT 'from page_verification',
 `page_verification_hash` VARCHAR(128) COMMENT 'Input values for merkle tree'
 );

CREATE TABLE IF NOT EXISTS `witness_merkle_tree` (
    `INDEX` INT(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
    `witness_event_id` INT(32) COMMENT 'ID of the related Witness_Event',
    `depth` VARCHAR(64) COMMENT 'the depth of the node',
    `left_leaf` VARCHAR(128),
    `right_leaf` VARCHAR(128),
    `successor` VARCHAR(128)
);

-- TODO create INDEX later for performance.
