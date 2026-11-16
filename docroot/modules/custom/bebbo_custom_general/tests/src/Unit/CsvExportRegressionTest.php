<?php

namespace Drupal\Tests\bebbo_custom_general\Unit;

use Drupal\bebbo_custom_general\Controller\CsvExportController;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\entity_share_client\Entity\RemoteInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Response;

/**
 * Tests safe CSV cells and completion of each batch page.
 *
 * @group bebbo_custom_general
 */
class CsvExportRegressionTest extends UnitTestCase {

  /**
   * Dangerous spreadsheet cells are literal text; ordinary text is retained.
   */
  public function testCsvFormulaEscaping(): void {
    $controller = (new \ReflectionClass(CsvExportController::class))->newInstanceWithoutConstructor();
    $encode = new \ReflectionMethod($controller, 'encodeCsvCell');
    foreach (['=1+1', '+SUM(A1:A2)', '-1+2', '@SUM(A1:A2)', "\t=1", '  =1'] as $cell) {
      $this->assertSame('"\'' . $cell . '"', $encode->invoke($controller, $cell));
    }
    $this->assertSame('"Hello, ""parent"""', $encode->invoke($controller, 'Hello, "parent"'));
  }

  /**
   * A fixed-page operation must finish rather than append that page forever.
   */
  public function testBatchPageFinishes(): void {
    $remote = $this->createMock(RemoteInterface::class);
    $store = $this->createMock(PrivateTempStore::class);
    $store->method('get')->with('export_params')->willReturn([
      'selected_remote' => $remote,
      'channel_url' => 'https://example.test/jsonapi/node/article',
    ]);
    $factory = $this->createMock(PrivateTempStoreFactory::class);
    $factory->method('get')->willReturn($store);
    $manager = $this->createMock(RemoteManagerInterface::class);
    $manager->expects($this->once())->method('jsonApiRequest')->willReturn(new Response(200, [], '{"data":[]}'));
    $controller = (new \ReflectionClass(CsvExportController::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($controller, 'tempStoreFactory'))->setValue($controller, $factory);
    (new \ReflectionProperty($controller, 'remoteManager'))->setValue($controller, $manager);
    $controller->setStringTranslation($this->getStringTranslationStub());
    $context = [];
    $controller->batchFetchData(2, 50, $context);
    $this->assertSame(1, $context['finished']);
  }

}
