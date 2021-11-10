<?php

// Common functions used by the API server and the extension in special pages.

namespace DataAccounting;

use MediaWiki\MediaWikiServices;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

function get_page_all_revs($page_title, $genesis_hash) {
	//Database Query
	$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
	$dbr = $lb->getConnectionRef( DB_REPLICA );
	//Check if $page_title is a genesis_hash
	if (empty($page_title)) {
		#INSERT LOOP AND PARSE INTO ARRAY TO GET ALL PAGES BY GENESIS_HASH 
		$res = $dbr->select(
			'page_verification',
			[ 'rev_id','page_title', 'genesis_hash','page_id' ],
			'genesis_hash= \''.$genesis_hash.'\'',
			__METHOD__,
			[ 'ORDER BY' => 'rev_id' ]
		);

		$output = array();
		$count = 0;
		foreach( $res as $row ) {
			$data = array();
			$data['page_title'] = $row->page_title;
			$data['genesis_hash'] = $row->genesis_hash;
			$data['page_id'] = $row->page_id;
			$data['rev_id'] = $row->rev_id;
			$output[$count] = $data;
			$count ++;
		}
		return $output;

	}
	#INSERT LOOP AND PARSE INTO ARRAY TO GET ALL PAGES
	$res = $dbr->select(
		'page_verification',
		[ 'rev_id','page_title','page_id' ],
		'page_title= \''.$page_title.'\'',
		__METHOD__,
		[ 'ORDER BY' => 'rev_id' ]
	);

	$output = array();
	$count = 0;
	foreach( $res as $row ) {
		$data = array();
		$data['page_title'] = $row->page_title;
		$data['page_id'] = $row->page_id;
		$data['rev_id'] = $row->rev_id;
		$output[$count] = $data;
		$count ++;
	}
	return $output;
}

function getPageChainHeight($page_title) {
	$revs = get_page_all_revs($page_title);
	return count($revs);
}

function requestMerkleProof($witness_event_id, $page_verification_hash, $depth = null) {
	//IF query returns a left or right leaf empty, it means the successor string will be identifical the next layer up. In this case it is required to read the depth and start the query with a depth parameter -1 to go to the next layer. This is repeated until the left or right leaf is present and the successor hash different.

	$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
	$dbr = $lb->getConnectionRef( DB_REPLICA );

	$final_output = array();

	while (true) {
		if (is_null($depth)) {
			$conds =
				'left_leaf=\'' . $page_verification_hash .
				'\' AND witness_event_id=' . $witness_event_id .
				' OR right_leaf=\'' . $page_verification_hash .
				'\' AND witness_event_id=' . $witness_event_id;
		} else {
			$conds =
				'left_leaf=\'' . $page_verification_hash .
				'\' AND witness_event_id=' . $witness_event_id .
				' AND depth=' .$depth .
				' OR right_leaf=\'' . $page_verification_hash .
				'\'  AND witness_event_id=' . $witness_event_id .
				' AND depth=' . $depth;
		}
		$res = $dbr->select(
			'witness_merkle_tree',
			['witness_event_id',
			 'depth',
			 'left_leaf',
			 'right_leaf',
			 'successor'
			],
			$conds,
		);
		$output = array();
		$max_depth = null;
		foreach( $res as $row ) {
			if (is_null($max_depth) || ($row->depth > $max_depth)) {
				$max_depth = $row->depth;
				$output = [
					'witness_event_id' => $row->witness_event_id,
					'depth' => $row->depth,
					'left_leaf' => $row->left_leaf,
					'right_leaf' => $row->right_leaf,
					'successor' => $row->successor,
				];
			}
		}
		if (empty($output)) {
			break;
		}
		$depth = $max_depth - 1;
		$page_verification_hash = $output['successor'];
		array_push($final_output, $output);
		if ($depth == -1) {
			break;
		}
	}
	return $final_output;
}

function getWitnessData($witness_event_id) {
	if (is_null($witness_event_id)) {
		return '';
	}

	$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
	$dbr = $lb->getConnectionRef( DB_REPLICA );

	$row = $dbr->selectRow(
		'witness_events',
		[
			'domain_id',
			'domain_manifest_title',
			'witness_hash',
			'witness_event_verification_hash',
			'witness_network',
			'smart_contract_address',
			'domain_manifest_verification_hash',
			'merkle_root',
			'witness_event_transaction_hash',
			'sender_account_address',
			'source'
		],
		[ 'witness_event_id' => $witness_event_id],
		__METHOD__
	);

	if (!$row) {
		return '';
	}

	$output = [
		'domain_id' => $row->domain_id,
		'domain_manifest_title' => $row->domain_manifest_title,
		'witness_hash' => $row->witness_hash,
		'witness_event_verification_hash' => $row->witness_event_verification_hash,
		'witness_network' => $row->witness_network,
		'smart_contract_address' => $row->smart_contract_address,
		'domain_manifest_verification_hash' => $row->domain_manifest_verification_hash,
		'merkle_root' => $row->merkle_root,
		'witness_event_transaction_hash' => $row->witness_event_transaction_hash,
		'sender_account_address' => $row->sender_account_address,
		'source' => $row->source
	];
	return $output;
}

function getMaxWitnessEventId($db) {
	$row = $db->selectRow(
		'witness_events',
		[ 'max(witness_event_id) as witness_event_id' ],
		'',
		__METHOD__,
	);
	if (!$row) {
		return null;
	}
	return $row->witness_event_id;
}
