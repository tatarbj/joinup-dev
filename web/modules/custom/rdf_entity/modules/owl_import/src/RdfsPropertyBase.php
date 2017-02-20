<?php

namespace Drupal\owl_import;

use Drupal\Component\Plugin\PluginBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * A base class to help developers implement their own rdf property plugins.
 *
 *
 * @see \Drupal\owl_import\Annotation\RdfsProperty
 * @see \Drupal\owl_import\RdfsPropertyInterface
 */
abstract class RdfsPropertyBase extends PluginBase implements RdfsPropertyInterface {
  protected $bundle;
  protected $bundle_uri;
  protected $property_uri;
  protected $property_definition;
  protected $field_name;

  protected function createFieldName() {
    $field_name = str_replace('=', '', base64_encode(sha1($this->property_uri)));
    $this->field_name = strtolower(substr($field_name, -28));
  }

  /**
   * {@inheritdoc}
   */
  public function setBundle($uri, $bundle) {
    $this->bundle_uri = $uri;
    $this->bundle = $bundle;
  }

  /**
   * {@inheritdoc}
   */
  function setPropertyDefinition($uri, $property_definition) {
    $this->property_uri = $uri;
    $this->property_definition = $property_definition;
  }
  /**
   * {@inheritdoc}
   */
  public function createField() {
    $this->createFieldName();

    FieldStorageConfig::create(array(
      'field_name' => $this->field_name,
      'entity_type' => 'rdf_entity',
      'type' => $this->pluginDefinition['field_type'],
    ))->save();
    $label = $this->property_definition['label'];
    $comment = $this->property_definition['comment'];
    $bundle = $this->bundle->id();
    FieldConfig::create([
      'field_name' => $this->field_name,
      'entity_type' => 'rdf_entity',
      'bundle' => $bundle,
      'label' => !empty($label) ? $label->getValue() : $this->field_name,
      'description' => !empty($comment) ? $comment->getValue() : '',
    ])->save();
    $field_type_manager = \Drupal::getContainer()->get('plugin.manager.field.field_type');

    $field_type_definition = $field_type_manager->getDefinition($this->pluginDefinition['field_type']);

    // Assign widget settings for the 'default' form mode.
    entity_get_form_display('rdf_entity', $bundle, 'default')
      ->setComponent($this->field_name, array(
        'type' => $field_type_definition['default_widget'],
      ))
      ->save();

    // Assign display settings for the 'default' and 'teaser' view modes.
    entity_get_display('rdf_entity', $bundle, 'default')
      ->setComponent($this->field_name, array(
        'label' => 'hidden',
        'type' => $field_type_definition['default_formatter'],
      ))
      ->save();
  }

}
