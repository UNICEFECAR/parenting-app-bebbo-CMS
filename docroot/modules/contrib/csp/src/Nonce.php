<?php

namespace Drupal\csp;

use Drupal\Component\Utility\Crypt;

/**
 * Service for retrieving a per-request nonce value.
 */
class Nonce {

  /**
   * The request nonce.
   *
   * @var null|string
   */
  private ?string $value;

  /**
   * Generate a new nonce value.
   *
   * @return string
   *   A base64-encoded string.
   */
  protected static function generateValue(): string {
    // Nonce should be at least 128 bits.
    // @see https://www.w3.org/TR/CSP/#security-nonces
    return Crypt::randomBytesBase64(16);
  }

  /**
   * Return if a nonce value has been generated.
   *
   * @return bool
   *   If a nonce value has been generated.
   */
  public function hasValue(): bool {
    return !empty($this->value);
  }

  /**
   * Get the nonce value.
   *
   * @return string
   *   A base64-encoded string.
   */
  public function getValue(): string {
    return $this->value ??= self::generateValue();
  }

  /**
   * Get the nonce value formatted for inclusion in a directive.
   *
   * @return string
   *   The nonce in the format "'nonce-{value}'"
   */
  public function getSource(): string {
    return "'nonce-{$this->getValue()}'";
  }

}
