<?php

declare(strict_types = 1);

namespace Drupal\rdf_etl;

/**
 * Class representing a state of a pipeline.
 */
class RdfEtlState {

  /**
   * The pipeline plugin ID.
   *
   * @var string
   */
  protected $pipeline;

  /**
   * The state sequence.
   *
   * @var int
   */
  protected $sequence;

  /**
   * Creates a new state object.
   *
   * @param string $pipeline
   *   The pipeline plugin id.
   * @param int $sequence
   *   The sequence of the pipeline.
   */
  public function __construct(string $pipeline, int $sequence) {
    $this->pipeline = $pipeline;
    $this->sequence = $sequence;
  }

  /**
   * Returns the persisted position within the pipeline.
   *
   * @return int
   *   The current position of within the pipeline.
   */
  public function sequence(): int {
    return (int) $this->sequence;
  }

  /**
   * Returns the persisted pipeline id.
   *
   * @return string
   *   The plugin id of the pipeline.
   */
  public function getPipelineId(): String {
    return $this->pipeline;
  }

}