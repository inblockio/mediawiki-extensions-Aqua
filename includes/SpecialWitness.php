<?php
/**
 * This Special Page is used to Generate Domain Snapshots for Witness events
 * Input is the list of all verified pages with the latest revision id and verification hashes which are stored in the table page_list which are printed in section 1 of the Domain Snapshot. This is used as input for generating and populating the table witness_merkle_tree.
 * The witness_merkle_tree is printed out on section 2 of the Domain Snapshot.
 * Output is a Domain Snapshot # as well as a redirect link to the SpecialPage:WitnessPublisher
 *
 * @file
 */

namespace DataAccounting;

use DataAccounting\Verification\Hasher;
use DataAccounting\Verification\WitnessingEngine;
use Exception;

use Config;
use CommentStoreComment;
use DataAccounting\Verification\VerificationEngine;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\SlotRecord;
use PermissionsError;
use Rht\Merkle\FixedSizeTree;
use SpecialPage;
use Title;
use TitleFactory;
use User;
use OutputPage;
use WikiPage;
use WikitextContent;
use Wikimedia\Rdbms\LoadBalancer;

class SpecialWitness extends SpecialPage {

	private PermissionManager $permManager;

	private LoadBalancer $lb;

	private TitleFactory $titleFactory;

	private VerificationEngine $verificationEngine;

	private WitnessingEngine $witnessingEngine;

	/**
	 * Initialize the special page.
	 * @param PermissionManager $permManager
	 * @param LoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param VerificationEngine $verificationEngine
	 * @param WitnessingEngine $witnessingEngine
	 */
	public function __construct(
		PermissionManager $permManager, LoadBalancer $lb,
		TitleFactory $titleFactory, VerificationEngine $verificationEngine,
		WitnessingEngine $witnessingEngine
	) {
		// A special page should at least have a name.
		// We do this by calling the parent class (the SpecialPage class)
		// constructor method with the name as first and only parameter.
		parent::__construct( 'Witness' );
		$this->permManager = $permManager;
		$this->lb = $lb;
		$this->titleFactory = $titleFactory;
		$this->verificationEngine = $verificationEngine;
		$this->witnessingEngine = $witnessingEngine;
	}

	/**
	 * Shows the page to the user.
	 *
	 * @param string|null $sub The subpage string argument (if any).
	 *
	 * @throws PermissionsError
	 */
	public function execute( $sub ) {
		$this->setHeaders();

		$user = $this->getUser();
		if ( !$this->permManager->userHasRight( $user, 'import' ) ) {
			throw new PermissionsError( 'import' );
		}

		$htmlForm = new HTMLForm( [], $this->getContext(), 'daDomainSnapshot' );
		$htmlForm->setSubmitText( $this->msg( 'da-specialwitness-submit-label' ) );
		$htmlForm->setSubmitCallback( [ $this, 'generateDomainSnapshot' ] );
		$htmlForm->show();
	}

