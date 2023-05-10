<?php

namespace DataAccounting\Verification;

use MediaWiki\Revision\RevisionRecord;

class Hasher {
	/**
	 * @param string $input
	 * @return string
	 */
	public function getHashSum( $input ): string {
		if ( $input == '' ) {
			return '';
		}
		return hash( "sha3-512", $input, false );
	}

	/**
	 * @return string
	 */
	public function generateDomainId(): string {
		$domainIdFull = $this->generateRandomHash();
		return substr( $domainIdFull, 0, 10 );
	}

	/**
	 * @return string
	 */
	private function generateRandomHash(): string {
		// Returns a hash sum (calculated using getHashSum) of n characters.
		$randomval = '';
		for ( $i = 0; $i < 128; $i++ ) {
			$randomval .= chr( rand( 65, 90 ) );
		}
		return $this->getHashSum( $randomval );
	}

	/**
	 * @param RevisionRecord $rev
	 * @return string
	 */
	public function calculateContentHash( RevisionRecord $rev ) {
		$pageContent = '';
		// Important! We sort the slot array alphabetically [1], to make it
		// consistent with canonical JSON (see
		// https://datatracker.ietf.org/doc/html/rfc8785).
		// [1] Actually, it is
		// > MUST order the members of all objects lexicographically by the UCS
		// (Unicode Character Set) code points of their names.
		$slots = $rev->getSlots()->getSlotRoles();
		sort( $slots );
		foreach ( $slots as $slot ) {
			$pageContent .= $rev->getContent( $slot )->serialize();
		}
		if ( !$pageContent ) {
			// Empty string hash
			// https://csrc.nist.gov/csrc/media/projects/cryptographic-standards-and-guidelines/documents/examples/sha3-512_msg0.pdf
			return 'a69f73cca23a9ac5c8b567dc185a756e97c982164fe25859e0d1dcc1475c80a615b2123af1f5f94c11e3e9402c3ac558';
		}

		return $this->getHashSum( $pageContent );
	}
}
