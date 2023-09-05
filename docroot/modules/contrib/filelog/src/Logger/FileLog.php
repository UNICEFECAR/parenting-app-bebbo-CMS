<?php

namespace Drupal\filelog\Logger;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Token;
use Drupal\filelog\FileLogException;
use Drupal\filelog\LogFileManagerInterface;
use Drupal\filelog\LogMessage;
use Psr\Log\LoggerInterface;
use function file_exists;
use function fopen;
use function fwrite;
use function watchdog_exception;

/**
 * File-based logger.
 */
class FileLog implements LoggerInterface {

  use RfcLoggerTrait;
  use DependencySerializationTrait;

  /**
   * The filelog settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $config;

  /**
   * The state system, for updating the filelog.rotation timestamp.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The token system, for formatting the log messages.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The log message parser, for formatting the log messages.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected LogMessageParserInterface $parser;

  /**
   * The time system.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The currently opened log file.
   *
   * @var resource
   */
  protected $logFile;

  /**
   * The STDERR fallback.
   *
   * @var resource
   */
  protected $stderr;

  /**
   * The log-file manager, providing file-handling methods.
   *
   * @var \Drupal\filelog\LogFileManagerInterface
   */
  protected LogFileManagerInterface $fileManager;

  /**
   * FileLog constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The logger.log_message_parser service.
   * @param \Drupal\filelog\LogFileManagerInterface $fileManager
   *   The filelog.file_manager service.
   */
  public function __construct(ConfigFactoryInterface $configFactory,
                              StateInterface $state,
                              Token $token,
                              TimeInterface $time,
                              LogMessageParserInterface $parser,
                              LogFileManagerInterface $fileManager) {
    $this->config = $configFactory->get('filelog.settings');
    $this->state = $state;
    $this->token = $token;
    $this->time = $time;
    $this->parser = $parser;
    $this->fileManager = $fileManager;
  }

  /**
   * Open the logfile for writing.
   *
   * @return bool
   *   Returns TRUE if the log file is available for writing.
   *
   * @throws \Drupal\filelog\FileLogException
   */
  protected function openFile(): bool {
    if ($this->logFile) {
      return TRUE;
    }

    // When creating a new log file, save the creation timestamp.
    $filename = $this->fileManager->getFileName();
    $create = !file_exists($filename);
    if (!$this->fileManager->ensurePath()) {
      $this->logFile = $this->stderr();
      throw new FileLogException('The log directory has disappeared.');
    }
    if ($this->logFile = fopen($filename, 'ab')) {
      if ($create) {
        $this->fileManager->setFilePermissions();
        $this->state->set('filelog.rotation', $this->time->getRequestTime());
      }
      return TRUE;
    }

    // Log errors to STDERR until the end of the current request.
    $this->logFile = $this->stderr();
    throw new FileLogException('The logfile could not be opened for writing. Logging to STDERR.');
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    if (!$this->shouldLog($level, $message, $context)) {
      return;
    }

    $entry = $this->render($level, $message, $context);

    try {
      $this->openFile();
      $this->write($entry);
    }
    catch (FileLogException $error) {
      // Log the exception, unless we were already logging a filelog error.
      if ($context['channel'] !== 'filelog') {
        watchdog_exception('filelog', $error);
      }
      // Write the message directly to STDERR.
      fwrite($this->stderr(), $entry . "\n");
    }
  }

  /**
   * Decides whether a message should be logged or ignored.
   *
   * @param mixed $level
   *   Severity level of the log message.
   * @param string $message
   *   Content of the log message.
   * @param array $context
   *   Context of the log message.
   *
   * @return bool
   *   TRUE if the message should be logged, FALSE otherwise.
   */
  protected function shouldLog(mixed $level, string $message, array $context = []): bool {
    // Ignore any messages below the configured severity.
    // (Severity decreases with level.)
    $should_log = $this->config->get('enabled') && $level <= $this->config->get('level');

    // Include or exclude based on channel list.
    return $should_log && (
      ($this->config->get('channels_type') === 'include')
      === in_array($context['channel'], $this->config->get('channels'), TRUE)
    );
  }

  /**
   * Renders a message to a string.
   *
   * @param mixed $level
   *   Severity level of the log message.
   * @param string $message
   *   Content of the log message.
   * @param array $context
   *   Context of the log message.
   *
   * @return string
   *   The formatted message.
   */
  protected function render(mixed $level, string $message, array $context = []): string {
    // Populate the message placeholders.
    $variables = $this->parser->parseMessagePlaceholders($message, $context);
    // Pass in bubbleable metadata that are just discarded later to prevent a
    // LogicException due to too early rendering. The metadata of the string
    // is not needed as it is not used for cacheable output but for writing to a
    // logfile.
    $bubbleable_metadata_to_discard = new BubbleableMetadata();
    $log = new LogMessage($level, $message, $variables, $context);
    $entry = $this->token->replace(
      $this->config->get('format'),
      ['log' => $log],
      [],
      $bubbleable_metadata_to_discard
    );
    return PlainTextOutput::renderFromHtml($entry);
  }

  /**
   * Open STDERR resource, or use STDERR constant if available.
   *
   * The STDERR constant is not defined in all PHP environments.
   *
   * @return resource
   *   Reference to the STDERR stream resource.
   */
  protected function stderr() {
    if ($this->stderr === NULL) {
      $this->stderr = defined('STDERR') ? STDERR : fopen('php://stderr', 'wb');
    }
    return $this->stderr;
  }

  /**
   * Write an entry to the logfile.
   *
   * @param string $entry
   *   The value to write. This should contain no newline characters.
   *
   * @throws \Drupal\filelog\FileLogException
   */
  protected function write(string $entry): void {
    if (!fwrite($this->logFile, $entry . "\n")) {
      throw new FileLogException('The message could not be written to the logfile.');
    }
  }

}
