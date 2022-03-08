<?php

namespace Drupal\feeds\Feeds\Parser;

use Drupal\feeds\Component\CsvParser as CsvFileParser;
use Drupal\feeds\Exception\EmptyFeedException;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Item\DynamicItem;
use Drupal\feeds\Result\FetcherResultInterface;
use Drupal\feeds\Result\ParserResult;
use Drupal\feeds\StateInterface;

/**
 * Defines a CSV feed parser.
 *
 * @FeedsParser(
 *   id = "csv",
 *   title = "CSV",
 *   description = @Translation("Parse CSV files."),
 *   form = {
 *     "configuration" = "Drupal\feeds\Feeds\Parser\Form\CsvParserForm",
 *     "feed" = "Drupal\feeds\Feeds\Parser\Form\CsvParserFeedForm",
 *   },
 * )
 */
class CsvParser extends ParserBase {

  /**
   * {@inheritdoc}
   */
  public function parse(FeedInterface $feed, FetcherResultInterface $fetcher_result, StateInterface $state) {
    // Get sources.
    $sources = [];
    foreach ($feed->getType()->getMappingSources() as $key => $info) {
      if (isset($info['value']) && trim(strval($info['value'])) !== '') {
        $sources[$info['value']] = $key;
      }
    }

    $feed_config = $feed->getConfigurationFor($this);

    if (!filesize($fetcher_result->getFilePath())) {
      throw new EmptyFeedException();
    }

    // Load and configure parser.
    $parser = CsvFileParser::createFromFilePath($fetcher_result->getFilePath())
      ->setDelimiter($feed_config['delimiter'] === 'TAB' ? "\t" : $feed_config['delimiter'])
      ->setHasHeader(!$feed_config['no_headers'])
      ->setStartByte((int) $state->pointer);

    // Wrap parser in a limit iterator.
    $parser = new \LimitIterator($parser, 0, $this->configuration['line_limit']);

    $header = !$feed_config['no_headers'] ? $parser->getHeader() : [];
    $result = new ParserResult();

    foreach ($parser as $row) {
      $item = new DynamicItem();

      foreach ($row as $delta => $cell) {
        $key = isset($header[$delta]) ? $header[$delta] : $delta;
        // Pick machine name of source, if one is found.
        if (isset($sources[$key])) {
          $key = $sources[$key];
        }
        $item->set($key, $cell);
      }

      $result->addItem($item);
    }

    // Report progress.
    $state->total = filesize($fetcher_result->getFilePath());
    $state->pointer = $parser->lastLinePos();
    $state->progress($state->total, $state->pointer);

    // Set progress to complete if no more results are parsed. Can happen with
    // empty lines in CSV.
    if (!$result->count()) {
      $state->setCompleted();
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappingSources() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function configSourceLabel() {
    return $this->t('CSV source');
  }

  /**
   * {@inheritdoc}
   */
  protected function configSourceDescription() {
    if ($this->getConfiguration('no_headers')) {
      return $this->t('Enter which column number of the CSV file to use: 0, 1, 2, etc.');
    }
    return $this->t('Enter the exact CSV column name. This is case-sensitive.');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultFeedConfiguration() {
    return [
      'delimiter' => $this->configuration['delimiter'],
      'no_headers' => $this->configuration['no_headers'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'delimiter' => ',',
      'no_headers' => 0,
      'line_limit' => 100,
    ];
  }

}
