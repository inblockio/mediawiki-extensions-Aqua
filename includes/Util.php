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

function generateDomainId() {
	//*todo* import public key via wizard instead of autogenerating random
	//value
	$randomval = '';
	for ( $i = 0; $i < 10; $i++ ) {
		$randomval .= chr( rand( 65, 90 ) );
	}
	$domain_id = getHashSum( $randomval );
	//print $domain_id;
	return substr( $domain_id, 0, 10 );
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
