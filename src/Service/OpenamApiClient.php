<?php

namespace Drupal\openam_api\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Config\ConfigFactory;
use Exception;

/**
 * Service class for OpenamApiClient.
 */
class OpenamApiClient {

  /**
   * An instance of Config Factory.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * LoggerChannelFactory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  public $loggerFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Create the OpenAM API client.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   An instance of Config Factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   LoggerChannelFactory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactory $configFactory,
                              LoggerChannelFactory $loggerFactory,
                              ModuleHandlerInterface $module_handler) {
    $this->config = $configFactory->get('openam_api.settings');
    $this->loggerFactory = $loggerFactory;
    $this->moduleHandler = $module_handler;

    // TODO Extend this for OpenAM Client.
  }

  /**
   * Debug OpenAM response and exceptions.
   *
   * @param mixed $data
   *   Data to debug.
   * @param string $type
   *   Response or Exception.
   */
  public function debug($data, $type = 'response') {
    if ($this->config->get('debug_' . $type)) {
      if ($this->moduleHandler->moduleExists('devel')) {
        ksm($data);
      }
    }
  }

  /**
   * Logs an error to the Drupal error log.
   *
   * @param string $message
   *   The error message.
   * @param \Exception $e
   *   The exception being handled.
   */
  public function logError($message, Exception $e) {
    $this->debug($e, 'exception');
    $this->loggerFactory->get('openam_api')->error(
      "@message - @exception", [
        '@message' => $message,
        // TODO Update the exception output for better readability.
        '@exception' => $e,
      ]
    );
  }

}
