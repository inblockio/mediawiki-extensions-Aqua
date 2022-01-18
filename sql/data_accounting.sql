--
-- Tables for the DataAccounting extension
--

-- Page verification table
CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/revision_verification (
	-- Add new fields to DataAccounting\Verification\VerificationLookup
	`revision_verification_id` INT(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
	`domain_id` VARCHAR(128),
	`genesis_hash` VARCHAR(128), -- Global unique identifier for Mobile Permissioned Content Blockchain (MPCB), represented as a MediaWiki page.
	`page_title` VARCHAR(255),
    -- from page table
	`page_id` INT,
    -- from revision table
	`rev_id` INT UNIQUE,
    -- provides context for the verification of this revision
	`verification_context` TEXT,
    -- Hashing the page content of the current version
	`content_hash` VARCHAR(128) DEFAULT '',
    -- write the timestamp of the revision in to the DB
    `time_stamp` VARCHAR(128),
    -- Hashing all values of related revision_id tuble entry in revision table
	`metadata_hash` VARCHAR(128) DEFAULT '',
    -- Combined metadata, content hash, optional signature_hash and witness_hash
	`verification_hash` VARCHAR(128),
    -- Previous verification hash of the previous revious, if the current
    -- revision is a genesis, it will remain empty.
	`previous_verification_hash` VARCHAR(128) DEFAULT '',
    -- Hash of signature data (signature + public_key)
    `signature_hash` VARCHAR(128) DEFAULT '',
	`signature` VARCHAR(256) DEFAULT '',
	`public_key` VARCHAR(256) DEFAULT '',
	`wallet_address` VARCHAR(128) DEFAULT '',
    -- Shows if revision was witnessed, an Index for witness_events table
	`witness_event_id` INT(32),
    -- possible values are "imported", "default"
	`source` VARCHAR(128)
);

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/witness_events (
		-- Add new fields to DataAccounting\Verification\WitnessLookup
        `witness_event_id` INT(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        -- to make page_title unique
        `domain_id` VARCHAR(128),
        -- from revision_verification
        `domain_snapshot_title` VARCHAR(255),
        -- Hashes together domain_snapshot_genesis_hash + merkle_root + witness_network + smart contract address
        `witness_hash` VARCHAR(128),
        -- from revision_verification table
        `domain_snapshot_genesis_hash` VARCHAR(128),
        -- Merkle Root Hash
        `merkle_root` VARCHAR(128),
        -- XOR of domain_snapshot_genesis_hash and merkle_root - populated when witness event is triggered
        `witness_event_verification_hash` VARCHAR(128),
        -- populated by SpecialPage:Witness configuration input fields
        `witness_network` VARCHAR(128) DEFAULT 'PLEASE SET THE VARIABLE IN THE SPECIALPAGE:WITNESS',
        -- populated by SpecialPage:Witness configuration input fields
        `smart_contract_address` VARCHAR(128) DEFAULT 'PLEASE SET THE VARIABLE IN THE SPECIALPAGE:WITNESS',
        `witness_event_transaction_hash` VARCHAR(128) DEFAULT 'PUBLISH WITNESS HASH TO BLOCKCHAIN TO POPULATE',
        -- is populated after witness_event has been executed via RESTAPI
        `sender_account_address` VARCHAR(128) DEFAULT 'PUBLISH WITNESS HASH TO BLOCKCHAIN TO POPULATE',
        -- possible values are "imported", "default"
        `source` VARCHAR(128)
    );

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/witness_page (
	-- Add new fields to DataAccounting\Verification\WitnessLookup
    `id` INT(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- ID of the related Witness_Event
    `witness_event_id` INT(32),
    -- to make page_title unique
    `domain_id` VARCHAR(128),
    -- from revision_verification
    `page_title` VARCHAR(255),
    -- from revision_verification
    `rev_id` VARCHAR(128),
    -- Input values for merkle tree
    `revision_verification_hash` VARCHAR(128)
);

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/witness_merkle_tree (
	-- Add new fields to DataAccounting\Verification\WitnessLookup
    `id` INT(32) NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- witness event ID of the related Witness_Event
	`witness_event_id` INT(32),
    -- witness event verification hash of the related Witness_Event
    `witness_event_verification_hash` VARCHAR(128),
    -- the depth of the node
    `depth` INT(32),
    `left_leaf` VARCHAR(128),
    `right_leaf` VARCHAR(128),
    `successor` VARCHAR(128)
);

CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/da_settings (
	`name` VARCHAR(255) NOT NULL,
	`value` mediumblob,
	PRIMARY KEY ( name )
) /*$wgDBTableOptions*/;

-- TODO create INDEX later for performance.