	public function helperGenerateDomainSnapshotTable(
		OutputPage $out,
		int $witness_event_id,
		string $output
	): array {
		// For the table
		$output .= <<<EOD

			{| class="table table-bordered"
			|-
			! {$this->msg( 'da-specialwitness-snapshottable-header-index' )->plain()}
			! {$this->msg( 'da-specialwitness-snapshottable-header-pagetitle' )->plain()}
			! {$this->msg( 'da-specialwitness-snapshottable-header-verificationhash' )->plain()}
			! {$this->msg( 'da-specialwitness-snapshottable-header-revision' )->plain()}

		EOD;

		// We include all pages which have a verification_hash and take the
		// last one of each page-object. Excluding the Domain Snapshot pages
		// identified by 'Data Accounting:%'.
		$res = $this->lb->getConnection( DB_REPLICA )->select(
			'revision_verification',
			[ 'page_title', 'max(rev_id) as rev_id' ],
			[
				"page_title NOT LIKE 'Data Accounting:%'",
				"page_title NOT LIKE 'MediaWiki:%'",
			],
			__METHOD__,
			[ 'GROUP BY' => 'page_title' ]
		);

		$tableIndexCount = 1;
		$verification_hashes = [];
		foreach ( $res as $row ) {
			$row3 = $this->lb->getConnection( DB_REPLICA )->selectRow(
				'revision_verification',
				[ 'verification_hash', 'domain_id' ],
				[ 'rev_id' => $row->rev_id ],
				__METHOD__,
			);

			$vhash = $row3->verification_hash;
			if ( $vhash === null ) {
				$title = $row->page_title;
				$out->addWikiTextAsContent(
					$this->msg( 'da-specialwitness-error-emptyhash' )->params( $title )->parse()
				);
				return false;
			}

			// We do not update the witnessEventID of the revision because it
			// is not witnessed / published yet.
			// We aggregate them here so they can be witnessed in the future.
			$this->lb->getConnection( DB_PRIMARY )->insert(
				'witness_page',
				[
					'witness_event_id' => $witness_event_id,
					'domain_id' => $row3->domain_id,
					'page_title' => $row->page_title,
					'rev_id' => $row->rev_id,
					'revision_verification_hash' => $vhash,
				],
				""
			);

			array_push( $verification_hashes, $vhash );

			$revisionWikiLink = $this->msg( 'da-specialwitness-error-revisionpermalink' )
				->params( $row->rev_id )
				->plain();

			// We thumbnail file pages so that they are not taking too much
			// screen space.
			$thumbnailedTitle = $row->page_title;
			if ( str_starts_with( $thumbnailedTitle, "File:" ) ) {
				$thumbnailedTitle .= "|thumb";
			}

			$output .= "|-\n|" . $tableIndexCount . "\n| [[" . $thumbnailedTitle . "]]\n|" . $this->wikilinkifyHash(
					$vhash
				) . "\n|" . $revisionWikiLink . "\n";
			$tableIndexCount++;
		}
		$output .= "|}\n";
		return [ true, $verification_hashes, $output ];
	}

	public function helperGenerateDomainSnapshotMerkleTree( array $verification_hashes ): array {
		$hasher = function ( $data ) {
			return ( new Hasher() )->getHashSum( $data );
		};

		$tree = new FixedSizeTree( count( $verification_hashes ), $hasher, null, true );
		for ( $i = 0; $i < count( $verification_hashes ); $i++ ) {
			$tree->set( $i, $verification_hashes[$i] );
		}
		$treeLayers = $tree->getLayersAsObject();
		return $treeLayers;
	}

	public function helperMakeNewDomainSnapshotpage(
		int $witness_event_id,
		array $treeLayers,
		string $output
	): array {
		//Generate the Domain Snapshot as a new page
		//6942 is custom namespace. See namespace definition in extension.json.
		$title = $this->titleFactory->newFromText( "DomainSnapshot $witness_event_id", 6942 );
		$page = new WikiPage( $title );
		$merkleTreeHtmlText = '<br><pre>' . $this->treePPrint( false, $treeLayers ) . '</pre>';
		$merkleTreeWikitext = $this->treePPrint( true, $treeLayers );
		$pageContent = $output . '<br>' . $merkleTreeWikitext;
		$this->editPageContent(
			$page,
			$pageContent,
			"Page created automatically by [[Special:Witness]]",
			$this->getUser()
		);

		//Get the freshly-generated Domain Snapshot verification hash.
		$rowDMVH = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'revision_verification',
			[ 'verification_hash' ],
			[ 'page_title' => $title ],
			__METHOD__,
		);
		if ( !$rowDMVH ) {
			throw new Exception( "Verification hash not found for $title" );
		}
		$domainSnapshotVH = $rowDMVH->verification_hash;

		return [ $title, $domainSnapshotVH, $merkleTreeHtmlText ];
	}

	public function helperMaybeInsertWitnessEvent(
		string $domain_snapshot_genesis_hash,
		int $witness_event_id,
		Title $title,
		string $merkle_root
	) {
		// Check if $witness_event_id is already present in the witness_events
		// table. If not, do insert.

		$row = $this->lb->getConnection( DB_REPLICA )->selectRow(
			'witness_events',
			[ 'witness_event_id' ],
			[ 'witness_event_id' => $witness_event_id ]
		);
		if ( !$row ) {
			$SmartContractAddress = $this->getConfig()->get( 'SmartContractAddress' );
			$WitnessNetwork = $this->getConfig()->get( 'WitnessNetwork' );
			// If witness_events table doesn't have it, then insert.
			$this->lb->getConnection( DB_PRIMARY )->insert(
				'witness_events',
				[
					'witness_event_id' => $witness_event_id,
					'domain_id' => $this->verificationEngine->getDomainId(),
					'domain_snapshot_title' => $title,
					'domain_snapshot_genesis_hash' => $domain_snapshot_genesis_hash,
					'merkle_root' => $merkle_root,
					'witness_event_verification_hash' => $this->verificationEngine->getHasher()
						->getHashSum( $domain_snapshot_genesis_hash . $merkle_root ),
					'smart_contract_address' => $SmartContractAddress,
					'witness_network' => $WitnessNetwork,
				],
				""
			);
		}
	}

