<?php

namespace Drupal\file_sanitizer\Service;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service for sanitizing file names to remove unsafe characters.
 */
class FilenameSanitizer {

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected TransliterationInterface $transliterator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Cached allowed extensions.
   *
   * @var array|null
   */
  protected ?array $allowedExtensions = NULL;

  public function __construct(
    TransliterationInterface $transliterator,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->transliterator = $transliterator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Check if filename needs sanitization.
   */
  public function needsSanitization(string $filename): bool {
    return $filename !== $this->sanitize($filename);
  }

  /**
   * Fully sanitize a filename.
   */
  public function sanitize(string $filename): string {
    // 1. Decode URL encoding (%20, %C4%8D, etc.)
    $filename = urldecode($filename);

    // 2. Split extension safely
    $info = pathinfo($filename);
    $name = $info['filename'] ?? '';
    $extension = $info['extension'] ?? '';

    // 3. Lowercase name and extension early
    $name = mb_strtolower($name);
    $extension = mb_strtolower($extension);

    // 4. Transliterate UTF-8 → ASCII
    $name = $this->transliterator->transliterate($name, 'en');

    // 5. Replace dots inside filename (only one dot allowed)
    $name = str_replace('.', '-', $name);

    // 6. Replace ANY non-safe character with dash
    $name = preg_replace('/[^a-z0-9_-]+/', '-', $name);

    // 7. Collapse multiple dashes and trim
    $name = preg_replace('/-+/', '-', $name);
    $name = trim($name, '-');

    // 8. Validate extension
    $extension = preg_replace('/[^a-z0-9]+/', '', $extension);

    if (!$this->isExtensionAllowed($extension)) {
      // Neutralize dangerous extensions instead of failing upload.
      $extension = 'bin';
    }

    return $name . '.' . $extension;
  }

  /**
   * Check extension safety.
   */
  protected function isExtensionAllowed(string $extension): bool {
    return in_array($extension, $this->getAllowedExtensions(), TRUE);
  }

  /**
   * Get allowed extensions from Drupal field configuration.
   *
   * @return array
   *   Array of allowed file extensions.
   */
  protected function getAllowedExtensions(): array {
    // Return cached value if already loaded.
    if ($this->allowedExtensions !== NULL) {
      return $this->allowedExtensions;
    }

    $extensions = [];

    // Load field config for media.image.field_media_image.
    $field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('media.image.field_media_image');

    if ($field_config) {
      $settings = $field_config->getSettings();
      if (!empty($settings['file_extensions'])) {
        // Parse space-separated extensions from field config.
        $extensions = array_filter(
          array_map('trim', explode(' ', $settings['file_extensions']))
        );
      }
    }

    // Cache the result.
    $this->allowedExtensions = $extensions;

    return $this->allowedExtensions;
  }

}
