<?php

use MediaWiki\MediaWikiServices;

// For editPageContent().
use MediaWiki\Revision\SlotRecord;

function getHashSum( $inputStr ) {
	if ( $inputStr == '' ) {
		return '';
	}
	return hash( "sha3-512", $inputStr, false );
}

function generateRandomHash() {
	// Returns a hash sum (calculated using getHashSum) of n characters.
	$randomval = '';
	for ( $i = 0; $i < 10; $i++ ) {
		$randomval .= chr( rand( 65, 90 ) );
	}
	return getHashSum( $randomval );
}

function generateDomainId() {
	$domain_id_full = generateRandomHash();
	return substr( $domain_id_full, 0, 10 );
}

function getDomainId(): string {
	return MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'da' )
		->get( 'DomainID' );
}

function editPageContent( $page, string $text, string $comment, $user ) {
	$newContent = new WikitextContent( $text );
	$signatureComment = CommentStoreComment::newUnsavedComment( $comment );
	$updater = $page->newPageUpdater( $user );
	$updater->setContent( SlotRecord::MAIN, $newContent );
	$updater->saveRevision( $signatureComment );
}
