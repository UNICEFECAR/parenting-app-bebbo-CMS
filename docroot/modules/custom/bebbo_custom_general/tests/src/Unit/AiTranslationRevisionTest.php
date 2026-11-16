<?php

namespace Drupal\Tests\bebbo_custom_general\Unit;

use Drupal\ai_translate\EntityTranslationOrchestrator;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the revision metadata patch rebased onto ai_translate 1.4.4.
 *
 * @group bebbo_custom_general
 */
class AiTranslationRevisionTest extends UnitTestCase {

  /**
   * AI translations record the current editor and request time.
   */
  public function testRevisionMetadata(): void {
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1794787200);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(11);
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getLanguage')->willReturn(new Language(['id' => 'ar', 'name' => 'Arabic']));
    $service = new EntityTranslationOrchestrator(
      $this->createMock(TextExtractorInterface::class),
      $this->createMock(TextTranslatorInterface::class),
      $languages,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ContentTranslationManagerInterface::class),
      $time,
      $account,
    );
    $service->setStringTranslation($this->getStringTranslationStub());
    $translation = $this->createMock(NodeInterface::class);
    $translation->expects($this->once())->method('setRevisionCreationTime')->with(1794787200);
    $translation->expects($this->once())->method('setRevisionUserId')->with(11);
    $translation->expects($this->once())->method('setRevisionLogMessage')->with('AI translated to Arabic.');
    $translation->expects($this->once())->method('setChangedTime')->with(1794787200);
    (new \ReflectionMethod($service, 'stampTranslationRevision'))->invoke($service, $translation, 'ar');
  }

}
