<?php

/**
 * @file
 * Post update functions for the Joinup core module.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;
use Drupal\file\Entity\File;
use Drupal\rdf_entity\Entity\RdfEntityMapping;
use EasyRdf\Graph;
use EasyRdf\GraphStore;
use EasyRdf\Resource;

/**
 * Enable the Sub-Pathauto module.
 */
function joinup_core_post_update_enable_subpathauto() {
  \Drupal::service('module_installer')->install(['subpathauto']);
}

/**
 * Enable the Views Bulk Operations module.
 */
function joinup_core_post_update_install_vbo() {
  \Drupal::service('module_installer')->install(['views_bulk_operations']);
}

/**
 * Enable the Email Registration module.
 */
function joinup_core_post_update_install_email_registration() {
  \Drupal::service('module_installer')->install(['email_registration']);
}

/**
 * Enable the Joinup Invite module.
 */
function joinup_core_post_update_install_joinup_invite() {
  \Drupal::service('module_installer')->install(['joinup_invite']);
}

/**
 * Move the contact form attachments under the private scheme.
 */
function joinup_core_post_update_move_contact_form_attachments() {
  /** @var \Drupal\Core\File\FileSystemInterface $file_system */
  $file_system = Drupal::service('file_system');

  $message_storage = \Drupal::entityTypeManager()->getStorage('message');
  $ids = $message_storage->getQuery()
    ->condition('template', 'contact_form_submission')
    ->exists('field_contact_attachment')
    ->execute();

  foreach ($message_storage->loadMultiple($ids) as $message) {
    /** @var \Drupal\file\FileInterface $attachment */
    if ($attachment = $message->field_contact_attachment->entity) {
      if (!file_exists($attachment->getFileUri())) {
        continue;
      }
      $target = file_uri_target($attachment->getFileUri());
      $uri = "private://$target";
      $destination_dir = $file_system->dirname($uri);
      if (!file_prepare_directory($destination_dir, FILE_CREATE_DIRECTORY)) {
        throw new \RuntimeException("Cannot create directory '$destination_dir'.");
      }
      if (!file_move($attachment, $uri)) {
        throw new \RuntimeException("Cannot move '{$attachment->getFileUri()}' to '$uri'.");
      }
    }
  }

  // Finally, remove the empty public://contact_form directory.
  file_unmanaged_delete_recursive('public://contact_form');
}

/**
 * Enable the Smart Trim module.
 */
function joinup_core_post_update_install_smart_trim() {
  \Drupal::service('module_installer')->install(['smart_trim']);
}

/**
 * Remove stale 'system.action.joinup_transfer_solution_ownership' config.
 */
function joinup_core_post_update_remove_action_transfer_solution_ownership() {
  \Drupal::configFactory()
    ->getEditable('system.action.joinup_transfer_solution_ownership')
    ->delete();
}

/**
 * Enable 'spain_ctt' module.
 */
function joinup_core_post_update_install_spain_ctt() {
  \Drupal::service('module_installer')->install(['spain_ctt']);
}

/**
 * Enable and configure the 'rdf_schema_field_validation' module.
 */
function joinup_core_post_update_configure_rdf_schema_field_validation() {
  \Drupal::service('module_installer')->install(['rdf_schema_field_validation']);
  $graph_uri = 'http://adms-definition';
  $class_definition = 'http://www.w3.org/2000/01/rdf-schema#Class';

  $sparql_endpoint = \Drupal::service('sparql_endpoint');
  $connection_options = $sparql_endpoint->getConnectionOptions();
  $connect_string = 'http://' . $connection_options['host'] . ':' . $connection_options['port'] . '/sparql-graph-crud';
  // Use a local SPARQL 1.1 Graph Store.
  $gs = new GraphStore($connect_string);
  $graph = new Graph($graph_uri);
  $graph->parseFile(DRUPAL_ROOT . '/../resources/fixtures/adms-definition.rdf');
  $gs->replace($graph);

  $data = ['collection', 'solution', 'asset_release', 'asset_distribution'];
  foreach ($data as $bundle) {
    RdfEntityMapping::loadByName('rdf_entity', $bundle)
      ->setThirdPartySetting('rdf_schema_field_validation', 'property_predicates', ['http://www.w3.org/2000/01/rdf-schema#domain'])
      ->setThirdPartySetting('rdf_schema_field_validation', 'graph', $graph_uri)
      ->setThirdPartySetting('rdf_schema_field_validation', 'class', $class_definition)
      ->save();
  }
}

