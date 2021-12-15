<?php

use MediaWiki\MediaWikiServices;

// For editPageContent().
use MediaWiki\Revision\SlotRecord;

/**
 * @param string $inputStr
 * @return string
 * @deprecated Use VerificationEngine
 */
function getHashSum( $inputStr ): string {
	if ( $inputStr == '' ) {
		return '';
	}
	return hash( "sha3-512", $inputStr, false );
}

function generateRandomHash(): string {
	// Returns a hash sum (calculated using getHashSum) of n characters.
	$randomval = '';
	for ( $i = 0; $i < 128; $i++ ) {
		$randomval .= chr( rand( 65, 90 ) );
	}
	return getHashSum( $randomval );
}

function generateDomainId(): string {
	$domain_id_full = generateRandomHash();
	return substr( $domain_id_full, 0, 10 );
}

/**
 * @return string
 * @deprecated Use VerificationEngine
 */
function getDomainId(): string {
	$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'da' );
	$domainID = (string)$config->get( 'DomainID' );
	if ( $domainID === "UnspecifiedDomainId" ) {
		// A default domain ID is still used, so we generate a new one
		$domainID = generateDomainId();
		$config->set( 'DomainID', $domainID );
	}
	return $domainID;
}

/**
 * @param WikiPage $page
 * @param string $text
 * @param string $comment
 * @param User $user
 * @throws MWException
 * @deprecated Use another slot to store this data
 */
function editPageContent( WikiPage $page, string $text, string $comment, User $user ): void {
	$newContent = new WikitextContent( $text );
	$signatureComment = CommentStoreComment::newUnsavedComment( $comment );
	$updater = $page->newPageUpdater( $user );
	$updater->setContent( SlotRecord::MAIN, $newContent );
	$updater->saveRevision( $signatureComment );
}
