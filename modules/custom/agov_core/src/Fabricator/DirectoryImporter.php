<?php

/**
 * @file
 * Contains a DirectoryImporter
 *
 * @license GPL v2 http://www.fsf.org/licensing/licenses/gpl.html
 * @author Chris Skene chris at previousnext dot com dot au
 * @copyright Copyright(c) 2014 Previous Next Pty Ltd
 */

namespace Drupal\agov_core\Fabricator;

use Drupal\agov_core\Util\Logger;

/**
 * Class DirectoryImporter
 * @package Drupal\agov_core\Fabricator
 */
class DirectoryImporter {

  /**
   * Lazy constructor.
   *
   * @return static
   *   This.
   * @static
   */
  static public function init() {
    $entity_importer = EntityImporter::init();

    return new static($entity_importer);
  }

  /**
   * Constructor.
   *
   * @param EntityImporter $entity_importer
   *   An entity importer
   */
  public function __construct(EntityImporter $entity_importer) {
    $this->entityImporter = $entity_importer;
  }

  /**
   * Imports entities from a module.
   *
   * @param string $module
   *   Module containing the default content.
   * @param array $features_to_revert
   *   Name of modules to revert.
   */
  public function createDefaultContent($module, $features_to_revert = array()) {

    foreach ($features_to_revert as $feature) {
      features_revert_module($feature);
    }

    $path = drupal_get_path('module', $module) . '/default_content';
    Logger::log('Scannning %path for content', array(
      '%path' => $path,
    ));

    $count = 0;
    $failed = 0;

    foreach (file_scan_directory($path, '/\.json$/') as $file) {
      try {
        $entity_values = json_decode(file_get_contents($path . '/' . $file->filename), TRUE);
        $this->entityImporter->createEntity($entity_values, $path);
      }
      catch (\Exception $e) {
        watchdog_exception('agov_core', $e);
        Logger::log('%message, at %file:%line', array(
          '%message' => $e->getMessage(),
          '%file' => $e->getFile(),
          '%line' => $e->getLine(),
        ), WATCHDOG_ERROR);
        $failed++;
      }
      $count++;
    }

    Logger::log('Attempted to create %count entities, with %failed failures.', array(
      '%count' => $count,
      '%failed' => $failed,
    ));
  }
}
