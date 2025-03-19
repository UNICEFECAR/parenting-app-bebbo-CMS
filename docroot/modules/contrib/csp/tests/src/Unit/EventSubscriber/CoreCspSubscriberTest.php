<?php

namespace Drupal\Tests\csp\Unit\EventSubscriber;

use Drupal\Core\Asset\LibraryDependencyResolverInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\csp\EventSubscriber\CoreCspSubscriber;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\csp\EventSubscriber\CoreCspSubscriber
 * @group csp
 */
class CoreCspSubscriberTest extends UnitTestCase {

  /**
   * The Library Dependency Resolver service.
   *
   * @var \Drupal\Core\Asset\LibraryDependencyResolverInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private $libraryDependencyResolver;

  /**
   * The Module Handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private $moduleHandler;

  /**
   * The event subscriber for core modules.
   *
   * @var \Drupal\csp\EventSubscriber\CoreCspSubscriber
   */
  private $coreCspSubscriber;

  /**
   * The response object.
   *
   * @var \Drupal\Core\Render\HtmlResponse|\PHPUnit\Framework\MockObject\MockObject
   */
  private $response;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->libraryDependencyResolver = $this->createMock(LibraryDependencyResolverInterface::class);
    $this->libraryDependencyResolver->method('getLibrariesWithDependencies')
      ->willReturnArgument(0);

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->response = $this->createMock(HtmlResponse::class);

