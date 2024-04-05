<?php

declare(strict_types = 1);

namespace Drupal\Tests\jsonapi_page_limit\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group jsonapi_page_limit
 */
final class LimitTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'jsonapi_page_limit',
    'node',
  ];

  /**
   * The name of the test content type.
   *
   * @var string
   */
  protected string $testContentType = 'test_content_type';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a content type to test with.
    $this->contentType = $this->drupalCreateContentType(['type' => $this->testContentType]);

    // Rebuild the routes before performing any tests to ensure the JSON:API
    // dynamic routes function correctly.
    \Drupal::service('router.builder')->rebuild();

    // Create lots of test content.
    for ($nodeCount = 0; $nodeCount < 100; $nodeCount++) {
      $this->drupalCreateNode(['type' => $this->testContentType]);
    }
  }

  /**
   * Sets Drupal to have a configured JSON:API Page Limit.
   *
   * @param int $pageLimit
   *   Page limit.
   */
  protected function setJsonApiPageLimit(int $pageLimit) : void {
    $parameters = $this->container->getParameter('jsonapi_page_limit.size_max');
    $parameters['/jsonapi/*'] = $pageLimit;

    $this->setContainerParameter('jsonapi_page_limit.size_max', $parameters);
    $this->rebuildContainer();
    $this->resetAll();
  }

  /**
   * Makes a JSON:API request and assert the results have the correct size.
   *
   * @param int $expectedResultCount
   *   Expected count of results in the JSON:API response.
   * @param int $requestLimit
   *   Limit specified in the HTTP request.
   * @param int $drupalLimit
   *   Limit specified at a system level in Drupal.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function testRequest(int $expectedResultCount, int $requestLimit = 0, int $drupalLimit = 0) : void {
    // Set the system-wide JSON:API page limit if passed.
    if ($drupalLimit) {
      $this->setJsonApiPageLimit($drupalLimit);
    }
    $options = [];
    if ($requestLimit) {
      $options = ['query' => ['page[limit]' => $requestLimit]];
    }
    $response = $this->drupalGet("jsonapi/node/$this->testContentType", $options);
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($response);
    $this->assertNotEmpty($response->data);
    $this->assertCount($expectedResultCount, $response->data);
  }

  /**
   * Test we get 50 items back on a normal request with no limit configured.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testUnlimitedRequest(): void {
    $this->testRequest(50);
  }

  /**
   * Test we get 75 items back on a request limit and config limit of 75.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLimitedRequestWithConfiguredLimit(): void {
    $this->testRequest(75, 75, 75);
  }

  /**
   * Test we get 55 items back on a request limit of 55 and config limit of 75.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLimitedRequestLessThanConfiguredLimit(): void {
    $this->testRequest(55, 55, 75);
  }

  /**
   * Test we get 75 items back on a request limit of 100 and config limit of 75.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLimitedRequestWithMoreThanConfiguredLimit(): void {
    $this->testRequest(75, 100, 75);
  }

}
