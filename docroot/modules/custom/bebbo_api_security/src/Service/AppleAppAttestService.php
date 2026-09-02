<?php

declare(strict_types=1);

namespace Drupal\bebbo_api_security\Service;

use CBOR\CBORObject;
use CBOR\Decoder;
use CBOR\Normalizable;
use CBOR\OtherObject\OtherObjectManager;
use CBOR\StringStream;
use CBOR\Tag\TagManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;

/**
 * Apple App Attest verification for iOS store builds.
 */
class AppleAppAttestService {

  private const AAGUID_PRODUCTION = "appattest\x00\x00\x00\x00\x00\x00\x00";

  private const AAGUID_DEVELOPMENT = "appattestdevelop";

  /**
   * Constructs an AppleAppAttestService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected readonly Connection $database,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Verify an Apple App Attest attestation object (one-time key registration).
   *
   * @param string $attestation_b64
   *   Base64-encoded CBOR attestation object.
   * @param string $key_id
   *   App Attest key identifier.
   * @param string $client_data_hash
   *   Hex-encoded SHA-256 hash of client data.
   * @param string $device_id
   *   Device identifier.
   *
   * @return string
   *   PEM-encoded public key extracted from attestation.
   *
   * @throws \RuntimeException
   *   On verification failure.
   */
  public function verifyAttestation(string $attestation_b64, string $key_id, string $client_data_hash, string $device_id): string {
    $attestation_raw = base64_decode($attestation_b64, TRUE);
    if ($attestation_raw === FALSE) {
      throw new \RuntimeException('Invalid base64 attestation object.');
    }

    $decoder = Decoder::create(TagManager::create(), OtherObjectManager::create());
    try {
      $cbor = $decoder->decode(StringStream::create($attestation_raw));
      $map = $this->normalizeCborObject($cbor);
    }
    catch (\Throwable $e) {
      throw new \RuntimeException('CBOR decode failed: ' . $e->getMessage());
    }

    $fmt = $map['fmt'] ?? '';
    if ($fmt !== 'apple-appattest') {
      throw new \RuntimeException("Unexpected attestation format: {$fmt}");
    }

    $auth_data = $map['authData'] ?? '';
    if (!is_string($auth_data) || strlen($auth_data) < 55) {
      throw new \RuntimeException('authData too short or invalid.');
    }

    $parsed = $this->parseAuthData($auth_data);

    // Verify RP ID hash.
    $config = $this->configFactory->get('bebbo_api_security.settings');
    $team_id = $config->get('apple_team_id');
    $bundle_id = $config->get('apple_bundle_id');
    $expected_rp_hash = hash('sha256', "{$team_id}.{$bundle_id}", TRUE);

    if (!hash_equals($expected_rp_hash, $parsed['rp_id_hash'])) {
      throw new \RuntimeException('RP ID hash mismatch. Check Apple Team ID and Bundle ID.');
    }

    // Verify AAGUID.
    $production = (bool) $config->get('apple_production_mode');
    $expected_aaguid = $production ? self::AAGUID_PRODUCTION : self::AAGUID_DEVELOPMENT;
    if ($parsed['aaguid'] !== $expected_aaguid) {
      throw new \RuntimeException('AAGUID mismatch. Check apple_production_mode setting.');
    }

    // Verify credential ID matches key_id.
    if ($parsed['credential_id'] !== base64_decode($key_id, TRUE)) {
      throw new \RuntimeException('Credential ID does not match key_id.');
    }

    // Verify counter is 0 for attestation.
    if ($parsed['counter'] !== 0) {
      throw new \RuntimeException('Counter must be 0 for attestation.');
    }

    // Extract and verify certificate chain.
    $att_stmt = $map['attStmt'] ?? [];
    $x5c = $att_stmt['x5c'] ?? [];
    if (count($x5c) < 2) {
      throw new \RuntimeException('Attestation missing x5c certificate chain.');
    }

    $leaf_der = $x5c[0];
    $intermediate_der = $x5c[1];

    $debug = (bool) $config->get('debug_logging');
    if ($debug) {
      $this->logger->debug('App Attest cert chain: x5c has @count certs, leaf DER @leaf_len bytes, intermediate DER @inter_len bytes.', [
        '@count' => count($x5c),
        '@leaf_len' => strlen($leaf_der),
        '@inter_len' => strlen($intermediate_der),
      ]);
    }

    $this->verifyCertificateChain($leaf_der, $intermediate_der, $debug);

    // Verify nonce in leaf certificate.
    $nonce = hash('sha256', $auth_data . hex2bin($client_data_hash), TRUE);
    $this->verifyAttestationNonce($leaf_der, $nonce);

    // Extract public key from COSE key in authData.
    $public_key_pem = $this->coseKeyToPem($parsed['cose_key_bytes']);

    return $public_key_pem;
  }

