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

