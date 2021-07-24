<?php

// Common functions used by the API server and the extension in special pages.

namespace MediaWiki\Extension\Example;

use MediaWiki\MediaWikiServices;

# include / exclude for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

function get_page_all_rev($page_title) {
	//Database Query
	$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
	$dbr = $lb->getConnectionRef( DB_REPLICA );
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
	$revs = get_page_all_rev($page_title);
	return count($revs);
}

function requestMerkleProof($witness_event_id, $page_verification_hash, $depth) {
	//IF query returns a left or right leaf empty, it means the successor string will be identifical the next layer up. In this case it is required to read the depth and start the query with a depth parameter -1 to go to the next layer. This is repeated until the left or right leaf is present and the successor hash different.

	$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
	$dbr = $lb->getConnectionRef( DB_REPLICA );

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
	foreach( $res as $row ) {
		array_push($output,
			['witness_event_id' => $row->witness_event_id,
			 'depth' => $row->depth,
			 'left_leaf' => $row->left_leaf,
			 'right_leaf' => $row->right_leaf,
			 'successor' => $row->successor,
			]
		);
	}
	return $output;
}

function getWitnessData($witness_event_id) {
	$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
	$dbr = $lb->getConnectionRef( DB_REPLICA );

	$row = $dbr->selectRow(
		'witness_events',
		[
			'domain_id',
			'page_manifest_title',
			'witness_event_verification_hash',
			'witness_network',
			'smart_contract_address',
			'page_manifest_verification_hash',
			'merkle_root'
		],
		[ 'witness_event_id' => $witness_event_id],
		__METHOD__
	);

	$output = [
		'domain_id' => $row->domain_id,
		'page_manifest_title' => $row->page_manifest_title,
		'witness_event_verification_hash' => $row->witness_event_verification_hash,
		'witness_network' => $row->witness_network,
		'smart_contract_address' => $row->smart_contract_address,
		'page_manifest_verification_hash' => $row->page_manifest_verification_hash,
		'merkle_root' => $row->merkle_root
	];
	return $output;
}
