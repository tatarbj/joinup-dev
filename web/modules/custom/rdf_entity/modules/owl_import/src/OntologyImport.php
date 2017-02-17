<?php
namespace Drupal\owl_import;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use \EasyRdf\Graph;
use EasyRdf\Literal;

class OntologyImport {
  /**
   * The graph with all the triples describing the ontology.
   * @var \EasyRdf\Graph
   */
  protected $ontology_graph;

  protected $rdfsClasses;

  protected $rdfsProperties;

  protected $bundles;

  public function loadModel(string $model_location) {
    /** @var \EasyRdf\Graph $graph */
    $this->ontology_graph = Graph::newAndLoad($model_location);
    $this->initializeRdfsClasses();
    $this->initializeRdfsProperties();
  }

  public function import() {
    $this->createBundles();
    $this->createFields();
  }

  protected function initializeRdfsClasses() {
    /** @var \EasyRdf\Resource $class */
    foreach ($this->ontology_graph->allOfType('rdfs:Class') as $class) {
      $uri = $class->getUri();
      /** @var Literal $label */
      $label = $class->label('en');
      $deprecated = $class->get('owl:deprecated');
      if ($deprecated) {
        continue;
      }
      if (empty($label)) {
        throw new \Exception('Undefined label for class ' . $uri);
      }
      $description = $class->get('rdfs:comment');
      $this->rdfsClasses[$uri] = [
        'label' => $label->getValue(),
        'description' => !empty($description) ? $description->getValue() : '',
      ];
    }
  }

  protected function initializeRdfsProperties() {
    foreach ($this->ontology_graph->allOfType('rdf:Property') as $property) {
      $domain = $property->get('rdfs:domain');
      if (empty($domain)) {
        continue;
      }
      $deprecated = $property->get('owl:deprecated');
      if ($deprecated) {
        continue;
      }
      $domain = $domain->getUri();
      // Property of a to-be created class?
      if (!isset($this->rdfsClasses[$domain])) {
        continue;
      }
      $this->rdfsProperties[$domain][$property->getUri()] = [
        'range' => $property->get('rdfs:range'),
        'label' => $property->label('en'),
        'comment' => $property->get('rdfs:comment'),
      ];
    }
  }

  /**
   * Create the bundles.
   */
  protected function createBundles() {
    foreach ($this->rdfsClasses as $uri => $definition) {
      /** @var \Drupal\rdf_entity\Entity\RdfEntityType $rdf_bundle */
      $rdf_bundle = \Drupal::entityTypeManager()->getStorage('rdf_type')->create([
        // @todo Find a nice way to encode this.
        'rid' => strtolower($definition['label']),
        'description' => $definition['description'],
        'name' => $definition['label'],
      ]);
      $rdf_bundle->setThirdPartySetting('rdf_entity', 'mapping', [
        'rid' => [
          'target_id' => $uri,
          // @todo Determine best option from properties.
        ],
        'label' => [
          'value' => 'http://www.w3.org/2000/01/rdf-schema#label',
        ],
      ]);
      $rdf_bundle->setThirdPartySetting('rdf_entity', 'graph', [
        'default' => $uri,
        'draft' => $uri,
      ]);
      $rdf_bundle->save();
      $this->bundles[$uri] = $rdf_bundle;
    }
  }

  protected function createFields() {
    foreach ($this->rdfsProperties as $bundle_uri => $bundle_properties) {
      foreach ($bundle_properties as $property_uri => $property_def) {
        $field_name = str_replace('=', '', base64_encode(sha1($property_uri)));
        $field_name = strtolower(substr($field_name, -28));
        FieldStorageConfig::create(array(
          'field_name' => $field_name,
          'entity_type' => 'rdf_entity',
          'type' => 'text',
        ))->save();
        $label = $property_def['label'];
        $comment = $property_def['comment'];
        $bundle = $this->bundles[$bundle_uri]->id();
        FieldConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'rdf_entity',
          'bundle' => $bundle,
          'label' => !empty($label) ? $label->getValue() : $field_name,
          'description' => !empty($comment) ? $comment->getValue() : '',
        ])->save();

        // Assign widget settings for the 'default' form mode.
        entity_get_form_display('rdf_entity', $bundle, 'default')
          ->setComponent($field_name, array(
            'type' => 'text_textfield',
          ))
          ->save();

        // Assign display settings for the 'default' and 'teaser' view modes.
        entity_get_display('rdf_entity', $bundle, 'default')
          ->setComponent($field_name, array(
            'label' => 'hidden',
            'type' => 'text_default',
          ))
          ->save();
      }
    }
  }

  /**
   * Delete all existing rdf bundles.
   */
  public function nuke() {
    $storage = \Drupal::entityTypeManager()->getStorage('field_config');
    /** @var \Drupal\rdf_entity\Entity\RdfEntityType $type */
    foreach (\Drupal::entityTypeManager()->getStorage('rdf_type')->loadMultiple() as $type) {
      $fields = $storage->loadByProperties(['entity_type' => 'rdf_entity', 'bundle' => $type->id()]);
      foreach ($fields as $field) {
        $field->delete();
      }
      $type->delete();
    }
  }
}