  /**
   * Verify an App Attest assertion (ongoing request verification).
   *
   * @param string $assertion_b64
   *   Base64-encoded CBOR assertion.
   * @param string $client_data_hash
   *   Hex-encoded SHA-256 of client data.
   * @param string $key_id
   *   App Attest key identifier.
   * @param string $stored_public_key
   *   PEM public key from attestation.
   * @param int $stored_counter
   *   Last known counter value.
   *
   * @return int
   *   New counter value (must be stored by caller).
   *
   * @throws \RuntimeException
   *   On verification failure.
   */
  public function verifyAssertion(string $assertion_b64, string $client_data_hash, string $key_id, string $stored_public_key, int $stored_counter): int {
    $assertion_raw = base64_decode($assertion_b64, TRUE);
    if ($assertion_raw === FALSE) {
      throw new \RuntimeException('Invalid base64 assertion.');
    }

    $decoder = Decoder::create(TagManager::create(), OtherObjectManager::create());
    try {
      $cbor = $decoder->decode(StringStream::create($assertion_raw));
      $map = $this->normalizeCborObject($cbor);
    }
    catch (\Throwable $e) {
      throw new \RuntimeException('CBOR decode failed: ' . $e->getMessage());
    }

    $signature = $map['signature'] ?? '';
    $authenticator_data = $map['authenticatorData'] ?? '';

    if (!is_string($authenticator_data) || strlen($authenticator_data) < 37) {
      throw new \RuntimeException('authenticatorData too short.');
    }

    // Parse authenticator data.
    $rp_id_hash = substr($authenticator_data, 0, 32);
    $counter = unpack('N', substr($authenticator_data, 33, 4))[1];

    // Verify RP ID hash.
    $config = $this->configFactory->get('bebbo_api_security.settings');
    $team_id = $config->get('apple_team_id');
    $bundle_id = $config->get('apple_bundle_id');
    $expected_rp_hash = hash('sha256', "{$team_id}.{$bundle_id}", TRUE);

    if (!hash_equals($expected_rp_hash, $rp_id_hash)) {
      throw new \RuntimeException('RP ID hash mismatch in assertion.');
    }

    // Verify counter is increasing.
    if ($counter <= $stored_counter) {
      throw new \RuntimeException("Counter not increasing: got {$counter}, expected > {$stored_counter}");
    }

    // Verify signature.
    $nonce = hash('sha256', $authenticator_data . hex2bin($client_data_hash), TRUE);
    $result = openssl_verify($nonce, $signature, $stored_public_key, OPENSSL_ALGO_SHA256);

    if ($result !== 1) {
      throw new \RuntimeException('Assertion signature verification failed.');
    }

    return $counter;
  }

  /**
   * Normalize a decoded CBOR object to a PHP array.
   *
   * @param \CBOR\CBORObject $object
   *   The decoded CBOR object.
   *
   * @return mixed
   *   The normalized PHP value (typically an array for maps).
   *
   * @throws \RuntimeException
   *   If the object does not implement Normalizable.
   */
  private function normalizeCborObject(CBORObject $object): mixed {
    if (!$object instanceof Normalizable) {
      throw new \RuntimeException('CBOR object is not normalizable.');
    }
    return $object->normalize();
  }

