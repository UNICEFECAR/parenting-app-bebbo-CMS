<?php

namespace Drupal\bebbo_serializer\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;

/**
 * Processes body HTML for field_body_rendered and field_embedded_images.
 *
 * Provides two public entry points:
 * - render(): runs check_markup, cleans HTML, converts images to WebP
 *   for populating field_body_rendered.
 * - extractImageUrls(): extracts image URLs from raw body HTML
 *   for populating field_embedded_images.
 *
 * Internal images are converted to WebP via the content_1200xh_ image style
 * with itok security tokens. GIF images are preserved as-is. External image
 * URLs are left unchanged.
 */
class BodyImageProcessor {

  /**
   * Maximum URL length to store in field_embedded_images.
   *
   * URLs exceeding this length are skipped to prevent DB storage issues.
   */
  const MAX_URL_LENGTH = 2048;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Constructs a BodyImageProcessor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function __construct(
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_type_manager,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Renders body HTML through the text format filter pipeline.
   *
   * Runs check_markup() (media_embed converts <drupal-media> to <img>),
   * cleans the output for API consumption, and converts internal images
   * to WebP via image style with itok params.
   *
   * @param string $bodyValue
   *   Raw body HTML (may contain <drupal-media> tags).
   * @param string $bodyFormat
   *   Text format ID (e.g. "full_html").
   * @param string $langcode
   *   Language code for rendering context.
   * @param string $baseUrl
   *   Absolute base URL (e.g. "https://bebbo.app").
   *
   * @return string
   *   Fully rendered and cleaned HTML.
   */
  public function render(string $bodyValue, string $bodyFormat, string $langcode, string $baseUrl): string {
    if (empty($bodyValue)) {
      return '';
    }

    // Run the full text format filter pipeline (including media_embed).
    // This is the same processing that Views' text_default formatter does.
    $build = [
      '#type' => 'processed_text',
      '#text' => $bodyValue,
      '#format' => $bodyFormat,
      '#langcode' => $langcode,
    ];
    $html = (string) $this->renderer->renderInIsolation($build);
    $html = $this->cleanBodyHtml($html, $baseUrl);
    $html = $this->convertImagesToWebp($html, $baseUrl);

    return $html;
  }

  /**
   * Converts internal image URLs in <img> tags to WebP with image style.
   *
   * Processes all <img> tags in the rendered HTML:
   * - Internal images (not GIF): converts to WebP via image style with itok.
   * - GIF images: preserved as-is.
   * - External image URLs: left unchanged.
   *
   * @param string $html
   *   Rendered HTML string.
   * @param string $baseUrl
   *   Absolute base URL of the current request.
   *
   * @return string
   *   HTML with internal image URLs converted to WebP.
   */
  protected function convertImagesToWebp(string $html, string $baseUrl): string {
    if (stripos($html, '<img') === FALSE) {
      return $html;
    }

    $imageStyle = $this->loadImageStyle();
    $publicBasePath = PublicStream::basePath();

    return preg_replace_callback(
      '/<img\b[^>]*>/i',
      function ($matches) use ($baseUrl, $imageStyle, $publicBasePath) {
        $tag = $matches[0];

        // Extract src attribute.
        if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $srcMatch)) {
          return $tag;
        }

        $src = $srcMatch[1];
        $pathOnly = parse_url($src, PHP_URL_PATH) ?? $src;

        // Skip GIFs — preserve as-is.
        if (preg_match('/\.gif$/i', $pathOnly)) {
          return $tag;
        }

        // Skip data URIs.
        if (strpos($src, 'data:') === 0) {
          return $tag;
        }

        // Skip external URLs — preserve as-is.
        if ($this->isExternalUrl($src, $baseUrl)) {
          return $tag;
        }

        // Skip non-image files.
        if (!preg_match('/\.(jpe?g|png|webp)$/i', $pathOnly)) {
          return $tag;
        }

        // Already a .webp URL with itok — nothing to do.
        if (preg_match('/\.webp.*itok=/i', $src)) {
          return $tag;
        }

        $newSrc = $this->processInternalImageUrl($src, $imageStyle, $publicBasePath, $baseUrl);

        if ($newSrc !== $src) {
          $tag = str_replace($srcMatch[0], 'src="' . $newSrc . '"', $tag);
        }

        return $tag;
      },
      $html
    ) ?? $html;
  }

  /**
   * Processes a single internal image URL for WebP conversion.
   *
   * @param string $src
   *   The image src URL.
   * @param \Drupal\image\Entity\ImageStyle|null $imageStyle
   *   The image style to apply, or NULL if unavailable.
   * @param string $publicBasePath
   *   The public files base path (e.g. "sites/default/files").
   * @param string $baseUrl
   *   Absolute base URL (used to strip host for URI resolution).
   *
   * @return string
   *   The processed image URL (WebP with itok if applicable).
   */
  protected function processInternalImageUrl(string $src, ?ImageStyle $imageStyle, string $publicBasePath, string $baseUrl = ''): string {
    // If already styled (has /styles/ in path), just swap extension.
    if (strpos($src, '/styles/') !== FALSE) {
      return $this->swapExtensionToWebp($src);
    }

    // Not styled — apply image style if available.
    if (!$imageStyle) {
      return $src;
    }

    $fileUri = $this->urlToFileUri($src, $publicBasePath, $baseUrl);
    if (!$fileUri) {
      return $src;
    }

    $styledUrl = $imageStyle->buildUrl($fileUri);
    if (!$styledUrl) {
      return $src;
    }

    return $this->swapExtensionToWebp($styledUrl);
  }

  /**
   * Cleans rendered body HTML for API output.
   *
   * Performs deterministic string replacements to match v1 API output:
   * absolutises file paths, strips presentational markup, decodes entities.
   *
   * @param string $html
   *   Rendered HTML string.
   * @param string $baseUrl
   *   Absolute base URL of the current request.
   *
   * @return string
   *   Cleaned HTML string.
   */
  protected function cleanBodyHtml(string $html, string $baseUrl): string {
    // 1. Absolutise file src paths using the current site's public path.
    $publicBasePath = PublicStream::basePath();
    $html = str_replace('src="/' . $publicBasePath . '/', 'src="' . $baseUrl . '/' . $publicBasePath . '/', $html);
    // Handle legacy /sites/default/files/ references on non-default sites.
    if ($publicBasePath !== 'sites/default/files') {
      $html = str_replace('src="/sites/default/files/', 'src="' . $baseUrl . '/' . $publicBasePath . '/', $html);
    }
    // 2. Absolutise oEmbed src paths.
    $html = str_replace('src="/media/oembed', 'src="' . $baseUrl . '/media/oembed', $html);
    // 3. Remove newlines.
    $html = str_replace("\n", '', $html);
    // 4. Strip <span> tags (open and close).
    $html = preg_replace('/<span[^>]+>|<\/span>/i', '', $html) ?? $html;
    // 5. Remove empty <p> and <strong> tags.
    $html = str_replace('<p> </p>', '', $html);
    $html = str_replace('<strong> </strong>', '', $html);
    // 6. Strip inline style attributes.
    $html = preg_replace('/(<[^>]*) style=("[^"]*"|\'[^\']*\')([^>]*>)/i', '$1$3', $html) ?? $html;
    // 7. Remove remote-video dimension attributes.
    $html = str_replace('width="640"', '', $html);
    $html = str_replace('height="480"', '', $html);
    // 8. Remove CKEditor image label div.
    $html = str_replace('<div class="field__label visually-hidden">Image</div>', '', $html);
    // 9. Decode HTML entities.
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $html;
  }

  /**
   * Extract image URLs from drupal-media and img tags in body HTML.
   *
   * Parses <drupal-media> UUIDs, loads media entities, applies
   * content_1200xh_ image style, and converts to WebP. Also extracts
   * URLs from plain <img> tags. Filters out URLs exceeding max length.
   *
   * @param string $html
   *   The raw body HTML content.
   *
   * @return string[]
   *   Array of image URLs.
   */
  public function extractImageUrls(string $html): array {
    if (empty($html)) {
      return [];
    }

    $hasDrupalMedia = strpos($html, '<drupal-media') !== FALSE;
    $hasImgTags = stripos($html, '<img') !== FALSE;

    if (!$hasDrupalMedia && !$hasImgTags) {
      return [];
    }

    $urls = [];

    // Extract from <drupal-media> tags first.
    if ($hasDrupalMedia) {
      $urls = $this->extractFromDrupalMedia($html);
    }

    // Extract from plain <img> tags as well.
    if ($hasImgTags) {
      $imgUrls = $this->extractFromImgTags($html);
      $urls = array_merge($urls, $imgUrls);
    }

    // Filter out URLs that are too long for field storage.
    $urls = array_filter($urls, function (string $url): bool {
      return strlen($url) <= self::MAX_URL_LENGTH;
    });

    return array_values($urls);
  }

  /**
   * Extract image URLs from <drupal-media> tags.
   *
   * @param string $html
   *   The raw body HTML content.
   *
   * @return string[]
   *   Array of styled WebP image URLs.
   */
  protected function extractFromDrupalMedia(string $html): array {
    // Extract UUIDs from <drupal-media> tags in body order.
    preg_match_all(
      '/data-entity-uuid="([a-f0-9\-]+)"/i',
      $html,
      $matches
    );

    // Preserve body order: keep first occurrence of each UUID.
    $orderedUuids = [];
    foreach ($matches[1] ?? [] as $uuid) {
      if (!isset($orderedUuids[$uuid])) {
        $orderedUuids[$uuid] = TRUE;
      }
    }

    if (empty($orderedUuids)) {
      return [];
    }

    // Load media entities by UUID.
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $mediaEntities = $mediaStorage->loadByProperties([
      'uuid' => array_keys($orderedUuids),
    ]);

    if (empty($mediaEntities)) {
      return [];
    }

    // Build UUID -> entity map for ordered lookup.
    $uuidMap = [];
    foreach ($mediaEntities as $media) {
      if ($media instanceof MediaInterface) {
        $uuidMap[$media->uuid()] = $media;
      }
    }

    $imageStyle = $this->loadImageStyle();

    // Build URLs in the same order as they appear in the body.
    $urls = [];
    foreach (array_keys($orderedUuids) as $uuid) {
      $media = $uuidMap[$uuid] ?? NULL;
      if (!$media) {
        continue;
      }

      // Only process image media.
      if ($media->bundle() !== 'image' || !$media->hasField('field_media_image')) {
        continue;
      }

      $file = $media->get('field_media_image')->entity;
      if (!$file instanceof FileInterface) {
        continue;
      }

      $uri = $file->getFileUri();

      // GIF media — preserve original URL without WebP conversion.
      if (preg_match('/\.gif$/i', $uri)) {
        $url = $imageStyle
          ? $imageStyle->buildUrl($uri)
          : $this->fileUrlGenerator->generateAbsoluteString($uri);
        $urls[] = $this->toRelativePath($url);
        continue;
      }

      $styledUrl = $imageStyle
        ? $imageStyle->buildUrl($uri)
        : $this->fileUrlGenerator->generateAbsoluteString($uri);

      // Convert to WebP.
      $url = $this->swapExtensionToWebp($styledUrl);

      // Store as relative path (strip scheme + host).
      $urls[] = $this->toRelativePath($url);
    }

    return $urls;
  }

  /**
   * Extract image URLs from plain <img> tags.
   *
   * Internal images are converted to WebP via image style with itok params.
   * External image URLs are kept as-is. GIF images are preserved.
   *
   * @param string $html
   *   The raw body HTML content.
   *
   * @return string[]
   *   Array of image URLs (relative paths for internal, as-is for external).
   */
  protected function extractFromImgTags(string $html): array {
    preg_match_all(
      '/<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i',
      $html,
      $matches
    );

    if (empty($matches[1])) {
      return [];
    }

    $imageStyle = $this->loadImageStyle();
    $publicBasePath = PublicStream::basePath();

    $urls = [];
    $seen = [];
    foreach ($matches[1] as $src) {
      // Skip duplicates.
      if (isset($seen[$src])) {
        continue;
      }
      $seen[$src] = TRUE;

      // Skip data URIs.
      if (strpos($src, 'data:') === 0) {
        continue;
      }

      $url = $this->toRelativePath($src);
      $pathOnly = parse_url($url, PHP_URL_PATH) ?? $url;

      // Preserve GIFs as-is — no WebP conversion.
      if (preg_match('/\.gif$/i', $pathOnly)) {
        $urls[] = $url;
        continue;
      }

      // External URLs — keep as-is without WebP conversion.
      if ($this->isExternalUrl($src)) {
        $urls[] = $url;
        continue;
      }

      // Internal image — convert to WebP with image style + itok.
      // If already styled (has /styles/ in path), just swap extension.
      if (strpos($url, '/styles/') !== FALSE) {
        $urls[] = $this->swapExtensionToWebp($url);
        continue;
      }

      // Not styled — apply image style for WebP + itok.
      if ($imageStyle) {
        $fileUri = $this->urlToFileUri($url, $publicBasePath);
        if ($fileUri) {
          $styledUrl = $imageStyle->buildUrl($fileUri);
          if ($styledUrl) {
            $url = $this->toRelativePath($styledUrl);
            $urls[] = $this->swapExtensionToWebp($url);
            continue;
          }
        }
      }

      // Fallback: swap extension for internal images without style.
      $urls[] = $this->swapExtensionToWebp($url);
    }

    return $urls;
  }

  /**
   * Loads the content_1200xh_ image style.
   *
   * @return \Drupal\image\Entity\ImageStyle|null
   *   The image style entity, or NULL if not found.
   */
  protected function loadImageStyle(): ?ImageStyle {
    $style = $this->entityTypeManager
      ->getStorage('image_style')
      ->load('content_1200xh_');
    return $style instanceof ImageStyle ? $style : NULL;
  }

  /**
   * Swaps jpg/jpeg/png extension to webp, preserving query string.
   *
   * @param string $url
   *   The image URL.
   *
   * @return string
   *   The URL with .webp extension.
   */
  protected function swapExtensionToWebp(string $url): string {
    return preg_replace(
      '/\.(jpe?g|png)(\?.*)?$/i',
      '.webp$2',
      $url
    ) ?? $url;
  }

  /**
   * Checks if a URL is external (not belonging to this site).
   *
   * @param string $url
   *   The URL to check.
   * @param string $baseUrl
   *   Optional site base URL for absolute URL matching.
   *
   * @return bool
   *   TRUE if the URL is external.
   */
  protected function isExternalUrl(string $url, string $baseUrl = ''): bool {
    // Relative paths starting with /sites/ are internal.
    if (strpos($url, '/sites/') === 0) {
      return FALSE;
    }
    // Relative paths starting with /media/ are internal.
    if (strpos($url, '/media/') === 0) {
      return FALSE;
    }
    // Absolute URLs matching baseUrl are internal.
    if (!empty($baseUrl) && strpos($url, $baseUrl) === 0) {
      return FALSE;
    }
    // Other absolute URLs are external.
    if (preg_match('#^https?://#i', $url)) {
      return TRUE;
    }
    // Other relative paths are internal.
    return FALSE;
  }

  /**
   * Converts a URL path to a Drupal public:// file URI.
   *
   * @param string $url
   *   The image URL (absolute or relative).
   * @param string $publicBasePath
   *   The public files base path (e.g. "sites/default/files").
   * @param string $baseUrl
   *   Optional site base URL to strip from absolute URLs.
   *
   * @return string|null
   *   The file URI (e.g. "public://photo.jpg") or NULL if not resolvable.
   */
  protected function urlToFileUri(string $url, string $publicBasePath, string $baseUrl = ''): ?string {
    $path = $url;
    // Strip base URL if present.
    if (!empty($baseUrl) && strpos($path, $baseUrl) === 0) {
      $path = substr($path, strlen($baseUrl));
    }
    // Strip query string.
    $path = strtok($path, '?') ?: $path;
    // Check if path matches the public files directory.
    $prefix = '/' . $publicBasePath . '/';
    if (strpos($path, $prefix) === 0) {
      return 'public://' . substr($path, strlen($prefix));
    }
    // Fallback: match /sites/default/files/ on non-default sites.
    $defaultPrefix = '/sites/default/files/';
    if ($publicBasePath !== 'sites/default/files' && strpos($path, $defaultPrefix) === 0) {
      return 'public://' . substr($path, strlen($defaultPrefix));
    }
    return NULL;
  }

  /**
   * Converts an absolute URL to a relative path.
   *
   * @param string $url
   *   The URL to convert.
   *
   * @return string
   *   The relative path portion of the URL.
   */
  protected function toRelativePath(string $url): string {
    $parsed = parse_url($url);
    if (!empty($parsed['path'])) {
      $result = $parsed['path'];
      if (!empty($parsed['query'])) {
        $result .= '?' . $parsed['query'];
      }
      return $result;
    }
    return $url;
  }

}
