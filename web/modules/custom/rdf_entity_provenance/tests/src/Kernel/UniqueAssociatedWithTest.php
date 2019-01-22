<?php

namespace Drupal\Tests\rdf_entity\Kernel;

use Drupal\rdf_entity\Entity\Rdf;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Provides unit testing the provenance_associated_with constraint.
 *
 * @group rdf_entity
 */
class UniqueAssociatedWithTest extends RdfKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'rdf_entity_provenance',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['rdf_entity_provenance']);
  }

  /**
   * Tests the unique field group constraint.
   */
  public function testUniqueAssociatedWith() {
    $first_entity = Rdf::create([
      'rid' => 'dummy',
      'id' => 'http://example.com/1',
      'label' => 'Foo',
    ]);
    $first_entity->save();

    $second_entity = Rdf::create([
      'rid' => 'dummy',
      'id' => 'http://example.com/2',
      'label' => 'Foo',
    ]);
    $second_entity->save();

    Rdf::create([
      'rid' => 'provenance_activity',
      'provenance_enabled' => TRUE,
      'provenance_entity' => $first_entity->id(),
      'provenance_associated_with' => 'http://example.com/parent/1',
    ])->save();

    $entity_that_passes = Rdf::create([
      'rid' => 'provenance_activity',
      'provenance_enabled' => TRUE,
      'provenance_entity' => $second_entity->id(),
      'provenance_associated_with' => 'http://example.com/parent/1',
    ]);
    $entity_that_passes->validate();

    $entity_that_fails = Rdf::create([
      'rid' => 'provenance_activity',
      'provenance_enabled' => TRUE,
      'provenance_entity' => $first_entity->id(),
      'provenance_associated_with' => 'http://example.com/parent/2',
    ]);
    $this->setExpectedException(InvalidArgumentException::class);
    $entity_that_fails->validate();
  }

}