  /**
   * Parse binary authData from attestation object.
   *
   * @param string $auth_data
   *   Raw binary authData.
   *
   * @return array
   *   Keys: rp_id_hash, flags, counter, aaguid, credential_id,
   *   cose_key_bytes.
   */
  private function parseAuthData(string $auth_data): array {
    $rp_id_hash = substr($auth_data, 0, 32);
    $flags = ord($auth_data[32]);
    $counter = unpack('N', substr($auth_data, 33, 4))[1];
    $aaguid = substr($auth_data, 37, 16);
    $cred_id_len = unpack('n', substr($auth_data, 53, 2))[1];
    $credential_id = substr($auth_data, 55, $cred_id_len);
    $cose_key_bytes = substr($auth_data, 55 + $cred_id_len);

    return [
      'rp_id_hash' => $rp_id_hash,
      'flags' => $flags,
      'counter' => $counter,
      'aaguid' => $aaguid,
      'credential_id' => $credential_id,
      'cose_key_bytes' => $cose_key_bytes,
    ];
  }

  /**
   * Verify the certificate chain: leaf -> intermediate -> Apple Root CA.
   *
   * @param string $leaf_der
   *   DER-encoded leaf certificate.
   * @param string $intermediate_der
   *   DER-encoded intermediate certificate.
   * @param bool $debug
   *   Whether to log detailed diagnostic information.
   *
   * @throws \RuntimeException
   *   If chain verification fails.
   */
  private function verifyCertificateChain(string $leaf_der, string $intermediate_der, bool $debug): void {
    $leaf_pem = $this->derToPem($leaf_der, 'CERTIFICATE');
    $intermediate_pem = $this->derToPem($intermediate_der, 'CERTIFICATE');

    $root_ca_pem = $this->configFactory->get('bebbo_api_security.settings')
      ->get('apple_root_ca_pem');
    if (empty($root_ca_pem)) {
      throw new \RuntimeException('Apple Root CA PEM not configured. Set it in API Security settings.');
    }

    $leaf_cert = openssl_x509_read($leaf_pem);
    if (!$leaf_cert) {
      $this->logger->error('App Attest: failed to parse leaf certificate. OpenSSL error: @err', [
        '@err' => openssl_error_string() ?: 'none',
      ]);
      throw new \RuntimeException('Failed to parse leaf certificate.');
    }

    $intermediate_cert = openssl_x509_read($intermediate_pem);
    if (!$intermediate_cert) {
      $this->logger->error('App Attest: failed to parse intermediate certificate. OpenSSL error: @err', [
        '@err' => openssl_error_string() ?: 'none',
      ]);
      throw new \RuntimeException('Failed to parse intermediate certificate.');
    }

    $root_cert = openssl_x509_read($root_ca_pem);
    if (!$root_cert) {
      $this->logger->error('App Attest: failed to parse configured root CA PEM. OpenSSL error: @err', [
        '@err' => openssl_error_string() ?: 'none',
      ]);
      throw new \RuntimeException('Failed to parse Apple Root CA certificate from config.');
    }

    if ($debug) {
      $leaf_parsed = openssl_x509_parse($leaf_cert);
      $inter_parsed = openssl_x509_parse($intermediate_cert);
      $root_parsed = openssl_x509_parse($root_cert);

      $this->logger->debug('App Attest cert subjects — leaf: @leaf_subj (issuer: @leaf_iss), intermediate: @inter_subj (issuer: @inter_iss), root: @root_subj (issuer: @root_iss).', [
        '@leaf_subj' => $leaf_parsed['subject']['CN'] ?? 'unknown',
        '@leaf_iss' => $leaf_parsed['issuer']['CN'] ?? 'unknown',
        '@inter_subj' => $inter_parsed['subject']['CN'] ?? 'unknown',
        '@inter_iss' => $inter_parsed['issuer']['CN'] ?? 'unknown',
        '@root_subj' => $root_parsed['subject']['CN'] ?? 'unknown',
        '@root_iss' => $root_parsed['issuer']['CN'] ?? 'unknown',
      ]);

      $this->logger->debug('App Attest cert validity — leaf: @leaf_from to @leaf_to, intermediate: @inter_from to @inter_to.', [
        '@leaf_from' => date('Y-m-d H:i:s', $leaf_parsed['validFrom_time_t'] ?? 0),
        '@leaf_to' => date('Y-m-d H:i:s', $leaf_parsed['validTo_time_t'] ?? 0),
        '@inter_from' => date('Y-m-d H:i:s', $inter_parsed['validFrom_time_t'] ?? 0),
        '@inter_to' => date('Y-m-d H:i:s', $inter_parsed['validTo_time_t'] ?? 0),
      ]);

      $this->logger->debug('App Attest cert algorithms — leaf: @leaf_algo, intermediate: @inter_algo, root: @root_algo.', [
        '@leaf_algo' => $leaf_parsed['signatureTypeSN'] ?? 'unknown',
        '@inter_algo' => $inter_parsed['signatureTypeSN'] ?? 'unknown',
        '@root_algo' => $root_parsed['signatureTypeSN'] ?? 'unknown',
      ]);
    }

    // Verify leaf is signed by intermediate.
    $inter_key = openssl_pkey_get_public($intermediate_cert);
    if (!$inter_key) {
      $this->logger->error('App Attest: failed to extract public key from intermediate cert. OpenSSL error: @err', [
        '@err' => openssl_error_string() ?: 'none',
      ]);
      throw new \RuntimeException('Failed to extract public key from intermediate certificate.');
    }

    if ($debug) {
      $inter_key_details = openssl_pkey_get_details($inter_key);
      $this->logger->debug('App Attest intermediate key — type: @type, bits: @bits.', [
        '@type' => $inter_key_details['type'] ?? 'unknown',
        '@bits' => $inter_key_details['bits'] ?? 'unknown',
      ]);
    }

    $result = openssl_x509_verify($leaf_cert, $inter_key);
    if ($result !== 1) {
      $this->logger->error('App Attest: leaf not signed by intermediate. openssl_x509_verify returned @result (1=ok, 0=mismatch, -1=error). OpenSSL error: @err', [
        '@result' => $result,
        '@err' => openssl_error_string() ?: 'none',
      ]);
      throw new \RuntimeException('Leaf certificate not signed by intermediate.');
    }

    if ($debug) {
      $this->logger->debug('App Attest: leaf → intermediate signature verified OK.');
    }

    // Verify intermediate is signed by Apple Root CA.
    $root_key = openssl_pkey_get_public($root_cert);
    if (!$root_key) {
      $this->logger->error('App Attest: failed to extract public key from root CA. OpenSSL error: @err', [
        '@err' => openssl_error_string() ?: 'none',
      ]);
      throw new \RuntimeException('Failed to extract public key from Apple Root CA.');
    }

    if ($debug) {
      $root_key_details = openssl_pkey_get_details($root_key);
      $this->logger->debug('App Attest root CA key — type: @type, bits: @bits.', [
        '@type' => $root_key_details['type'] ?? 'unknown',
        '@bits' => $root_key_details['bits'] ?? 'unknown',
      ]);
    }

    $result = openssl_x509_verify($intermediate_cert, $root_key);
    if ($result !== 1) {
      $inter_parsed = $inter_parsed ?? openssl_x509_parse($intermediate_cert);
      $this->logger->error('App Attest: intermediate not signed by root CA. openssl_x509_verify returned @result (1=ok, 0=mismatch, -1=error). OpenSSL error: @err. Intermediate issuer CN: @issuer.', [
        '@result' => $result,
        '@err' => openssl_error_string() ?: 'none',
        '@issuer' => $inter_parsed['issuer']['CN'] ?? 'unknown',
      ]);
      throw new \RuntimeException('Intermediate certificate not signed by Apple Root CA.');
    }

    if ($debug) {
      $this->logger->debug('App Attest: intermediate → root CA signature verified OK.');
    }
  }

