<?php

declare(strict_types = 1);

namespace Drupal\rdf_etl\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Data pipeline item annotation object.
 *
 * @see \Drupal\rdf_etl\Plugin\RdfEtlPipelinePluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class RdfEtlPipeline extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The list of steps for this pipeline.
   *
   * @var string[]
   */
  public $steps;

}