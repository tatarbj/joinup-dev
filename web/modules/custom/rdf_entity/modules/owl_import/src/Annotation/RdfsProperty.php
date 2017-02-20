<?php

namespace Drupal\owl_import\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a rdfs property field annotation object.
 *
 *
 * @see plugin_api
 *
 * @Annotation
 */
class RdfsProperty extends Plugin {

  /**
   * The plugin ID: the range of the rdfs property.
   *
   * @var string
   */
  public $id;

  /**
   * The field type used to save this property.
   * @var string
   */
  public $field_type;

}