/**
 * Fix the banner predicate [ISAICP-4332].
 */
function joinup_core_post_update_fix_banner_predicate() {
  /** @var \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface $sparql_endpoint */
  $sparql_endpoint = \Drupal::service('sparql_endpoint');
  $retrieve_query = <<<QUERY
  SELECT ?graph ?entity_id ?image_uri
  FROM <http://joinup.eu/collection/published>
  FROM <http://joinup.eu/collection/draft>
  FROM <http://joinup.eu/solution/published>
  FROM <http://joinup.eu/solution/draft>
  FROM <http://joinup.eu/asset_release/published>
  FROM <http://joinup.eu/asset_release/draft>
  WHERE {
    GRAPH ?graph {
      ?entity_id a ?type .
      ?entity_id <http://xmlns.com/foaf/0.1/#term_Image> ?image_uri
    }
  }
QUERY;

  $results = $sparql_endpoint->query($retrieve_query);
  $items_to_update = [];
  foreach ($results as $result) {
    // Index by entity id so that only one value is inserted.
    $items_to_update[$result->graph->getUri()][$result->entity_id->getUri()] = [
      'value' => $result->image_uri->getValue(),
      'datatype' => $result->image_uri->getDatatype(),
    ];
  }

  $update_query = <<<QUERY
  WITH <@graph>
  DELETE {
    <@entity_id> <http://xmlns.com/foaf/0.1/#term_Image> "@value"^^@datatype
  }
  INSERT {
    <@entity_id> <http://xmlns.com/foaf/0.1/Image> "@value"^^@datatype
  }
  WHERE {
    <@entity_id> <http://xmlns.com/foaf/0.1/#term_Image> "@value"^^@datatype
  }
QUERY;
  $search = ['@graph', '@entity_id', '@value', '@datatype'];
  foreach ($items_to_update as $graph => $graph_data) {
    foreach ($graph_data as $entity_id => $item) {
      $replace = [
        $graph,
        $entity_id,
        $item['value'],
        $item['datatype'],
      ];
      $query = str_replace($search, $replace, $update_query);
      $sparql_endpoint->query($query);
    }
  }
}

/**
 * Fix the owner class predicate [ISAICP-4333].
 */
