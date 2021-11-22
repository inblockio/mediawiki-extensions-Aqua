<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use PHPUnit\Framework\TestCase;
use DataAccounting\Hasher\HashingService;

require_once __DIR__ . "/../../../includes/Util.php";

/**
 * @covers \DataAccounting\Hasher
 */
class HasherTest extends TestCase {
	private HashingService $hs;
	private string $expectedMetadataHash;
	private string $content;
	private string $expectedContentHash;
	private string $expectedSignatureHash;
	private string $expectedWitnessHash;

	public function setUp(): void {
		$domainID = '6eda4376fa';
		$this->hs = new HashingService( $domainID );
		$this->expectedMetadataHash = '2acc3d342f60d8dd45bc235cd7edb84fb00af82f84455a0566406fdc4b57db210e761103f1e0df8d05a0c938ca1bf7e3858ecf94fcc557a88d940b084ccacf0f';
		$this->content = 'Now I am become Death, the destroyer of worlds.<br><br><hr><div class="toccolours mw-collapsible mw-collapsed"><div style="font-weight:bold;line-height:1.6;">Data Accounting Signatures</div><div class="mw-collapsible-content">[[User:0x1ad5da43de60aa7d311f9b4e9c3342c155e6d2e0|0x1ad5da43de60aa7d311f9b4e9c3342c155e6d2e0]] ([[User talk:0x1ad5da43de60aa7d311f9b4e9c3342c155e6d2e0|talk]]) 23:45, 22 November 2021 (UTC) <br></div>';
		$this->expectedContentHash = '193249c9e9090557dfc1c4305b450fbfe12216a7fff98ecdc6ac6ce38edda8e90b8c85c6fe83991a602b504f04d66d092fad0b9b51e11fdc08c2550815f7a097';
		$this->expectedSignatureHash = '42b15d9ef32d9fb58061e45351c70a1f06c00bbd19ee4451cfb34a45fe73f53adffd5d2fde9da2c3a549102566cfae0c61994e2cd634d7a65609c7d799c4a342';
		$this->expectedWitnessHash = 'd7a53836f1e772c3b027684cbeb4e6beffd94d5bc96ed5043bd4321d7df167958ccc3da3655f9d32c2538ad4ca6782e2a37240937d667dbf2adc2f8d84d85972';
	}

	public function testCalculateContentHash(): void {
		$this->assertEquals(
			$this->expectedContentHash,
			getHashSum( $this->content )
		);
	}

	public function testCalculateMetadataHash(): void {
		$timestamp = '20211122234526';
		$prevVH = '651ae0f59aff694d171c9875c676a021e8d9b1609de123923b19ce13076d749eda1d6d8f063fa0cfed8bcb95916459c9f09dd72df7d3f24aafdb3c1201511008';
		$metadataHash = $this->hs->calculateMetadataHash( $timestamp, $prevVH );
		$this->assertEquals(
			$this->expectedMetadataHash,
			$metadataHash
		);
	}

	public function testCalculateSignatureHash(): void {
		$previousSignature = '0x7dfbd462fe6806385ec2355ef8a55c280a8cee6492130e9b71b83211a3be47812374cbe1c2ca99684fdcd3e5bd3207b44e7d6be2eb0133ac026cf141efb637961b';
		$previousPublicKey = '0x04f00d6e178562a62ec9e595da4294f640dca429fc98e7128b8e7ee83039912d64a924bea34e629b9b45990c65e92efc3d74533f870479d10ff895834fff4fa1e8';
		$signatureHash = $this->hs->calculateSignatureHash( $previousSignature, $previousPublicKey );
		$this->assertEquals(
			$this->expectedSignatureHash,
			$signatureHash
		);
	}

	public function testCalculateWitnessHash(): void {
		$domain_manifest_verification_hash = 'e0d2acd5db990b5e154f494b0c4b18cf96420b852a55de785633a20baeb5d42815212c104ccdf25de021bb7d031f7145f64d46b77ae10f9afe19bcef2f43225c';
		$merkle_root = '4e8192aa9adf6398e2ba83b7400a32e1fa075ce8f242d8a94f8621ab4300c003ef1c4d03793ddfb6db0f9c975dffb25d85bba699abe522d26e89e7453f2624e0';
		$witness_network = 'goerli';
		$witness_tx_hash = '0xd7d33a9ef2bd990880050c06271b20d94b528795c9eac3edc979410d5414ac02';
		$wh = $this->hs->calculateWitnessHash(
			$domain_manifest_verification_hash,
			$merkle_root,
			$witness_network,
			$witness_tx_hash
		);
		$this->assertEquals(
			$this->expectedWitnessHash,
			$wh
		);
	}

	public function testCalculateVerificationHash(): void {
		// Test when the previous witness hash is empty
		$VH = $this->hs->calculateVerificationHash(
			$this->expectedContentHash,
			$this->expectedMetadataHash,
			$this->expectedSignatureHash,
			''
		);
		$expectedVH = 'b33d0e6ef5d239252a426fc5098a87bb1b79abc4e4cfe6fdaccf72904b5ec8b7e1b2c7a0f54abbe05cb1f408aa746f8d3bff5a16b4ec39e7e9bda0bf8531617a';
		$this->assertEquals(
			$expectedVH,
			$VH
		);
		// Test the next revision in the hash chain, which uses the previous
		// chain's witness hash as an input.
		$actualNextVH = $this->hs->calculateVerificationHash(
			'f187785992eaeb9043f98c3d70b2c8cd856c9a0b81df9a2f1ddc859cc72839e98c9546fc87db4e4476459b149782cd7a7e7d50850550e81fee729a7d76ee7f1f',
			'e546af53d7f3d5985127b821afb6d27bef658a8160199acfd6f709051072928aa02c87374fa8f9bf9b0c71f7a2627d512105cc9772305d8fcb54e3bdd61f4650',
			'5ace0e9cfa3fda1ce8c634d892c3f978bcbab091079884604da7c36fd4ad34e6db87dc886c1de514585152e3e25ebe35a3ac28a09d0aeb44020bf34bba1a5bfa',
			'd7a53836f1e772c3b027684cbeb4e6beffd94d5bc96ed5043bd4321d7df167958ccc3da3655f9d32c2538ad4ca6782e2a37240937d667dbf2adc2f8d84d85972'
		);
		$this->assertEquals(
			'd16af8f3550048d9d40a587978e29d29372e6eaa9535d2de7b1d34bd2cb4b8217c65f7398ad992ab838dd18ac7b652305ffd931cf237db79a1d3f62b6aba9a74',
			$actualNextVH
		);
	}
}
