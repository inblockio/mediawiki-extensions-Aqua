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
	for ( $i = 0; $i < 128; $i++ ) {
		$randomval .= chr( rand( 65, 90 ) );
	}
	return getHashSum( $randomval );
}

function generateDomainId() {
	$domain_id_full = generateRandomHash();
	return substr( $domain_id_full, 0, 10 );
}

function getDomainId(): string {
	$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'da' );
	$domainID = $config->get( 'DomainID' );
	if ( $domainID === "UnspecifiedDomainId" ) {
		// A default domain ID is still used, so we generate a new one
		$domainID = generateDomainId();
		$config->set( 'DomainID', $domainID );
	}
	return $domainID;
}

function editPageContent( $page, string $text, string $comment, $user ) {
	$newContent = new WikitextContent( $text );
	$signatureComment = CommentStoreComment::newUnsavedComment( $comment );
	$updater = $page->newPageUpdater( $user );
	$updater->setContent( SlotRecord::MAIN, $newContent );
	$updater->saveRevision( $signatureComment );
}
