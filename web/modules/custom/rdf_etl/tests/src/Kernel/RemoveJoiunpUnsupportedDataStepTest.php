<?php

declare(strict_types = 1);

namespace Drupal\Tests\rdf_etl\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\rdf_entity\RdfEntityGraphStoreTrait;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;
use EasyRdf\Graph;

/**
 * Tests the 'remove_joinup_unsupported_data' process step plugin.
 *
 * @group rdf_etl
 */
class RemoveJoiunpUnsupportedDataStepTest extends KernelTestBase {

  use RdfDatabaseConnectionTrait;
  use RdfEntityGraphStoreTrait;

  /**
   * Test graph URI.
   *
   * @var string
   */
  const TEST_GRAPH = 'http://example.com/graph/test/sink';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rdf_entity',
    'rdf_etl',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setUpSparql();
    $graph = new Graph(static::TEST_GRAPH);
    $graph->parse(file_get_contents(__DIR__ . '/../../fixtures/valid_adms.rdf'));
    $this->createGraphStore()->replace($graph);
  }

  /**
   * Test ADMSv2 changes.
   */
  public function test() {
    $graph_uri = static::TEST_GRAPH;

    /** @var \Drupal\rdf_etl\Plugin\RdfEtlStepPluginManager $manager */
    $manager = $this->container->get('plugin.manager.rdf_etl_step');
    /** @var \Drupal\rdf_etl\Plugin\RdfEtlStepInterface $plugin */
    $plugin = $manager->createInstance('remove_joinup_unsupported_data', [
      'sink_graph' => $graph_uri,
    ]);

    // Run the step.
    $data = [];
    $result = $plugin->execute($data);

    // Check that the step ran without any error.
    $this->assertNull($result);

    $query = <<<Query
SELECT ?graph ?subject ?predicate ?object
FROM NAMED <$graph_uri>
WHERE {
  GRAPH ?graph { ?subject ?predicate ?object }
}
Query;

    $unsupported_triples = 0;
    foreach ($this->sparql->query($query) as $triple) {
      // Only 'http://vocabulary/term' triples are unsupported from in file.
      if ($triple->subject->getUri() == 'http://vocabulary/term') {
        $unsupported_triples++;
      }
    }

    // Check that all remaining triples are supported.
    $this->assertEquals(0, $unsupported_triples);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->sparql->query("CLEAR GRAPH <" . static::TEST_GRAPH . ">;");
    parent::tearDown();
  }

}
