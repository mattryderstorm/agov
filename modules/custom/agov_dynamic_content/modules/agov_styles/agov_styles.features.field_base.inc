<?php
/**
 * @file
 * agov_styles.features.field_base.inc
 */

/**
 * Implements hook_field_default_field_bases().
 */
function agov_styles_field_default_field_bases() {
  $field_bases = array();

  // Exported field_base: 'field_style_classes'
  $field_bases['field_style_classes'] = array(
    'active' => 1,
    'cardinality' => -1,
    'deleted' => 0,
    'entity_types' => array(),
    'field_name' => 'field_style_classes',
    'foreign keys' => array(
      'format' => array(
        'columns' => array(
          'format' => 'format',
        ),
        'table' => 'filter_format',
      ),
    ),
    'indexes' => array(
      'format' => array(
        0 => 'format',
      ),
    ),
    'locked' => 0,
    'module' => 'text',
    'settings' => array(
      'max_length' => 255,
      'required_summary' => 0,
      'show_summary' => 0,
    ),
    'translatable' => 0,
    'type' => 'text',
  );

  return $field_bases;
}
