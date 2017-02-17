<?php

namespace Drupal\owl_import\Form;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use EasyRdf\Graph;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\owl_import\OntologyImport;

/**
 * Import ontology form.
 */
class ImportForm extends FormBase {
  /** @var \Drupal\owl_import\OntologyImport */
  protected $importer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('owl_import.ontology_import')
    );
  }

  public function __construct(OntologyImport $importer) {
    $this->importer = $importer;
  }

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
    $this->importer->nuke();
    $ontology_location = $form_state->getValue('ontology');
    $this->importer->loadModel($ontology_location);
    $this->importer->import();

  }

}