    $this->coreCspSubscriber = new CoreCspSubscriber($this->libraryDependencyResolver, $this->moduleHandler);
  }

  /**
   * Check that the subscriber listens to the Policy Alter event.
   *
   * @covers ::getSubscribedEvents
   */
  public function testSubscribedEvents() {
    $this->assertArrayHasKey(CspEvents::POLICY_ALTER, CoreCspSubscriber::getSubscribedEvents());
  }

  /**
   * Test a response with no attachments.
   *
   * Classes like AjaxResponse may return an empty array, so an error shouldn't
   * be thrown if the 'library' element does not exist.
   *
   * @covers ::onCspPolicyAlter
   */
  public function testNoAttachments() {
    $policy = new Csp();

    $this->response->method('getAttachments')
      ->willReturn([]);

    $alterEvent = new PolicyAlterEvent($policy, $this->response);

    $this->coreCspSubscriber->onCspPolicyAlter($alterEvent);

    $this->addToAssertionCount(1);
  }

  /**
   * Policies are altered for the Drupal AJAX library.
   *
   * @covers ::onCspPolicyAlter
   */
  public function testAjaxLibrary() {
    $policy = new Csp();
    $policy->setDirective('default-src', [Csp::POLICY_ANY]);
    $policy->setDirective('script-src', [Csp::POLICY_SELF]);
    $policy->setDirective('style-src', [Csp::POLICY_SELF]);

    $this->response->method('getAttachments')
      ->willReturn([
        'library' => [
          'core/drupal.ajax',
        ],
      ]);

    $alterEvent = new PolicyAlterEvent($policy, $this->response);

    $this->coreCspSubscriber->onCspPolicyAlter($alterEvent);

    $this->assertEquals(
      [Csp::POLICY_SELF],
      $alterEvent->getPolicy()->getDirective('script-src')
    );
    $this->assertFalse($alterEvent->getPolicy()->hasDirective('script-src-attr'));
    $this->assertFalse($alterEvent->getPolicy()->hasDirective('script-src-elem'));

    $this->assertEquals(
      [Csp::POLICY_SELF],
      $alterEvent->getPolicy()->getDirective('style-src')
    );
    $this->assertFalse(
      $alterEvent->getPolicy()->hasDirective('style-src-attr')
    );
    $this->assertFalse(
      $alterEvent->getPolicy()->hasDirective('style-src-elem')
    );
  }

  /**
   * CKEditor shouldn't alter the policy if no directives are enabled.
   *
   * @covers ::onCspPolicyAlter
   */
  public function testCkeditorScriptNoDirectives() {
    $policy = new Csp();

    $this->response->method('getAttachments')
      ->willReturn([
        'library' => [
          'core/ckeditor',
        ],
      ]);

    $alterEvent = new PolicyAlterEvent($policy, $this->response);

    $this->coreCspSubscriber->onCspPolicyAlter($alterEvent);

    $this->assertFalse($alterEvent->getPolicy()->hasDirective('script-src'));
    $this->assertFalse($alterEvent->getPolicy()->hasDirective('script-src-attr'));
    $this->assertFalse($alterEvent->getPolicy()->hasDirective('script-src-elem'));
  }

  /**
   * Test that including ckeditor5 modifies enabled style directives.
   *
   * @covers ::onCspPolicyAlter
   */
  public function testCkeditorStyle() {
    $policy = new Csp();
    $policy->setDirective('default-src', [Csp::POLICY_ANY]);
    $policy->setDirective('style-src', [Csp::POLICY_SELF]);
    $policy->setDirective('style-src-attr', [Csp::POLICY_SELF]);
    $policy->setDirective('style-src-elem', [Csp::POLICY_SELF]);

    $this->response->method('getAttachments')
      ->willReturn([
        'library' => [
          'core/ckeditor5',
        ],
      ]);

    $alterEvent = new PolicyAlterEvent($policy, $this->response);

    $this->coreCspSubscriber->onCspPolicyAlter($alterEvent);

    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src')
    );
    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src-attr')
    );
    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src-elem')
    );
  }

  /**
   * Test ckeditor5 fallback if style-src enabled.
   *
   * @covers ::onCspPolicyAlter
   */
  public function testCkeditorStyleFallback() {
    $policy = new Csp();
    $policy->setDirective('default-src', [Csp::POLICY_ANY]);
    $policy->setDirective('style-src', [Csp::POLICY_SELF]);

    $this->response->method('getAttachments')
      ->willReturn([
        'library' => [
          'core/ckeditor5',
        ],
      ]);

    $alterEvent = new PolicyAlterEvent($policy, $this->response);

    $this->coreCspSubscriber->onCspPolicyAlter($alterEvent);

    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src')
    );
    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src-attr')
    );
    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src-elem')
    );
  }

  /**
   * Test ckeditor5 fallback if only default-src is enabled.
   *
   * @covers ::onCspPolicyAlter
   */
  public function testCkeditorStyleDefaultFallback() {
    $policy = new Csp();
    $policy->setDirective('default-src', [Csp::POLICY_SELF]);

    $this->response->method('getAttachments')
      ->willReturn([
        'library' => [
          'core/ckeditor5',
        ],
      ]);

    $alterEvent = new PolicyAlterEvent($policy, $this->response);

    $this->coreCspSubscriber->onCspPolicyAlter($alterEvent);

    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src')
    );
    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src-attr')
    );
    $this->assertEquals(
      [Csp::POLICY_SELF, Csp::POLICY_UNSAFE_INLINE],
      $alterEvent->getPolicy()->getDirective('style-src-elem')
    );
  }

  /**
   * Test that including umami modifies enabled font directive.
   *
   * @covers ::onCspPolicyAlter
   */
  public function testUmamiFont() {
    $policy = new Csp();
    $policy->setDirective('default-src', [Csp::POLICY_ANY]);
    $policy->setDirective('font-src', []);

    $this->response->method('getAttachments')
      ->willReturn([
        'library' => [
          'umami/webfonts-open-sans',
        ],
      ]);

    $alterEvent = new PolicyAlterEvent($policy, $this->response);

    $this->coreCspSubscriber->onCspPolicyAlter($alterEvent);

    $this->assertEquals(
      ['https://fonts.gstatic.com'],
      $alterEvent->getPolicy()->getDirective('font-src')
    );
  }

  /**
   * Test font-src fallback if default-src enabled.
   *
   * @covers ::onCspPolicyAlter
   */
  public function testUmamiFontDefaultFallback() {
    $policy = new Csp();
    $policy->setDirective('default-src', [Csp::POLICY_SELF]);

    $this->response->method('getAttachments')
      ->willReturn([
        'library' => [
          'umami/webfonts-open-sans',
        ],
      ]);

    $alterEvent = new PolicyAlterEvent($policy, $this->response);

    $this->coreCspSubscriber->onCspPolicyAlter($alterEvent);

    $this->assertEquals(
      [Csp::POLICY_SELF, 'https://fonts.gstatic.com'],
      $alterEvent->getPolicy()->getDirective('font-src')
    );
  }

}
