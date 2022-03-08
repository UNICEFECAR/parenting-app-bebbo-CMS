<?php

namespace Drupal\Tests\allowed_languages\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\Entity\User;

/**
 * Kernel test base for the allowed languages module.
 */
class AllowedLanguagesKernelTestBase extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'allowed_languages',
    'content_translation',
    'field',
    'language',
    'user',
    'system',
  ];

  /**
   * The user created for these tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');

    $this->user = User::create([
      'uid' => 1,
      'name' => $this->randomString(),
    ]);

    $this->user->save();

    $sv = ConfigurableLanguage::create(['label' => 'Swedish', 'id' => 'sv']);
    $en = ConfigurableLanguage::create(['label' => 'English', 'id' => 'en']);

    $sv->save();
    $en->save();

    $this->user->set('allowed_languages', [$sv, $en]);
  }

}
