<?php

namespace Drupal\owl_import;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\owl_import\Annotation\RdfsProperty;

/**
 * A plugin manager for rdfs property plugins.
 */
class RdfsPropertyPluginManager extends DefaultPluginManager {

  /**
   * Creates the discovery object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
   
    $subdir = 'Plugin/RdfsProperty';

    // Enforce interface.
    $plugin_interface = RdfsPropertyInterface::class;

    // The name of the annotation class that contains the plugin definition.
    $plugin_definition_annotation_name = RdfsProperty::class;

    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name);

    // Setup an alter hook.
    $this->alterInfo('rdfs_property_alter');

    $this->setCacheBackend($cache_backend, 'rdfs_property_info');
  }

}
