<?php

namespace Drupal\bebbo_serializer\Encoder;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * JSON encoder for the bebbo_json serialization format.
 *
 * BebboSerializer (the Views style plugin) handles all row-level
 * transformation (media resolution, type casting, etc.). This encoder
 * is only responsible for converting the final PHP array to JSON.
 */
class BebboEncoder implements EncoderInterface {

  const FORMAT = 'bebbo_json';

  /**
   * {@inheritdoc}
   */
  public function encode($data, string $format, array $context = []): string {
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding(string $format): bool {
    return self::FORMAT === $format;
  }

}
