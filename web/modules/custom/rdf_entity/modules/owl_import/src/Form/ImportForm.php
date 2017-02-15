<?php

namespace Drupal\owl_import\Form;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use EasyRdf\Graph;

/**
 * Import ontology form.
 */
class ImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rdf_owl_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['ontology'] = [
      '#type' => 'textfield',
      '#default_value' => 'http://localhost/dcat.rdf',
      '#title' => $this->t('Ontology URL'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->nuke();
    $ontology = $form_state->getValue('ontology');
    $graph = Graph::newAndLoad($ontology);

    $bundle_definitions = [];
    $property_def_by_bundle = [];

    /** @var \EasyRdf\Resource $class */
    foreach ($graph->allOfType('rdfs:Class') as $class) {
      $uri = $class->getUri();
      /** @var Literal $label */
      $label = $class->label('en');
      $deprecated = $class->get('owl:deprecated');
      if ($deprecated) {
        continue;
      }
      $random = new Random();
      $rand = strtolower($random->name(16));

      $description = $class->get('rdfs:comment');
      $bundle_definitions[$uri] = [
        'label' => !empty($label) ? $label->getValue() : $rand,
        'description' => !empty($description) ? $description->getValue() : '',
      ];
    }
    foreach ($graph->allOfType('rdf:Property') as $property) {
      $domain = $property->get('rdfs:domain');
      if (empty($domain)) {
        continue;
      }
      $domain = $domain->getUri();
      // Property of on of the bundles?
      if (!isset($bundle_definitions[$domain])) {
        continue;
      }
      $property_def_by_bundle[$domain][$property->getUri()] = [
        'range' => $property->get('rdfs:range'),
        'label' => $property->label('en'),
        'comment' => $property->get('rdfs:comment'),
      ];
    }
    $bundles = $this->createBundles($bundle_definitions);
    $this->createFields($bundles, $property_def_by_bundle);
  }

  /**
   * Attach the fields to the bundle.
   */
  protected function createFields($bundles, array $property_def_by_bundle) {
    foreach ($property_def_by_bundle as $bundle_uri => $bundle_properties) {
      foreach ($bundle_properties as $property_uri => $property_def) {
        $random = new Random();
        $field_name = strtolower($random->name(16));
        // $fieldname = strtolower($property_def['label']);.
        FieldStorageConfig::create(array(
          'field_name' => $field_name,
          'entity_type' => 'rdf_entity',
          'type' => 'text',
        ))->save();
        $label = $property_def['label'];
        FieldConfig::create([
          'field_name' => $field_name,
          'entity_type' => 'rdf_entity',
          'bundle' => $bundles[$bundle_uri]->id(),
          'label' => !empty($label) ? $label->getValue() : $field_name,
        ])->save();
      }
    }
  }

  /**
   * Create the bundles.
   */
  protected function createBundles(array $definitions) {
    $bundles = NULL;
    foreach ($definitions as $uri => $definition) {
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
      $rdf_bundle->save();
      $bundles[$uri] = $rdf_bundle;
    }
    return $bundles;
  }

  /**
   * Delete all existing rdf bundles.
   */
  public function nuke() {
    /** @var RdfEntityType $type */
    foreach (\Drupal::entityTypeManager()->getStorage('rdf_type')->loadMultiple() as $type) {
      $type->delete();
    }
  }

}
