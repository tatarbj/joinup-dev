<?php

namespace Drupal\owl_import;

/**
 * An interface for all rdfs property type plugins.
 */
interface RdfsPropertyInterface {
  /**
   * Create a field.
   */
  function createField();

  /**
   * @param $uri
   *   The bundle uri.
   * @param $bundle
   *   The bundle object.
   * @return mixed
   */
  function setBundle($uri, $bundle);
  
  function setPropertyDefinition($uri, $property_definition);
}
