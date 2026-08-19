<?php

declare(strict_types=1);

namespace Drupal\Tests\bebbo_serializer\Kernel;

use Drupal\bebbo_serializer\Cache\RowFragmentCache;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for the per-node row fragment cache.
 *
 * @coversDefaultClass \Drupal\bebbo_serializer\Cache\RowFragmentCache
 * @group bebbo_serializer
 */
class RowFragmentCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'bebbo_serializer'];

  /**
   * Renders each row once, then serves both rows from cache on a repeat call.
   *
   * @covers ::render
   * @covers ::cid
   */
  public function testRendersMissesOnceAndCachesByNid(): void {
    /** @var \Drupal\bebbo_serializer\Cache\RowFragmentCache $svc */
    $svc = $this->container->get('bebbo_serializer.row_fragment_cache');

    $rows = [(object) ['nid' => 10], (object) ['nid' => 11]];
    $calls = [];
    $renderRow = function (int $i, object $row) use (&$calls) {
      $calls[] = $row->nid;
      return 'R' . $row->nid;
    };

    $meta1 = new BubbleableMetadata();
    $out1 = $svc->render('d', 'en', 'https://h', $rows, $renderRow, $meta1);
    $this->assertSame(['R10', 'R11'], $out1);
    $this->assertSame([10, 11], $calls);

    // Second pass: both cached, renderRow not called again.
    $meta2 = new BubbleableMetadata();
    $out2 = $svc->render('d', 'en', 'https://h', $rows, $renderRow, $meta2);
    $this->assertSame(['R10', 'R11'], $out2);
    $this->assertSame([10, 11], $calls, 'no new render calls on cache hit');

    // No per-node tag reaches the response: 460 of them is what made every
    // unrelated save expire it. The listing tags from the views cache plugin
    // scope the response instead.
    $this->assertNotContains('node:10', $meta1->getCacheTags());
    $this->assertNotContains('node:11', $meta1->getCacheTags());
  }

  /**
   * Invalidating a single node's cache tag only re-renders that row.
   *
   * @covers ::render
   * @covers ::anyLanguageTag
   */
  public function testInvalidationByNodeTagReRendersOnlyThatRow(): void {
    /** @var \Drupal\bebbo_serializer\Cache\RowFragmentCache $svc */
    $svc = $this->container->get('bebbo_serializer.row_fragment_cache');

    $rows = [(object) ['nid' => 10], (object) ['nid' => 11]];
    $calls = [];
    $renderRow = function (int $i, object $row) use (&$calls) {
      $calls[] = $row->nid;
      return 'R' . $row->nid;
    };

    $svc->render('d', 'en', 'https://h', $rows, $renderRow, new BubbleableMetadata());

    Cache::invalidateTags([RowFragmentCache::anyLanguageTag(10)]);

    $calls = [];
    $svc->render('d', 'en', 'https://h', $rows, $renderRow, new BubbleableMetadata());
    $this->assertSame([10], $calls, 'only node 10 re-rendered');
  }

  /**
   * One language's invalidation leaves the same node's other languages warm.
   *
   * @covers ::render
   * @covers ::languageTag
   */
  public function testLanguageTagInvalidatesOnlyThatLanguage(): void {
    /** @var \Drupal\bebbo_serializer\Cache\RowFragmentCache $svc */
    $svc = $this->container->get('bebbo_serializer.row_fragment_cache');

    $rows = [(object) ['nid' => 10]];
    $calls = [];
    $renderRow = function (int $i, object $row) use (&$calls) {
      $calls[] = $row->nid;
      return 'R' . $row->nid;
    };

    foreach (['en', 'ro-ro', 'bn'] as $langcode) {
      $svc->render('d', $langcode, 'https://h', $rows, $renderRow, new BubbleableMetadata());
    }
    $this->assertCount(3, $calls, 'one render per language');

    // A translator saves ro-ro only.
    Cache::invalidateTags([RowFragmentCache::languageTag(10, 'ro-ro')]);

    $calls = [];
    foreach (['en', 'ro-ro', 'bn'] as $langcode) {
      $svc->render('d', $langcode, 'https://h', $rows, $renderRow, new BubbleableMetadata());
    }
    $this->assertSame([10], $calls, 'only ro-ro re-rendered; en and bn stayed cached');
  }

  /**
   * An untranslatable change expires the node in every language at once.
   *
   * @covers ::render
   * @covers ::anyLanguageTag
   */
  public function testAnyLanguageTagInvalidatesEveryLanguage(): void {
    /** @var \Drupal\bebbo_serializer\Cache\RowFragmentCache $svc */
    $svc = $this->container->get('bebbo_serializer.row_fragment_cache');

    $rows = [(object) ['nid' => 10]];
    $calls = [];
    $renderRow = function (int $i, object $row) use (&$calls) {
      $calls[] = $row->nid;
      return 'R' . $row->nid;
    };

    foreach (['en', 'ro-ro', 'bn'] as $langcode) {
      $svc->render('d', $langcode, 'https://h', $rows, $renderRow, new BubbleableMetadata());
    }

    Cache::invalidateTags([RowFragmentCache::anyLanguageTag(10)]);

    $calls = [];
    foreach (['en', 'ro-ro', 'bn'] as $langcode) {
      $svc->render('d', $langcode, 'https://h', $rows, $renderRow, new BubbleableMetadata());
    }
    $this->assertCount(3, $calls, 'every language re-rendered');
  }

  /**
   * Core's node:NID no longer expires the fragment, only the response.
   *
   * @covers ::render
   */
  public function testCoreNodeTagDoesNotExpireFragments(): void {
    /** @var \Drupal\bebbo_serializer\Cache\RowFragmentCache $svc */
    $svc = $this->container->get('bebbo_serializer.row_fragment_cache');

    $rows = [(object) ['nid' => 10]];
    $calls = [];
    $renderRow = function (int $i, object $row) use (&$calls) {
      $calls[] = $row->nid;
      return 'R' . $row->nid;
    };

    $svc->render('d', 'en', 'https://h', $rows, $renderRow, new BubbleableMetadata());

    Cache::invalidateTags(['node:10']);

    $calls = [];
    $svc->render('d', 'en', 'https://h', $rows, $renderRow, new BubbleableMetadata());
    $this->assertSame([], $calls, 'fragment survives core node tag; hook drives expiry now');
  }

  /**
   * A render aborted partway through still persists the rows already built.
   *
   * @covers ::render
   */
  public function testPartialRenderPersistsCompletedRows(): void {
    /** @var \Drupal\bebbo_serializer\Cache\RowFragmentCache $svc */
    $svc = $this->container->get('bebbo_serializer.row_fragment_cache');

    $rows = [(object) ['nid' => 10], (object) ['nid' => 11]];

    // First pass aborts while rendering the second row (mimics a gateway
    // timeout mid-render).
    $failing = function (int $i, object $row) {
      if ($row->nid === 11) {
        throw new \RuntimeException('aborted');
      }
      return 'R' . $row->nid;
    };
    try {
      $svc->render('d', 'en', 'https://h', $rows, $failing, new BubbleableMetadata());
      $this->fail('render should have propagated the abort');
    }
    catch (\RuntimeException) {
      // Expected.
    }

    // Second pass completes: node 10 is already cached, only node 11 renders.
    $calls = [];
    $renderRow = function (int $i, object $row) use (&$calls) {
      $calls[] = $row->nid;
      return 'R' . $row->nid;
    };
    $out = $svc->render('d', 'en', 'https://h', $rows, $renderRow, new BubbleableMetadata());
    $this->assertSame(['R10', 'R11'], $out);
    $this->assertSame([11], $calls, 'node 10 survived the aborted pass; only node 11 re-rendered');
  }

}
