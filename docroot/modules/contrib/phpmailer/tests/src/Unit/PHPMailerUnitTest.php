<?php

namespace Drupal\Tests\phpmailer\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Validates e-mail address extraction.
 *
 * This needs more work, so that it actually works with PHPUnit. It maybe that
 * due to it only testing a single function in the .module file and not a method
 * in a class, it won't work at all.
 *
 * @group phpmailer
 */
class PHPMailerUnitTest extends UnitTestCase {

  /**
   * Tests e-mail address extraction using phpmailer_parse_address().
   */
  function testAddressParser() {
    // Set up various test addresses according to RFC 5322.
    $this->addresses = [
      // addr-spec.
      [
        'mail' => 'user-1@domain.tld',
        'name' => ''
      ],
      // Invalid but supported angle-addr without preceding display-name.
      '<user-2@domain.tld>' => [
        'mail' => 'user-2@domain.tld',
        'name' => ''
      ],
      // Unquoted atom name-addr.
      'John Doe <user-3@domain.tld>' => [
        'mail' => 'user-3@domain.tld',
        'name' => 'John Doe'
      ],
      // Quoted atom name-addr.
      '"John Doe" <user-4@domain.tld>' => [
        'mail' => 'user-4@domain.tld',
        'name' => 'John Doe'
      ],
      // name-addr with a quoted-string in display-name.
      [
        'mail' => 'user-5@domain.tld',
        'name' => 'John "The Dude" Doe'
      ],
      // name-addr with a quoted-string and comma in display-name.
      [
        'mail' => 'user-6@domain.tld',
        'name' => 'John "The Dude" Doe (Foo, Bar)'
      ],
      // name-addr containing non-ASCII chars in display-name.
      [
        'mail' => 'user-7@domain.tld',
        'name' => 'Jöhn "The Düde" Döe'
      ],
    ];

    $all = [];
    // Validate each address format is correctly parsed.
    foreach ($this->addresses as $test => $address) {
      if (is_numeric($test)) {
        if ($address['name'] != '') {
          // Create a valid encoded email address.
          $test = '"' . addslashes(Unicode::mimeHeaderEncode($address['name'])) . '" <' . $address['mail'] . '>';
        }
        else {
          $test = $address['mail'];
        }
      }
      $result = phpmailer_parse_address($test);
      $this->assertEqual($result[0], $address, t('Successfully extracted %email, %name from %address.', ['%email' => $result[0]['mail'], '%name' => $result[0]['name'] ? $result[0]['name'] : '(blank)', '%address' => $test]), 'PHPMailer');
      $all[] = $test;
    }

    // One final test with all addresses concatenated.
    $result = phpmailer_parse_address(implode(', ', $all));
    $expected_result = array_values($this->addresses);
    $this->assertEqual($result, $expected_result, t('All concatenated e-mail addresses could be extracted.'), 'PHPMailer');
  }
}
