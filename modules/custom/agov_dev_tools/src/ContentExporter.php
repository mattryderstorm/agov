<?php

/**
 * @file
 * Contains a ContentExporter
 *
 * @license GPL v2 http://www.fsf.org/licensing/licenses/gpl.html
 * @author Chris Skene chris at previousnext dot com dot au
 * @copyright Copyright(c) 2014 Previous Next Pty Ltd
 */

namespace Drupal\agov_dev_tools\Page;

use Drupal\ghost\Page\PageController;

/**
 * Class ContentExporter
 * @package Drupal\agov_dev_tools\Page
 */
class ContentExporter extends PageController {

  /**
   * Export page content.
   */
  public function exportContent($node) {

    $export = agov_core_export_entity('node', $node->nid);

    $export_form = array();
    $export_form['export'] = array(
      '#type' => 'textarea',
      '#title' => t('Export'),
      '#rows' => 20,
      '#value' => $export,
    );

    return drupal_render($export_form);
  }
}
