<?php

namespace Drupal\Tests\pb_strings\Unit;

use Drupal\allowed_languages\AllowedLanguagesManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\pb_strings\Form\StringsFilterForm;
use Drupal\pb_strings\Form\StringsTranslateForm;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests language restrictions and protection of source strings.
 *
 * @group pb_strings
 */
class StringTranslationAccessTest extends UnitTestCase {

  /**
   * A translator sees assigned languages and cannot submit an unassigned one.
   */
  public function testAssignedLanguagesAndDeniedSubmission(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getLanguages')->willReturn([
      'en' => new Language(['id' => 'en']),
      'ar' => new Language(['id' => 'ar']),
    ]);
    $assigned = $this->createMock(AllowedLanguagesManagerInterface::class);
    $assigned->method('assignedLanguages')->willReturn(['ar']);
    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    $container->set('language_manager', $languages);
    $container->set('allowed_languages.allowed_languages_manager', $assigned);
    \Drupal::setContainer($container);
    $this->assertSame(['ar'], array_keys(StringsFilterForm::getAllowedLanguages()));
    $form = new StringsTranslateForm($this->createMock(EntityTypeManagerInterface::class), $languages,
      $this->createMock(Connection::class), $account);
    $state = (new FormState())->setValues(['langcode' => 'en', 'strings' => []]);
    $build = [];
    $this->expectException(AccessDeniedHttpException::class);
    $form->submitForm($build, $state);
  }

  /**
   * Even a strings administrator cannot overwrite a source label here.
   */
  public function testSourceLabelCannotBeOverwritten(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('hasPermission')->willReturn(TRUE);
    $languages = $this->createMock(LanguageManagerInterface::class);
    $languages->method('getLanguages')->willReturn(['en' => new Language(['id' => 'en'])]);
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('strings');
    $term->method('getUntranslated')->willReturnSelf();
    $term->method('language')->willReturn(new Language(['id' => 'en']));
    $term->expects($this->never())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($term);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    $container->set('language_manager', $languages);
    \Drupal::setContainer($container);
    $form = new StringsTranslateForm($manager, $languages, $this->createMock(Connection::class), $account);
    $state = (new FormState())->setValues(['langcode' => 'en', 'strings' => [1 => ['translation' => 'overwrite']]]);
    $build = [];
    $form->submitForm($build, $state);
    $this->assertFalse($state->hasAnyErrors());
  }

}
