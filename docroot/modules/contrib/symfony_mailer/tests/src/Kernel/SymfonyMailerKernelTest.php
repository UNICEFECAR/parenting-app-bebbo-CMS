<?php

namespace Drupal\Tests\symfony_mailer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer_test\MailerTestTrait;
use Drupal\Tests\RandomGeneratorTrait;

/**
 * Tests basic email sending.
 *
 * @group filter
 */
class SymfonyMailerKernelTest extends KernelTestBase {

  use MailerTestTrait;
  use RandomGeneratorTrait;

  /**
   * Email address for the tests.
   *
   * @var string
   */
  protected string $addressTo = 'symmfony-mailer-to@example.com';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['symfony_mailer', 'symfony_mailer_test', 'system', 'user', 'filter'];

  /**
   * The email factory.
   *
   * @var \Drupal\symfony_mailer\EmailFactoryInterface
   */
  protected $emailFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['symfony_mailer']);
    $this->installEntitySchema('user');
    $this->emailFactory = $this->container->get('email_factory');
    $this->config('system.site')
      ->set('name', 'Example')
      ->set('mail', 'sender@example.com')
      ->save();
  }

  /**
   * Basic email sending test.
   */
  public function testEmail() {
    // Test email error.
    $this->emailFactory->sendTypedEmail('symfony_mailer', 'test');
    $this->readMail();
    $this->assertError('An email must have a "To", "Cc", or "Bcc" header.');

    // Test email success.
    $this->emailFactory->sendTypedEmail('symfony_mailer', 'test', $this->addressTo);
    $this->readMail();
    $this->assertNoError();
    $this->assertSubject("Test email from Example");
    $this->assertTo($this->addressTo);
  }

  /**
   * Inline CSS adjuster test.
   */
  public function testInlineCss() {
    // Test an email including the test library.
    $this->emailFactory->newTypedEmail('symfony_mailer', 'test', $this->addressTo)->addLibrary('symfony_mailer_test/inline_css_test')->send();
    $this->readMail();
    $this->assertNoError();
    // The inline CSS from inline.text-small.css should appear.
    $this->assertBodyContains('<h4 class="text-small" style="padding-top: 3px; padding-bottom: 3px; text-align: center; color: white; background-color: #0678be; font-size: smaller; font-weight: bold;">');
    // The imported CSS from inline.day.css should appear.
    $this->assertBodyContains('<span class="day" style="font-style: italic;">');
  }

  /**
   * Data provider for ::testEmailAddresses().
   */
  public function testEmailAddressesProvider(): array {
    $addresses = [
      '<site>',
      $this->randomMachineName() . '@example.com',
      new Address($this->randomMachineName() . '@example.com'),
      new Address($this->randomMachineName() . '@example.com', $this->randomMachineName()),
    ];

    // Generate a different sets of headers/values.
    foreach (['cc', 'bcc', 'reply-to'] as $name) {

      // Tests header erasing.
      $data[] = [
        'name' => $name,
        'addresses' => NULL,
      ];

      // Tests the header with a single address value.
      foreach ($addresses as $address) {
        $data[] = [
          'name' => $name,
          'addresses' => $address,
        ];
      }

      // Tests with multiple addresses for the header.
      $data[] = [
        'name' => $name,
        'addresses' => $addresses,
      ];
    }

    return $data;
  }

  /**
   * Test a possibility to remove cc/bcc/reply-to email addresses.
   *
   * @param string $name
   *   The address header.
   * @param mixed $addresses
   *   The email addresses.
   *
   * @dataProvider testEmailAddressesProvider
   */
  public function testEmailAddresses(string $name, $addresses) {
    $email = $this->emailFactory->newTypedEmail('symfony_mailer', 'test', $this->addressTo);

    // Sets a random header value to ensure its overrides works correctly.
    $email->setAddress($name, $this->randomMachineName() . '@example.com');

    $email->setAddress($name, $addresses);
    $email->send();

    // Assert a test email with header exists.
    $this->readMail();
    $this->assertNoError();
    $this->assertAddress($name, $addresses);
  }

}
