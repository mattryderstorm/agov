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
   * @param array $entity_values
   *   The entity values.
   * @param string $path
   *   Directory in which the source file resides. Defaults to NULL
   * @param string $parent_entity_type
   *   The parent entity type.
   * @param object $parent_entity
   *   Our entity
   *
   * @return object
   *   The created entity (which will already have been saved).
   */
  public function createEntity($entity_values, $path = NULL, $parent_entity_type = NULL, $parent_entity = NULL) {

    // Exports must contain an "entity_type" key so we can proceed.
    if (empty($entity_values['entity_type'])) {
      throw new \InvalidArgumentException('You must provide a value for entity_type');
    }

    // Get keys for the entity type.
    $entity_type = $entity_values['entity_type'];
    $info = entity_get_info($entity_type);
    $primary_key = $info['entity keys']['id'];
    $revision_key = $info['entity keys']['revision'];
    if (!empty($info['entity keys']['bundle'])) {
      $bundle_key = $info['entity keys']['bundle'];
    }
    else {
      // The entity type provides no bundle key: assume a single bundle, named
      // after the entity type.
      $bundle_key = $entity_type;
    }

    // Clean up keys provided on the source. These are of no use to us.
    unset($entity_values[$primary_key]);
    if (isset($entity_values[$revision_key])) {
      unset($entity_values[$revision_key]);
    }

    // 1. Preparation step.
    $paragraphs = array();
    $fields_info = field_info_instances($entity_type, $entity_values[$bundle_key]);
    foreach (array_keys($fields_info) as $field_name) {
      $field_info = field_info_field($field_name);

      // Paragraphs have been in-lined into our main entity and
      // need to be removed and remapped back to individual entities.
      if ($field_info['type'] == 'paragraphs') {
        $paragraphs[$field_name] = $this->extractParagraphEntities($field_name, $entity_values);
      }
    }

    // 2. Entity creation.
    $entity = $this->entityFactory->createEntity($entity_type, $entity_values);
    if (isset($entity->workbench_moderation)) {
      unset($entity->workbench_moderation);
    }

    // Ensure some defaults are set correctly.
    $entity->status = 1;
    $entity->revision = 1;
    $entity->is_new = 1;
    if (workbench_moderation_node_type_moderated($entity_values[$bundle_key])) {
      $entity->workbench_moderation_state_new = 'published';
    }

    // 3. Field resolution.
    foreach (array_keys($fields_info) as $field_name) {
      $field_info = field_info_field($field_name);

      // Paragraphs should have been exported directly, and
      // need to be resaved and remapped back to field values.
      if ($field_info['type'] == 'paragraphs' && isset($paragraphs[$field_name]) && !empty($paragraphs[$field_name])) {
        $this->processParagraphEntities($entity_type, $entity, $paragraphs[$field_name], $path);
      }

      if (!empty($path)) {
        if (isset($field_info['type']) && ($field_info['type'] == 'file' || $field_info['type'] == 'image')) {
          $this->processFileImageFields($path, $field_name, $entity);
        }
      }
    }

    // 4. Entity save.
    if ($entity_type == 'paragraphs_item') {
      $entity->setHostEntity($parent_entity_type, $parent_entity);
    }
    entity_save($entity_type, $entity);
    drupal_set_message(format_string('Created new entity of type %type with ID %id', array(
      '%type' => $entity_type,
      '%id' => $entity->{$primary_key},
    )));

    return $entity;
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
   * @param string $parent_entity_type
   *   The parent entity type.
   * @param object $parent_entity
   *   Our entity
   * @param array $paragraphs
   *   An array of paragraph item settings.
   * @param string $path
   *   Directory in which the source file resides. Defaults to NULL.
   */
  protected function processParagraphEntities($parent_entity_type, $parent_entity, $paragraphs, $path) {

    if (empty($paragraphs)) {

      return;
    }

    foreach ($paragraphs as $items) {

      if (empty($items)) {
        continue;
      }

      foreach ($items as $item) {

        $item = (array) $item;

        $paragraph_entity = $this->createEntity($item, $path, $parent_entity_type, $parent_entity);

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
      unset($entity->$field_name);

      foreach ($files as $lang => $lang_files) {
        foreach ($lang_files as $original_settings) {
          $uri = 'public://' . $original_settings['filename'];

          if (($handle = fopen($path . '/files/' . $original_settings['filename'], 'r')) && $new_file = file_save_data($handle, $uri)) {
            fclose($handle);

            $original_settings['fid'] = $new_file->fid;
            $original_settings['uri'] = $new_file->uri;
            $original_settings['filename'] = $new_file->filename;
            $original_settings['timestamp'] = $new_file->timestamp;
            $original_settings['uuid'] = $new_file->uuid;

            $entity->{$field_name}[$lang][] = $original_settings;
          }
        }
      }
    }
  }
}
