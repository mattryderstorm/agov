<?php

/**
 * @file
 * Contains a Logger util.
 *
 * @license GPL v2 http://www.fsf.org/licensing/licenses/gpl.html
 * @author Chris Skene chris at previousnext dot com dot au
 * @copyright Copyright(c) 2014 Previous Next Pty Ltd
 */

namespace Drupal\agov_core\Util;

use Drupal\ghost\Utilities\LogMessage;

/**
 * Class Logger
 * @package Drupal\agov_core\Util
 */
class Logger {

  /**
   * Standard logging function.
   *
   * @param string $message
   *   The message, containing replacement arguments.
   * @param array $args
   *   The arguments to replace
   * @param int $type
   *   The type. Defaults to WATCHDOG_NOTICE.
   *
   * @return static
   *   This logger, however the message has already been logged.
   * @static
   */
  static public function log($message, array $args = array(), $type = WATCHDOG_NOTICE) {

    $logger = new static();

    return $logger->logMessage($message, $args, $type);
  }

  /**
   * Log a message.
   *
   * @param string $message
   *   The message, containing replacement arguments.
   * @param array $args
   *   The arguments to replace
   * @param int $type
   *   The type. Defaults to WATCHDOG_NOTICE.
   */
  public function logMessage($message, array $args = array(), $type = WATCHDOG_NOTICE) {

    LogMessage::logMessage('agov_core', $message, $args, $type, $this->logToScreen());
  }

  /**
   * Whether to log to screen or not.
   *
   * @return bool
   *   TRUE if screen logging is requested.
   */
  protected function logToScreen() {

    return AGOV_CORE_LOG_SCREEN;
  }
}