	/**
	 * @see: HTMLForm::trySubmit
	 * @param array $formData
	 * @return bool|string|array|Status
	 *     - Bool true or a good Status object indicates success,
	 *     - Bool false indicates no submission was attempted,
	 *     - Anything else indicates failure. The value may be a fatal Status
	 *       object, an HTML string, or an array of arrays (message keys and
	 *       params) or strings (message keys)
	 */
	public function generateDomainSnapshot( array $formData ) {
		$old_max_witness_event_id = $this->witnessingEngine->getLookup()->getLastWitnessEventId();
		// Set to 0 if null.
		$old_max_witness_event_id = $old_max_witness_event_id === null ? 0 : $old_max_witness_event_id;
		$witness_event_id = $old_max_witness_event_id + 1;

		$output = $this->msg( 'da-specialwitness-snapshot-help' )->plain() . '<br><br>';

		$out = $this->getOutput();
		[ $is_valid, $verification_hashes, $output ] = $this->helperGenerateDomainSnapshotTable(
			$out,
			$witness_event_id,
			$output
		);
		if ( !$is_valid ) {
			// If there is a problem, we exit early.
			return false;
		}

		if ( empty( $verification_hashes ) ) {
			$out->addHTML( $this->msg( 'da-specialwitness-error-emptyhash-description' )->plain() );
			return true;
		}

		$treeLayers = $this->helperGenerateDomainSnapshotMerkleTree( $verification_hashes );

		$out->addWikiTextAsContent( $output );

		// Store the Merkle tree in the DB
		$this->storeMerkleTree( $witness_event_id, $treeLayers );

		//Generate the Domain Snapshot as a new page
		[ $title, $domainSnapshotVH, $merkleTreeHtmlText ] = $this->helperMakeNewDomainSnapshotpage(
			$witness_event_id,
			$treeLayers,
			$output
		);

		//Write results into the witness_events DB
		$merkle_root = array_keys( $treeLayers )[0];

		// Check if $witness_event_id is already present in the witness_events
		// table. If not, do insert.
		$this->helperMaybeInsertWitnessEvent(
			$domainSnapshotVH,
			$witness_event_id,
			$title,
			$merkle_root
		);

		$out->addHTML( $merkleTreeHtmlText );
		$out->addHTML(
			"<br>" . $this->msg( 'da-specialwitness-showmerkleproof-text' )->params( $title )->parse()
		);
		return true;
	}

	protected function getGroupName(): string {
		return 'other';
	}

	public function getConfig(): Config {
		return MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'da' );
	}

	private function shortenHash( string $hash ): string {
		return substr( $hash, 0, 6 ) . "..." . substr( $hash, -6, 6 );
	}

	private function hrefifyHashW( string $hash ): string {
		return "<a href='" . $hash . "'>" . $this->shortenHash( $hash ) . "</a>";
	}

	private function wikilinkifyHash( string $hash ): string {
		$shortened = $this->shortenHash( $hash );
		return "[http://$hash $shortened]";
	}

	private function treePPrint( bool $do_wikitext, array $layers, string $out = "",
		string $prefix = "└─ ", int $level = 0, bool $is_last = true ): string {
		# The default prefix is for level 0
		$length = count( $layers );
		$idx = 1;
		foreach ( $layers as $key => $value ) {
			$is_last = $idx == $length;
			if ( $level == 0 ) {
				$out .= "Merkle root: " . $key . "\n";
			} else {
				$formatted_key = $do_wikitext
					? $this->wikilinkifyHash( $key )
					: $this->hrefifyHashW( $key );
				$glyph = $is_last ? "  └─ " : "  ├─ ";
				$out .= " " . $prefix . $glyph . $formatted_key . "\n";
			}
			if ( $value !== null ) {
				if ( $level == 0 ) {
					$new_prefix = "";
				} else {
					$new_prefix = $prefix . ( $is_last ? "   " : "  │" );
				}
				$out .= $this->treePPrint(
					$do_wikitext,
					$value,
					"",
					$new_prefix,
					$level + 1,
					$is_last
				);
			}
			$idx += 1;
		}
		return $out;
	}

	private function storeMerkleTree( $witness_event_id, $treeLayers, $depth = 0 ) {
		// TODO: Should be done by a service
		foreach ( $treeLayers as $parent => $children ) {
			if ( $children === null ) {
				continue;
			}
			$children_keys = array_keys( $children );
			$this->lb->getConnection( DB_PRIMARY )->insert(
				"witness_merkle_tree",
				[
					"witness_event_id" => $witness_event_id,
					"depth" => $depth,
					"left_leaf" => $children_keys[0],
					"right_leaf" => ( count( $children_keys ) > 1 ) ? $children_keys[1] : null,
					"successor" => $parent,
				]
			);
			$this->storeMerkleTree( $witness_event_id, $children, $depth + 1 );
		}
	}

	/**
	 * @param WikiPage $page
	 * @param string $text
	 * @param string $comment
	 * @param User $user
	 * @throws MWException
	 * @deprecated Use another slot to store this data
	 */
	private function editPageContent( WikiPage $page, string $text, string $comment,
		User $user ): void {
		// This is a copy of deprecated Util.php
		// TODO: should be handled by a service!
		$newContent = new WikitextContent( $text );
		$signatureComment = CommentStoreComment::newUnsavedComment( $comment );
		$updater = $page->newPageUpdater( $user );
		$updater->setContent( SlotRecord::MAIN, $newContent );
		$updater->saveRevision( $signatureComment );
	}
}
