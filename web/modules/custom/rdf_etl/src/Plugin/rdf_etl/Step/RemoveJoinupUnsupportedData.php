<?php

declare(strict_types = 1);

namespace Drupal\rdf_etl\Plugin\rdf_etl\Step;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rdf_entity\Database\Driver\sparql\Connection;
use Drupal\rdf_entity\Entity\Query\Sparql\SparqlArg;
use Drupal\rdf_entity\Entity\RdfEntityMapping;
use Drupal\rdf_entity\RdfEntityGraphStoreTrait;
use Drupal\rdf_etl\Plugin\RdfEtlStepPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a process step that removes the triples not supported by Joinup.
 *
 * Scan the imported triples (which are now in 'SINK' graph) and filter out all
 * that are not Joinup entities: solutions, releases, distributions, licences,
 * owners or contact information.
 *
 * @RdfEtlStep(
 *  id = "remove_joinup_unsupported_data",
 *  label = @Translation("Remove data not supported by Joinup"),
 * )
 */
class RemoveJoinupUnsupportedData extends RdfEtlStepPluginBase implements ContainerFactoryPluginInterface {

  use RdfEntityGraphStoreTrait;

  /**
   * The SPARQL connection.
   *
   * @var \Drupal\rdf_entity\Database\Driver\sparql\Connection
   */
  protected $sparql;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\rdf_entity\Database\Driver\sparql\Connection $sparql
   *   The SPARQL connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $sparql) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sparql = $sparql;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('sparql_endpoint')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array &$data) {
    $graph_uri = $this->getConfiguration()['sink_graph'];

    $entity_type_uris = [];
    /** @var \Drupal\rdf_entity\RdfEntityMappingInterface $mapping */
    foreach (RdfEntityMapping::loadMultiple() as $mapping) {
      // Only add rdf:type URI for RDF entities.
      if ($mapping->getTargetEntityTypeId() === 'rdf_entity') {
        $entity_type_uris[] = $mapping->getRdfType();
      }
    }
    $entity_type_uris = SparqlArg::serializeUris($entity_type_uris);

    $query = <<<Ouery
DELETE FROM <$graph_uri> {
  ?subject ?predicate ?object
}
WHERE {
  ?subject ?predicate ?object
  {
    SELECT DISTINCT(?subject)
    FROM NAMED <$graph_uri>
    WHERE {
      GRAPH <$graph_uri> {
        ?subject <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?object .
        FILTER ( ?object NOT IN ( $entity_type_uris ) ) .
      }
    }
  }
}
Ouery;
    $this->sparql->query($query);
  }

}
