<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_api_security\Unit;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use Drupal\bebbo_api_security\Service\AppleAppAttestService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for AppleAppAttestService attestation and assertion logic.
 *
 * @coversDefaultClass \Drupal\bebbo_api_security\Service\AppleAppAttestService
 * @group bebbo_api_security
 */
class AppleAppAttestServiceTest extends TestCase {

  /**
   * The service under test.
   *
   * @var \Drupal\bebbo_api_security\Service\AppleAppAttestService
   */
  private AppleAppAttestService $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $database = $this->createMock(Connection::class);
    $logger = $this->createMock(LoggerInterface::class);

    $mockConfig = $this->createMock(ImmutableConfig::class);
    $mockConfig->method('get')->willReturnMap([
      ['apple_team_id', 'TESTTEAMID'],
      ['apple_bundle_id', 'com.example.testapp'],
      ['apple_production_mode', FALSE],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('bebbo_api_security.settings')
      ->willReturn($mockConfig);

    $this->service = new AppleAppAttestService(
      $database,
      $configFactory,
      $logger,
    );
  }

  /**
   * Tests parseAuthData correctly extracts fields from binary data.
   *
   * @covers ::parseAuthData
   */
  public function testParseAuthDataExtractsFields(): void {
    $rp_id_hash = hash('sha256', 'TESTTEAMID.com.example.testapp', TRUE);
    $flags = chr(0x41);
    $counter = pack('N', 0);
    $aaguid = "appattestdevelop";
    $cred_id = random_bytes(32);
    $cred_id_len = pack('n', strlen($cred_id));
    $cose_key = random_bytes(77);

    $auth_data = $rp_id_hash . $flags . $counter . $aaguid
      . $cred_id_len . $cred_id . $cose_key;

    $method = new \ReflectionMethod($this->service, 'parseAuthData');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->service, $auth_data);

    $this->assertEquals($rp_id_hash, $result['rp_id_hash']);
    $this->assertEquals(0x41, $result['flags']);
    $this->assertEquals(0, $result['counter']);
    $this->assertEquals($aaguid, $result['aaguid']);
    $this->assertEquals($cred_id, $result['credential_id']);
    $this->assertEquals($cose_key, $result['cose_key_bytes']);
  }

  /**
   * Tests parseAuthData with non-zero counter value.
   *
   * @covers ::parseAuthData
   */
  public function testParseAuthDataWithNonZeroCounter(): void {
    $auth_data = str_repeat("\x00", 32)
      . chr(0x01)
      . pack('N', 42)
      . str_repeat("\x00", 16)
      . pack('n', 4)
      . "test"
      . "cosedata";

    $method = new \ReflectionMethod($this->service, 'parseAuthData');
    $method->setAccessible(TRUE);
    $result = $method->invoke($this->service, $auth_data);

    $this->assertEquals(42, $result['counter']);
    $this->assertEquals("test", $result['credential_id']);
  }

  /**
   * Tests coseKeyToPem produces a valid PEM for EC P-256.
   *
   * @covers ::coseKeyToPem
   */
  public function testCoseKeyToPemReturnsValidPem(): void {
    $ec = openssl_pkey_new([
      'curve_name' => 'prime256v1',
      'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    $details = openssl_pkey_get_details($ec);
    $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    $cose_map = MapObject::create()
      ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
      ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
      ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
      ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
      ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y));

    $cose_bytes = (string) $cose_map;

    $method = new \ReflectionMethod($this->service, 'coseKeyToPem');
    $method->setAccessible(TRUE);
    $pem = $method->invoke($this->service, $cose_bytes);

    $this->assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $pem);
    $this->assertStringContainsString('-----END PUBLIC KEY-----', $pem);
    $key = openssl_pkey_get_public($pem);
    $this->assertNotFalse($key);
  }

  /**
   * Tests verifyAttestation rejects invalid base64 input.
   *
   * @covers ::verifyAttestation
   */
  public function testVerifyAttestationRejectsInvalidBase64(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid base64');
    $this->service->verifyAttestation('!!!not-base64!!!', 'key', 'hash', 'dev');
  }

  /**
   * Tests verifyAttestation rejects invalid CBOR data.
   *
   * @covers ::verifyAttestation
   */
  public function testVerifyAttestationRejectsBadCbor(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('CBOR decode failed');
    $this->service->verifyAttestation(
      base64_encode("\xFF\xFE\xFD"),
      'key',
      'hash',
      'dev',
    );
  }

  /**
   * Tests verifyAttestation rejects wrong attestation format.
   *
   * @covers ::verifyAttestation
   */
  public function testVerifyAttestationRejectsWrongFormat(): void {
    $cbor = MapObject::create()
      ->add(TextStringObject::create('fmt'), TextStringObject::create('packed'))
      ->add(TextStringObject::create('authData'), ByteStringObject::create(str_repeat("\x00", 64)))
      ->add(TextStringObject::create('attStmt'), MapObject::create());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unexpected attestation format');
    $this->service->verifyAttestation(
      base64_encode((string) $cbor),
      'key',
      'hash',
      'dev',
    );
  }

  /**
   * Tests verifyAttestation rejects authData that is too short.
   *
   * @covers ::verifyAttestation
   */
  public function testVerifyAttestationRejectsShortAuthData(): void {
    $cbor = MapObject::create()
      ->add(TextStringObject::create('fmt'), TextStringObject::create('apple-appattest'))
      ->add(TextStringObject::create('authData'), ByteStringObject::create(str_repeat("\x00", 20)))
      ->add(TextStringObject::create('attStmt'), MapObject::create());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('authData too short');
    $this->service->verifyAttestation(
      base64_encode((string) $cbor),
      'key',
      'hash',
      'dev',
    );
  }

  /**
   * Tests verifyAssertion rejects invalid base64.
   *
   * @covers ::verifyAssertion
   */
  public function testVerifyAssertionRejectsInvalidBase64(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Invalid base64 assertion');
    $this->service->verifyAssertion('!!!bad!!!', 'hash', 'key', 'pubkey', 0);
  }

  /**
   * Tests verifyAssertion rejects authenticatorData that is too short.
   *
   * @covers ::verifyAssertion
   */
  public function testVerifyAssertionRejectsShortAuthData(): void {
    $cbor = MapObject::create()
      ->add(TextStringObject::create('signature'), ByteStringObject::create('sig'))
      ->add(TextStringObject::create('authenticatorData'), ByteStringObject::create(str_repeat("\x00", 10)));

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('authenticatorData too short');
    $this->service->verifyAssertion(
      base64_encode((string) $cbor),
      'aabb',
      'key',
      'pubkey',
      0,
    );
  }

  /**
   * Tests derToPem wraps DER bytes in correct PEM headers.
   *
   * @covers ::derToPem
   */
  public function testDerToPemFormatsCorrectly(): void {
    $method = new \ReflectionMethod($this->service, 'derToPem');
    $method->setAccessible(TRUE);

    $der = random_bytes(64);
    $pem = $method->invoke($this->service, $der, 'CERTIFICATE');

    $this->assertStringStartsWith("-----BEGIN CERTIFICATE-----\n", $pem);
    $this->assertStringEndsWith("-----END CERTIFICATE-----\n", $pem);
    $this->assertEquals($der, base64_decode(
      str_replace(
        ["-----BEGIN CERTIFICATE-----\n", "\n-----END CERTIFICATE-----\n", "\n"],
        ['', '', ''],
        $pem,
      ),
    ));
  }

}
