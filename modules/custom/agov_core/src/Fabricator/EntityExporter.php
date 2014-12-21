<?php

/**
 * @file
 * Contains an EntityExporter
 *
 * @license GPL v2 http://www.fsf.org/licensing/licenses/gpl.html
 * @author Chris Skene chris at previousnext dot com dot au
 * @copyright Copyright(c) 2014 Previous Next Pty Ltd
 */

namespace Drupal\agov_core\Fabricator;


/**
 * Class EntityExporter
 * @package Drupal\agov_core\Fabricator
 */
class EntityExporter {

  /**
   * Lazy constructor.
   *
   * @return static
   *   This EntityExporter
   * @static
   */
  static public function init() {

    return new static();
  }

  /**
   * Helper to export an entity.
   *
   * Use this to generate exports.
   *
   * @param string $entity_type
   *   The entity type
   * @param string $entity_id
   *   The entity identifier
   *
   * @return string
   *   Encoded JSON
   *
   * @throws \Exception
   */
  public function exportEntity($entity_type, $entity_id) {

    $entity = $this->entityLoad($entity_type, $entity_id);

    list(, , $bundle) = entity_extract_ids($entity_type, $entity);

    $fields_info = field_info_instances($entity_type, $bundle);

    foreach (array_keys($fields_info) as $field_name) {
      $field_info = field_info_field($field_name);

      // Paragraphs get mapped exactly to their parent, and can be loaded here.
      if ($field_info['type'] == 'paragraphs') {

        $this->processParagraphEntities($field_name, $entity);
      }
    }

    $entity->entity_type = $entity_type;

    return json_encode($entity, JSON_PRETTY_PRINT);
  }

  /**
   * Load entities.
   *
   * @param string $entity_type
   *   The entity type
   * @param array $entity_id
   *   The entity identifier
   *
   * @return array
   *   An array of entity objects indexed by their ids. When no results are
   *   found, an empty array is returned.
   */
  protected function entityLoad($entity_type, $entity_id) {

    $entity_ids = array($entity_id);
    $entities = entity_load($entity_type, $entity_ids);

    if (empty($entities)) {
      drupal_set_message('No entities found');
    }

    return reset($entities);
  }

  /**
   * Process Paragraph entities into inline values.
   *
   * @param string $field_name
   *   The field name
   * @param object $entity
   *   Our entity.
   */
  protected function processParagraphEntities($field_name, &$entity) {

    if (isset($entity->$field_name) && !empty($entity->$field_name)) {
      $paragraph_entities = array();

      foreach ($entity->$field_name as $paragraphs_item_lang => $paragraphs_items) {
        if (empty($paragraphs_items)) {
          continue;
        }

        foreach ($paragraphs_items as $paragraphs_item) {
          $paragraph_entities[$paragraphs_item_lang][] = $this->entityLoad('paragraphs_item', $paragraphs_item['value']);
        }
      }

      $entity->$field_name = $paragraph_entities;
    }
  }
}
