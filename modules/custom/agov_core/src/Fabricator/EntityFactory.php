<?php

/**
 * @file
 * Contains a
 *
 * @license GPL v2 http://www.fsf.org/licensing/licenses/gpl.html
 * @author Chris Skene chris at previousnext dot com dot au
 * @copyright Copyright(c) 2014 Previous Next Pty Ltd
 */

namespace Drupal\agov_core\Fabricator;


/**
 * Class EntityFactory
 * @package Drupal\agov_core\Fabricator
 */
class EntityFactory {

  /**
   * Lazy constructor.
   *
   * @return static
   *   This EntityFactory.
   * @static
   */
  static public function init() {
    return new static();
  }

  /**
   * Create a generic entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param array $entity_values
   *   The entity values.
   *
   * @return object
   *   An entity.
   */
  public function createEntity($entity_type, array $entity_values) {

    return entity_create($entity_type, $entity_values);
  }

  /**
   * Create a new Paragraph.
   *
   * @param array $paragraphs_item
   *   An item containing the properties to apply
   *
   * @return \ParagraphsItemEntity
   *   A Paragraphs Item.
   */
  public function createParagraph(array $paragraphs_item) {

    return entity_create('paragraphs_item', $paragraphs_item);
  }
}
