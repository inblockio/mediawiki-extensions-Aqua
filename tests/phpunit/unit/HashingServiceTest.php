<?php

declare( strict_types = 1 );

namespace DataAccounting\Tests;

use PHPUnit\Framework\TestCase;
use DataAccounting\Hasher\HashingService;

require_once __DIR__ . "/../../../includes/Util.php";

/**
 * @covers \DataAccounting\Hasher\HashingService
 */
class HashingServiceTest extends TestCase {
	private HashingService $hs;
	private string $expectedMetadataHash;
	private string $content;
	private string $expectedContentHash;
	private string $expectedSignatureHash;
	private string $expectedWitnessHash;

	public function setUp(): void {
		$domainID = '95d7c6609a';
		$this->hs = new HashingService( $domainID );
		$this->expectedMetadataHash = '4cc996ffbc5237e15fd4e2fc67795fb7fa9563805ae04071ef20ace02535be2fb336f8de4cb3bebfc7afdefa0420d3ba8085d5a6f891464908bf3a08d57bfb77';
		$this->content = "Look again at that dot. That's here. That's home. That's us. On it everyone you love, everyone you know, everyone you ever heard of, every human being who ever was, lived out their lives. The aggregate of our joy and suffering, thousands of confident religions, ideologies, and economic doctrines, every hunter and forager, every hero and coward, every creator and destroyer of civilization, every king and peasant, every young couple in love, every mother and father, hopeful child, inventor and explorer, every teacher of morals, every corrupt politician, every \"superstar,\" every \"supreme leader,\" every saint and sinner in the history of our species lived there--on a mote of dust suspended in a sunbeam.\n\n<br>\n-- Carl Sagan<br><br><hr><div class=\"toccolours mw-collapsible mw-collapsed\"><div style=\"font-weight:bold;line-height:1.6;\">Data Accounting Signatures</div><div class=\"mw-collapsible-content\">[[User:0xa2026582b94feb9124231fbf7b052c39218954c2|0xa2026582b94feb9124231fbf7b052c39218954c2]] ([[User talk:0xa2026582b94feb9124231fbf7b052c39218954c2|talk]]) 03:52, 27 November 2021 (UTC) <br></div>";
		$this->expectedContentHash = '83bc065434d5efc36876ee3b4b09ad03fbbf9d6c209a8066f2f6257f0841b2617f91dc73eae770e7b19545c257fc79a39bfb7ccac915ac588f892a4298f85397';
		$this->expectedSignatureHash = '9d774d430284394aab69cc16b19fcddd0a9704130d08e74805f7adff0a5f16bf14dde78058d600e157b3264f8e741498b19be24e7764b71b65ac586d36298f02';
		$this->expectedWitnessHash = 'c02ee814b4166d58440f3805c823659c0aaa8a75ed22cee60bf7b9ec114dbbbaf9cdac6b12d25f2d1622030b43455e089a79a2e04b9ce507e9f9e77637ca1424';
	}

	public function testCalculateContentHash(): void {
		$this->assertEquals(
			$this->expectedContentHash,
			getHashSum( $this->content )
		);
	}

	public function testCalculateMetadataHash(): void {
		$timestamp = '20211127035257';
		$prevVH = '701059060ec2f5e2d2bbcae71b04586bb2774d029e00442ec1211c00a610ffd4e0a93b11b0fcf900e218b02929b8a00f932abf332080a33d1198ea1d95402ea7';
		$metadataHash = $this->hs->calculateMetadataHash( $timestamp, $prevVH );
		$this->assertEquals(
			$this->expectedMetadataHash,
			$metadataHash
		);
	}

	public function testCalculateSignatureHash(): void {
		$previousSignature = '0xe930ad52a31ed5b5e981866c1cf68bb09071f55047845c8ef5b81d8d13632710144d500120ce0785242c297c24a6a8927621a5d7f2827d28ee044f05497409401c';
		$previousPublicKey = '0x041518581af65749b3ddc69889df3e5d229bc8ad79279a07ddeb368ade5e1592368c5ff3b69143d7a1e7cf64f7d0774a6724e6eaf138d318d07ddc30f6081ca89a';
		$signatureHash = $this->hs->calculateSignatureHash( $previousSignature, $previousPublicKey );
		$this->assertEquals(
			$this->expectedSignatureHash,
			$signatureHash
		);
	}

	public function testCalculateWitnessHash(): void {
		$domain_manifest_verification_hash = '9df4198c20d31bb46f8ea97417346108b01ca13af20902e1755a1aad5bc3ba689f3731f653b4daa67ef657b96915fd7ac1035ab93938d9afde9c4c13cfd468c7';
		$merkle_root = 'bbc665767bac03f0a16321555c40b28bd6a6da9bc16f736c5680414b266a910d4ae436a091326a8ca6b71d2628326e42191d67214b65b87acf096c176f126447';
		$witness_network = 'goerli';
		$witness_tx_hash = '0x473b0b7b9ad818b9af02c0ab73cd9b186b28b6208c13f7d07554ace0915ca88e';
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
		$expectedVH = '418d8af3796e9b7c66c244465f6342ddf10f57a1172c30bb5ac3b519c09b0df53f01668dcd7d2ee86255bf96f477e57a0ec17d8b8a58630b0dfade370217338b';
		$this->assertEquals(
			$expectedVH,
			$VH
		);
		// Test the next revision in the hash chain, which uses the previous
		// chain's witness hash as an input.
		$actualNextVH = $this->hs->calculateVerificationHash(
			'bbb362540fd4451ac309243b277c4c91fc572871aa322e0d09385f9c09871c15ed36424dc5a12d066695402bb7df6dfafc90af01aac8b58c58b0fcc6495379fa',
			'9062387004712f32f73278b1804416710fef52732737e0807edeab928f4d614ececdb14e1e695f23d4fbe106578fa52588158d6faf5c54c8c569b69385ee84d9',
			'cfc99c25bb799554b06d7dab8e2d602918a9991fac968bf2ac26290e1454f7f2d2e42c192d91bfce97e7fa430b23494ca0c183a46e29d2cc958f83c532e683e3',
			'c02ee814b4166d58440f3805c823659c0aaa8a75ed22cee60bf7b9ec114dbbbaf9cdac6b12d25f2d1622030b43455e089a79a2e04b9ce507e9f9e77637ca1424'
		);
		$this->assertEquals(
			'25d75f33f11add368e0b99fdb3acbf7810cac0ed8604966dde9f3d17b402b4115843b5ecd3186b3692c563841d98e07e94dd26d80b295cb8aa1581d84cefeea2',
			$actualNextVH
		);
	}
}