  /**
   * Extract and verify the nonce from the leaf certificate extension.
   *
   * @param string $leaf_der
   *   DER-encoded leaf certificate.
   * @param string $expected_nonce
   *   Binary expected nonce (32 bytes).
   *
   * @throws \RuntimeException
   *   If nonce doesn't match.
   */
  private function verifyAttestationNonce(string $leaf_der, string $expected_nonce): void {
    $leaf_pem = $this->derToPem($leaf_der, 'CERTIFICATE');
    $parsed = openssl_x509_parse($leaf_pem);

    // OID 1.2.840.113635.100.8.2 contains the nonce.
    $extensions = $parsed['extensions'] ?? [];
    $nonce_ext = $extensions['1.2.840.113635.100.8.2'] ?? NULL;

    if ($nonce_ext === NULL) {
      throw new \RuntimeException('Attestation certificate missing nonce extension (OID 1.2.840.113635.100.8.2).');
    }

    // The extension value is ASN.1 wrapped. The nonce is the last 32 bytes.
    $nonce_bytes = substr($nonce_ext, -32);

    if (!hash_equals($expected_nonce, $nonce_bytes)) {
      throw new \RuntimeException('Attestation nonce mismatch.');
    }
  }

  /**
   * Convert a COSE-encoded EC P-256 public key to PEM format.
   *
   * COSE key map: 1=kty(EC2=2), 3=alg, -1=crv(P-256=1), -2=x, -3=y.
   *
   * @param string $cose_bytes
   *   Raw CBOR-encoded COSE key bytes.
   *
   * @return string
   *   PEM-encoded EC P-256 public key.
   *
   * @throws \RuntimeException
   *   If key type unsupported or coordinates invalid.
   */
  private function coseKeyToPem(string $cose_bytes): string {
    $decoder = Decoder::create(TagManager::create(), OtherObjectManager::create());
    try {
      $cose = $decoder->decode(StringStream::create($cose_bytes));
      $map = $this->normalizeCborObject($cose);
    }
    catch (\Throwable $e) {
      throw new \RuntimeException('Failed to decode COSE key: ' . $e->getMessage());
    }

    $kty = (int) ($map[1] ?? 0);
    $crv = (int) ($map[-1] ?? 0);
    $x = $map[-2] ?? NULL;
    $y = $map[-3] ?? NULL;

    if ($kty !== 2 || $crv !== 1) {
      throw new \RuntimeException('Unsupported COSE key type. Expected EC2 P-256.');
    }

    if (!is_string($x) || strlen($x) !== 32 || !is_string($y) || strlen($y) !== 32) {
      throw new \RuntimeException('Invalid COSE key coordinates.');
    }

    // Build uncompressed EC point: 0x04 || x || y.
    $point = "\x04" . $x . $y;

    // Wrap in DER SubjectPublicKeyInfo for P-256.
    $der = "\x30\x59"
      . "\x30\x13"
      . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
      . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
      . "\x03\x42\x00"
      . $point;

    return "-----BEGIN PUBLIC KEY-----\n"
      . chunk_split(base64_encode($der), 64, "\n")
      . "-----END PUBLIC KEY-----\n";
  }

  /**
   * Convert DER bytes to PEM format.
   *
   * @param string $der
   *   DER-encoded bytes.
   * @param string $type
   *   PEM type label (e.g., 'CERTIFICATE').
   *
   * @return string
   *   PEM-encoded string.
   */
  private function derToPem(string $der, string $type): string {
    return "-----BEGIN {$type}-----\n"
      . chunk_split(base64_encode($der), 64, "\n")
      . "-----END {$type}-----\n";
  }

}
