<?php

namespace Drupal\Tests\feeds\Unit\Feeds\Parser;

use Drupal\Component\Bridge\ZfExtensionManagerSfContainer;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\Feeds\Parser\SyndicationParser;
use Drupal\feeds\Result\RawFetcherResult;
use Drupal\feeds\State;
use Drupal\Tests\feeds\Unit\FeedsUnitTestCase;
use RuntimeException;
use Zend\Feed\Reader\StandaloneExtensionManager;

/**
 * @coversDefaultClass \Drupal\feeds\Feeds\Parser\SyndicationParser
 * @group feeds
 */
class SyndicationParserTest extends FeedsUnitTestCase {

  /**
   * The Feeds parser plugin under test.
   *
   * @var \Drupal\feeds\Feeds\Parser\SyndicationParser
   */
  protected $parser;

  /**
   * The feed type entity.
   *
   * @var \Drupal\feeds\FeedTypeInterface
   */
  protected $feedType;

  /**
   * The feed entity.
   *
   * @var \Drupal\feeds\FeedInterface
   */
  protected $feed;

  /**
   * The state object.
   *
   * @var \Drupal\feeds\StateInterface
   */
  protected $state;

  /**
   * A list of syndication readers.
   *
   * @var array
   */
  protected $readerExtensions = [
    'feed.reader.dublincoreentry' => 'Zend\Feed\Reader\Extension\DublinCore\Entry',
    'feed.reader.dublincorefeed' => 'Zend\Feed\Reader\Extension\DublinCore\Feed',
    'feed.reader.contententry' => 'Zend\Feed\Reader\Extension\Content\Entry',
    'feed.reader.atomentry' => 'Zend\Feed\Reader\Extension\Atom\Entry',
    'feed.reader.atomfeed' => 'Zend\Feed\Reader\Extension\Atom\Feed',
    'feed.reader.slashentry' => 'Zend\Feed\Reader\Extension\Slash\Entry',
    'feed.reader.wellformedwebentry' => 'Zend\Feed\Reader\Extension\WellFormedWeb\Entry',
    'feed.reader.threadentry' => 'Zend\Feed\Reader\Extension\Thread\Entry',
    'feed.reader.podcastentry' => 'Zend\Feed\Reader\Extension\Podcast\Entry',
    'feed.reader.podcastfeed' => 'Zend\Feed\Reader\Extension\Podcast\Feed',
    'feed.reader.georssentry' => 'Drupal\feeds\Zend\Extension\Georss\Entry',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $container = new ContainerBuilder();
    $manager = new ZfExtensionManagerSfContainer('feed.reader.');
    $manager->setContainer($container);
    $manager->setStandalone(StandaloneExtensionManager::class);

    foreach ($this->readerExtensions as $key => $class) {
      $container->set($key, new $class());
    }

    $container->set('feed.bridge.reader', $manager);
    \Drupal::setContainer($container);

    $this->feedType = $this->createMock('Drupal\feeds\FeedTypeInterface');
    $configuration = ['feed_type' => $this->feedType];
    $this->parser = new SyndicationParser($configuration, 'syndication', []);
    $this->parser->setStringTranslation($this->getStringTranslationStub());

    $this->state = new State();

    $this->feed = $this->createMock('Drupal\feeds\FeedInterface');
    $this->feed->expects($this->any())
      ->method('getType')
      ->will($this->returnValue($this->feedType));
  }

  /**
   * Tests parsing a RSS feed that succeeds.
   *
   * @covers ::parse
   */
  public function testParse() {
    $file = $this->resourcesPath() . '/rss/googlenewstz.rss2';
    $fetcher_result = new RawFetcherResult(file_get_contents($file), $this->getMockFileSystem());

    $result = $this->parser->parse($this->feed, $fetcher_result, $this->state);
    $this->assertSame(count($result), 6);
    $this->assertSame($result[0]->get('title'), "First thoughts: Dems' Black Tuesday - msnbc.com");
    $this->assertSame($result[0]->get('author_name'), 'Person Name');
    $this->assertSame($result[0]->get('timestamp'), 1262805987);
    $this->assertSame($result[0]->get('updated'), 1262805987);
    $this->assertSame($result[0]->get('guid'), 'tag:news.google.com,2005:cluster=17593687403189');
    $this->assertSame($result[3]->get('title'), 'NEWSMAKER-New Japan finance minister a fiery battler - Reuters');
  }

  /**
   * Tests parsing an Atom feed.
   *
   * @covers ::parse
   */
  public function testParseAtom() {
    $file = $this->resourcesPath() . '/atom/entries.atom';
    $fetcher_result = new RawFetcherResult(file_get_contents($file), $this->getMockFileSystem());

    $result = $this->parser->parse($this->feed, $fetcher_result, $this->state);
    $this->assertSame(count($result), 3);
    $this->assertSame($result[0]->get('title'), 'Re-spin the patch');
    $this->assertSame($result[0]->get('content'), 'Re-spin the patch for feeds 7.x-2.0-beta2.');
    $this->assertSame($result[0]->get('description'), 'Re-spin the patch for feeds 7.x-2.0-beta2.');
    $this->assertSame($result[0]->get('author_name'), 'natew');
    $this->assertSame($result[0]->get('timestamp'), 1475082480);
    $this->assertSame($result[0]->get('updated'), 1477756140);
    $this->assertSame($result[0]->get('url'), 'node/1281496#comment-11669575');
    $this->assertSame($result[0]->get('guid'), 'comment-11669575');
  }

  /**
   * Tests parsing an invalid feed.
   *
   * @covers ::parse
   */
  public function testInvalidFeed() {
    $fetcher_result = new RawFetcherResult('beep boop', $this->getMockFileSystem());

    $this->expectException(RuntimeException::class);
    $result = $this->parser->parse($this->feed, $fetcher_result, $this->state);
  }

  /**
   * Tests parsing an empty feed.
   *
   * @covers ::parse
   */
  public function testEmptyFeed() {
    $result = new RawFetcherResult('', $this->getMockFileSystem());

    $this->expectException(EmptyFeedException::class);
    $this->parser->parse($this->feed, $result, $this->state);
  }

  /**
   * @covers ::getMappingSources
   */
  public function testGetMappingSources() {
    // Not really much to test here.
    $this->assertSame(count($this->parser->getMappingSources()), 17);
  }

}