function joinup_core_post_update_fix_owner_predicate() {
  $rdf_entity_mapping = RdfEntityMapping::loadByName('rdf_entity', 'owner');
  $rdf_entity_mapping->setRdfType('http://xmlns.com/foaf/0.1/Agent');
  $rdf_entity_mapping->save();

  /** @var \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface $sparql_endpoint */
  $sparql_endpoint = \Drupal::service('sparql_endpoint');
  $retrieve_query = <<<QUERY
SELECT ?graph ?entity_id ?type
WHERE {
  GRAPH ?graph {
    ?entity_id a ?type .
    VALUES ?graph { <http://joinup.eu/owner/published> <http://joinup.eu/owner/draft> }
  }
}
QUERY;

  $results = $sparql_endpoint->query($retrieve_query);
  $items_to_update = [];
  foreach ($results as $result) {
    // Index by entity id so that only one value is inserted.
    $items_to_update[$result->graph->getUri()][] = $result->entity_id->getUri();
  }

  $update_query = <<<QUERY
  WITH <@graph>
  DELETE {
    <@entity_id> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/spec/#term_Agent>
  }
  INSERT {
    <@entity_id> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/0.1/Agent>
  }
  WHERE {
    <@entity_id> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://xmlns.com/foaf/spec/#term_Agent>
  }
QUERY;
  $search = ['@graph', '@entity_id'];
  foreach ($items_to_update as $graph => $entity_ids) {
    foreach ($entity_ids as $entity_id) {
      $replace = [
        $graph,
        $entity_id,
      ];
      $query = str_replace($search, $replace, $update_query);
      $sparql_endpoint->query($query);
    }
  }
}

/**
 * Fix data type of the solution contact point[ISAICP-4334].
 */
function joinup_core_post_update_fix_solution_contact_datatypea() {
  /** @var \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface $sparql_endpoint */
  $sparql_endpoint = \Drupal::service('sparql_endpoint');
  // Two issues are to be fixed here.
  // 1. The contact reference should be a resource instead of a literal.
  // 2. The predicate should be updated so that the IRI does not use the https
  // protocol.
  $retrieve_query = <<<QUERY
SELECT ?graph ?entity_id ?contact
WHERE {
  GRAPH ?graph {
    ?entity_id a ?type .
    ?entity_id <https://www.w3.org/ns/dcat#contactPoint> ?contact .
  }
}
QUERY;
  $results = $sparql_endpoint->query($retrieve_query);
  $items_to_update = [];
  foreach ($results as $result) {
    $contact_uri = $result->contact instanceof Resource ? $result->contact->getUri() : $result->contact->getValue();
    // Index by entity id so that only one value is inserted.
    $items_to_update[$result->graph->getUri()][$result->entity_id->getUri()][] = $contact_uri;
  }

  $update_query = <<<QUERY
  WITH <@graph>
  DELETE {
    <@entity_id> <https://www.w3.org/ns/dcat#contactPoint> ?contact_uri
  }
  INSERT {
    <@entity_id> <http://www.w3.org/ns/dcat#contactPoint> <@contact_uri>
  }
  WHERE {
    <@entity_id> <https://www.w3.org/ns/dcat#contactPoint> ?contact_uri .
    FILTER (str(?contact_uri) = str("@contact_uri"))
  }
QUERY;
  $search = ['@graph', '@entity_id', '@contact_uri'];
  foreach ($items_to_update as $graph => $graph_data) {
    foreach ($graph_data as $entity_id => $contact_uris) {
      foreach ($contact_uris as $contact_uri) {
        $replace = [
          $graph,
          $entity_id,
          $contact_uri,
        ];
        $query = str_replace($search, $replace, $update_query);
        $sparql_endpoint->query($query);
      }
    }
  }
}

/**
 * Fix data type of the access url field [ISAICP-4349].
 */
function joinup_core_post_update_fix_access_url_datatype() {
  /** @var \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface $sparql_endpoint */
  $sparql_endpoint = \Drupal::service('sparql_endpoint');
  $retrieve_query = <<<QUERY
SELECT ?graph ?entity_id ?predicate ?access_url
WHERE {
  GRAPH ?graph {
    ?entity_id a ?type .
    ?entity_id ?predicate ?access_url .
    VALUES ?type { <http://www.w3.org/ns/dcat#Catalog> <http://www.w3.org/ns/dcat#Distribution> }
    VALUES ?predicate { <http://www.w3.org/ns/dcat#accessURL> <http://xmlns.com/foaf/spec/#term_homepage> }
    VALUES ?graph { <http://joinup.eu/collection/draft> <http://joinup.eu/collection/published> <http://joinup.eu/asset_distribution/published> }
    FILTER (!datatype(?access_url) = xsd:anyURI) .
  }
}
QUERY;
  $results = $sparql_endpoint->query($retrieve_query);
  $items_to_update = [];
  foreach ($results as $result) {
    // The above query should not have errors or duplicated values so lets keep
    // a simple array structure.
    $items_to_update[] = [
      '@graph' => $result->graph->getUri(),
      '@entity_id' => $result->entity_id->getUri(),
      '@predicate' => $result->predicate->getUri(),
      '@access_url' => $result->access_url->getValue(),
    ];
  }

  $update_query = <<<QUERY
  WITH <@graph>
  DELETE {
    <@entity_id> <@predicate> "@access_url"^^<http://www.w3.org/2001/XMLSchema#string> .
    <@entity_id> <@predicate> "@access_url" .
  }
  INSERT {
    <@entity_id> <@predicate> "@access_url"^^<http://www.w3.org/2001/XMLSchema#anyURI>
  }
  WHERE {
    <@entity_id> <@predicate> ?access_url .
    VALUES ?access_url { "@access_url"^^<http://www.w3.org/2001/XMLSchema#string> "@access_url" }
  }
QUERY;
  foreach ($items_to_update as $data_array) {
    $query = str_replace(array_keys($data_array), array_values($data_array), $update_query);
    $sparql_endpoint->query($query);
  }
}

/**
 * Enable the Tallinn module.
 */
function joinup_core_post_update_install_tallinn() {
  \Drupal::service('module_installer')->install(['tallinn']);
}

/**
 * Migrate from Piwik to Matomo.
 */
function joinup_core_post_update_install_piwik2matomo() {
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $installer */
  $installer = \Drupal::service('module_installer');
  // Install the new modules. This is also uninstalling 'piwik_reporting_api'.
  $installer->install(['matomo_reporting_api']);
  // Uninstall the Piwik module.
  $installer->uninstall(['piwik']);
  // Note that the module installer API requires the presence of the modules in
  // the codebase. For this reason they will be removed from the codebase in a
  // follow-up.
}

/**
 * Enable the 'joinup_sparql' and 'joinup_federation' modules.
 */
function joinup_core_post_update_install_modules() {
  \Drupal::service('module_installer')->install(['joinup_sparql', 'joinup_federation']);
}

/**
 * Add the user support menu.
 */
function joinup_core_post_update_remove_tour_buttons() {
  \Drupal::service('module_installer')->install(['menu_admin_per_menu']);
  $config_factory = \Drupal::configFactory();
  $config_factory->getEditable('block.block.tourbutton_2')->delete();
  $config_factory->getEditable('block.block.tourbutton')->delete();
}

/**
 * Enable the 'error_page' module.
 */
function joinup_core_post_update_install_error_page() {
  \Drupal::service('module_installer')->install(['error_page']);
}

/**
 * Update the EIRA terms and perform other related tasks.
 */
function joinup_core_post_update_eira() {
  \Drupal::service('module_installer')->install(['eira']);

  $graph_uri = 'http://eira_skos';
  /** @var \Drupal\Driver\Database\joinup_sparql\Connection $connection */
  $connection = \Drupal::service('sparql_endpoint');
  $connection->query('CLEAR GRAPH <http://eira_skos>;');

  $graph = new Graph($graph_uri);
  $filename = DRUPAL_ROOT . '/../resources/fixtures/EIRA_SKOS.rdf';
  $graph->parseFile($filename);

  $sparql_connection = Database::getConnection('default', 'sparql_default');
  $connection_options = $sparql_connection->getConnectionOptions();
  $connect_string = "http://{$connection_options['host']}:{$connection_options['port']}/sparql-graph-crud";
  $graph_store = new GraphStore($connect_string);
  $graph_store->insert($graph);

  $graphs = [
    'http://joinup.eu/solution/published',
    'http://joinup.eu/solution/draft',
  ];
  $map = [
    'http://data.europa.eu/dr8/ConfigurationAndCartographyService' => 'http://data.europa.eu/dr8/ConfigurationAndSolutionCartographyService',
    'http://data.europa.eu/dr8/TestReport' => 'http://data.europa.eu/dr8/ConformanceTestReport',
    'http://data.europa.eu/dr8/TestService' => 'http://data.europa.eu/dr8/ConformanceTestingService',
    'http://data.europa.eu/dr8/ConfigurationAndCartographyServiceComponent' => 'http://data.europa.eu/dr8/ConfigurationAndSolutionCartographyServiceComponent',
    'http://data.europa.eu/dr8/TestComponent' => 'http://data.europa.eu/dr8/ConformanceTestingComponent',
    'http://data.europa.eu/dr8/ApplicationService' => 'http://data.europa.eu/dr8/InteroperableEuropeanSolutionService',
    'http://data.europa.eu/dr8/TestScenario' => 'http://data.europa.eu/dr8/ConformanceTestScenario',
    'http://data.europa.eu/dr8/PublicPolicyDevelopmentMandate' => 'http://data.europa.eu/dr8/PublicPolicyImplementationMandate',
    'http://data.europa.eu/dr8/Data-levelMapping' => 'http://data.europa.eu/dr8/DataLevelMapping',
    'http://data.europa.eu/dr8/Schema-levelMapping' => 'http://data.europa.eu/dr8/SchemaLevelMapping',
    'http://data.europa.eu/dr8/AuditAndLoggingComponent' => 'http://data.europa.eu/dr8/AuditComponent',
    'http://data.europa.eu/dr8/LegalRequirementOrConstraint' => 'http://data.europa.eu/dr8/LegalAct',
    'http://data.europa.eu/dr8/BusinessInformationExchange' => 'http://data.europa.eu/dr8/ExchangeOfBusinessInformation',
    'http://data.europa.eu/dr8/InteroperabilityAgreement' => 'http://data.europa.eu/dr8/OrganisationalInteroperabilityAgreement',
    'http://data.europa.eu/dr8/BusinessProcessManagementComponent' => 'http://data.europa.eu/dr8/OrchestrationComponent',
    'http://data.europa.eu/dr8/HostingAndNetworkingInfrastructureService' => 'http://data.europa.eu/dr8/HostingAndNetworkingInfrastructure',
    'http://data.europa.eu/dr8/PublicPolicyDevelopmentApproach' => 'http://data.europa.eu/dr8/PublicPolicyImplementationApproach',
  ];

  foreach ($graphs as $graph) {
    foreach ($map as $old_uri => $new_uri) {
      $query = <<<QUERY
WITH <$graph>
DELETE { ?solution_id <http://purl.org/dc/terms/type> <$old_uri> }
INSERT { ?solution_id <http://purl.org/dc/terms/type> <$new_uri> }
WHERE { ?solution_id <http://purl.org/dc/terms/type> <$old_uri> }
QUERY;
      $connection->query($query);
    }
  }

  // Finally, repeat the process that initially fixed the eira skos vocabulary.
  // @see ISAICP-3216.
  // @see \DrupalProject\Phing\AfterFixturesImportCleanup::main()
  //
  // Add the "Concept" type to all collection elements so that they are listed
  // as Parent terms.
  $connection->query('INSERT INTO <http://eira_skos> { ?subject a skos:Concept } WHERE { ?subject a skos:Collection . };');
  // Add the link to all "Concept" type elements so that they are all considered
  // as children of the EIRA vocabulary regardless of the depth.
  $connection->query('INSERT INTO <http://eira_skos> { ?subject skos:topConceptOf <http://data.europa.eu/dr8> } WHERE { GRAPH <http://eira_skos> { ?subject a skos:Concept .} };');
  // Create a backwards connection from the children to the parent.
  $connection->query('INSERT INTO <http://eira_skos> { ?member skos:broaderTransitive ?collection } WHERE { ?collection a skos:Collection . ?collection skos:member ?member };');
}

/**
 * Remove temporary 'file' entities that lack the file on file system.
 */
function joinup_core_post_update_fix_files(array &$sandbox) {
  if (!isset($sandbox['fids'])) {
    $sandbox['fids'] = array_values(\Drupal::entityQuery('file')
      ->condition('status', FILE_STATUS_PERMANENT, '<>')
      ->sort('fid')
      ->execute());
    $sandbox['processed'] = 0;
  }

  $fids = array_splice($sandbox['fids'], 0, 50);
  foreach (File::loadMultiple($fids) as $file) {
    /** @var \Drupal\file\FileInterface $file */
    if (!file_exists($file->getFileUri())) {
      $file->delete();
      $sandbox['processed']++;
    }
  }

  $sandbox['#finished'] = (int) !$sandbox['fids'];

  if ($sandbox['#finished'] === 1) {
    return $sandbox['processed'] ? "{$sandbox['processed']} file entities deleted." : "No file entities were deleted.";
  }
}

/**
 * Force-update all distribution aliases.
 */
function joinup_core_post_update_create_distribution_aliases(array &$sandbox) {
  if (!isset($sandbox['entity_ids'])) {
    // In order to force-update all distribution aliases in a post_update
    // function the pattern config file is imported manually, as normally, the
    // config sync runs after the database updatess.
    $pathauto_settings = Yaml::decode(file_get_contents(DRUPAL_ROOT . '/profiles/joinup/config/install/pathauto.pattern.rdf_entities_distributions.yml'));
    \Drupal::configFactory()
      ->getEditable('pathauto.pattern.rdf_entities_distributions')
      ->setData($pathauto_settings)
      ->save();

    $sandbox['entity_ids'] = \Drupal::entityQuery('rdf_entity')
      ->condition('rid', 'asset_distribution')
      ->execute();
    $sandbox['current'] = 0;
    $sandbox['max'] = count($sandbox['entity_ids']);
  }

  $entity_storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  /** @var \Drupal\pathauto\PathautoGeneratorInterface $pathauto_generator */
  $pathauto_generator = \Drupal::service('pathauto.generator');

  $result = array_slice($sandbox['entity_ids'], $sandbox['current'], 50);
  foreach ($entity_storage->loadMultiple($result) as $entity) {
    $pathauto_generator->updateEntityAlias($entity, 'update', ['force' => TRUE]);
    $sandbox['current']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['current'] / $sandbox['max']);
  return "Processed {$sandbox['current']} out of {$sandbox['max']}.";
}

/**
 * Disable database logging, use the syslog instead.
 */
function joinup_core_post_update_swap_dblog_with_syslog() {
  // Writing log entries in the database during anonymous requests is causing
  // load on the database. Another problem is that there is a cap on the number
  // of log entries that are retained in the the database. On some occasions
  // during heavy logging activity they rotated before we had the chance to read
  // them. Write the log entries to the syslog instead.
  \Drupal::service('module_installer')->install(['syslog']);
  \Drupal::service('module_installer')->uninstall(['dblog']);
}

/**
 * Enable the spdx module.
 */
function joinup_core_post_update_enable_spdx() {
  \Drupal::service('module_installer')->install(['spdx']);
}
