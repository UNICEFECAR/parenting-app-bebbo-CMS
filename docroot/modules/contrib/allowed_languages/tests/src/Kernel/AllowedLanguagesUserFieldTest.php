<?php

namespace Drupal\Tests\allowed_languages\Kernel;

/**
 * Tests for the allowed languages field added fo the user entity.
 *
 * @group allowed_languages
 */
class AllowedLanguagesUserFieldTest extends AllowedLanguagesKernelTestBase {

  /**
   * Test that the field has been applied to the user entity.
   */
  public function testAllowedLanguagesFieldExists() {
    $this->assertTrue($this->user->hasField('allowed_languages'));
  }

  /**
   * Test the allowed languages function to get a users languages.
   */
  public function testAllowedLanguageGetAllowedLanguagesForUser() {
    $allowed_languages = \Drupal::service('allowed_languages.allowed_languages_manager')->assignedLanguages($this->user);

    $this->assertEquals($allowed_languages, ['sv', 'en']);
  }

  /**
   * Test the function to get allowed language options.
   */
  public function testAllowedLanguagesGetLanguageOptions() {
    $language_options = allowed_languages_get_language_options();

    $this->assertEquals($language_options, [
      'sv' => 'Swedish',
      'en' => 'English',
    ]);
  }

}
