<?php

/**
 * @file
 * Contains an EntityImporter
 *
 * @license GPL v2 http://www.fsf.org/licensing/licenses/gpl.html
 * @author Chris Skene chris at previousnext dot com dot au
 * @copyright Copyright(c) 2014 Previous Next Pty Ltd
 */

namespace Drupal\agov_core\Fabricator;

use Drupal\agov_core\Util\Logger;
use Drupal\agov_core\Fabricator\EntityFactory;

/**
 * Class EntityImporter
 * @package Drupal\agov_core
 */
class EntityImporter {

  /**
   * The EntityFactory
   *
   * @var EntityFactory
   */
  protected $entityFactory;

  /**
   * Lazy constructor.
   *
   * @return static
   *   This EntityImporter
   * @static
   */
  static public function init() {

    $entity_factory = EntityFactory::init();

    return new static($entity_factory);
  }

  /**
   * Constructor.
   *
   * @param EntityFactory $entity_factory
   *   An EntityFactory
   */
  public function __construct(EntityFactory $entity_factory) {

    $this->entityFactory = $entity_factory;
  }

  /**
   * Imports default entity.
   *
   * @param string $path
   *   Directory in which file resides
   * @param string $file
   *   File name.
   *
   * @throws \EntityMalformedException
   *   When the entity is malformed.
   * @throws \InvalidArgumentException
   *   When no entity-type is provided.
   */
  public function createEntity($path, $file) {

    $entity_values = json_decode(file_get_contents($path . '/' . $file->filename), TRUE);
    unset($entity_values['nid']);
    unset($entity_values['vid']);

    if (empty($entity_values['entity_type'])) {
      throw new \InvalidArgumentException('You must provide a value for entity_type');
    }

    // Get keys for the entity type.
    $entity_type = $entity_values['entity_type'];
    $info = entity_get_info($entity_type);
    $primary_id = $info['entity keys']['id'];
    if (!empty($info['entity keys']['bundle'])) {
      $bundle_id = $info['entity keys']['bundle'];
    }
    else {
      // The entity type provides no bundle key: assume a single bundle, named
      // after the entity type.
      $bundle_id = $entity_type;
    }

    // Get field information to process per-type fields.
    $paragraphs = array();
    $fields_info = field_info_instances($entity_type, $entity_values[$bundle_id]);
    foreach (array_keys($fields_info) as $field_name) {
      $field_info = field_info_field($field_name);

      // Paragraphs have been in-lined into our main entity and
      // need to be removed and remapped back to individual entities.
      if ($field_info['type'] == 'paragraphs') {
        $paragraphs[$field_name] = $this->extractParagraphEntities($field_name, $entity_values);
      }
    }

    // Create the entity.
    $entity = $this->entityFactory->createEntity($entity_type, $entity_values);
    if (isset($entity->workbench_moderation)) {
      unset($entity->workbench_moderation);
    }

    foreach (array_keys($fields_info) as $field_name) {
      $field_info = field_info_field($field_name);

      // Paragraphs should have been exported directly, and
      // need to be resaved and remapped back to field values.
      if ($field_info['type'] == 'paragraphs' && isset($paragraphs[$field_name]) && !empty($paragraphs[$field_name])) {
        $this->processParagraphEntities($entity, $entity_type, $paragraphs[$field_name]);
      }

      if (isset($fields_info['type']) && ($fields_info['type'] == 'file' || $fields_info['type'] == 'image')) {
        $this->processFileImageFields($path, $field_name, $entity);
      }
    }

    $entity->status = 1;
    $entity->revision = 1;

    if (workbench_moderation_node_type_moderated($entity_values[$bundle_id])) {
      $entity->workbench_moderation_state_new = 'published';
    }

    entity_save($entity_type, $entity);
    drupal_set_message(format_string('Created new entity of type %type with ID %id', array(
      '%type' => $entity_type,
      '%id' => $entity->{$primary_id},
    )));
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
        $this->createEntity($path, $file);

      }
      catch (\Exception $e) {
        watchdog_exception('agov_core', $e);
        $failed++;
      }
      $count++;
    }

    Logger::log('Attempted to create %count entities, with %failed failures.', array(
      '%count' => $count,
      '%failed' => $failed,
    ));

  }

  /**
   * Extract paragraph entities.
   *
   * @param string $field_name
   *   Field name containing inline entities.
   * @param array $parent_entity_values
   *   Our entity
   *
   * @return array
   *   An array, possibly containing paragraph entity information.
   */
  protected function extractParagraphEntities($field_name, &$parent_entity_values) {
    $paragraphs = array();

    // Extract the paragraph entities for later creation, and remove the values
    // so that we don't break things during entity creation.
    if (isset($parent_entity_values[$field_name]) && !empty($parent_entity_values[$field_name])) {
      $paragraphs = $parent_entity_values[$field_name];
      $parent_entity_values[$field_name] = array();
    }

    return $paragraphs;
  }

  /**
   * Process paragraph entities.
   *
   * @param object $parent_entity
   *   Our entity
   * @param string $parent_entity_type
   *   The parent entity type.
   * @param array $paragraphs
   *   An array of paragraph item settings.
   *
   * @throws \Exception
   */
  protected function processParagraphEntities($parent_entity, $parent_entity_type, $paragraphs) {

    if (empty($paragraphs)) {

      return;
    }

    foreach ($paragraphs as $items) {

      if (empty($items)) {
        continue;
      }

      foreach ($items as $item) {

        $item = (array) $item;

        // Convert this to a "new" entity.
        unset($item['item_id']);
        unset($item['revision_id']);
        $item['is_new'] = TRUE;

        $paragraph_entity = $this->entityFactory->createParagraph($item);
        $paragraph_entity->setHostEntity($parent_entity_type, $parent_entity);
        $paragraph_entity->save();

        Logger::log('Created paragraph %id of type %bundle', array(
          '%id' => $paragraph_entity->identifier(),
          '%bundle' => $paragraph_entity->bundle(),
        ));
      }
    }
  }

  /**
   * Process File Fields.
   *
   * @param string $path
   *   Path to the directory containing the default content.
   * @param string $field_name
   *   The field name
   * @param object $entity
   *   The entity.
   */
  protected function processFileImageFields($path, $field_name, $entity) {

    if (!empty($entity->{$field_name})) {

      $files = $entity->$field_name;

      foreach ($files as $file_settings) {
        if (($handle = fopen($path . '/files/' . $file_settings->filename, 'r')) && $file = file_save_data($handle, 'public://' . $file_settings->filename)) {
          fclose($handle);
          $file_settings->uri = 'public://' . $file_settings->filename;
          $file_settings->fid = $file->fid;
          $entity->{$field_name}[LANGUAGE_NONE][] = $file_settings;
        }
      }
    }
  }
}
