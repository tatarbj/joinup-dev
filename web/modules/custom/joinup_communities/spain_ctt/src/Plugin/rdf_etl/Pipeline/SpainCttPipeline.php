<?php

namespace Drupal\spain_ctt\Plugin\rdf_etl\Pipeline;

use Drupal\rdf_etl\Plugin\RdfEtlPipelinePluginBase;

/**
 * The data pipeline of Spanish repository.
 *
 * @RdfEtlPipeline(
 *   id = "spain",
 *   label = @Translation("Spain - Center for Technology Transfer"),
 *   steps = {
 *     "manual_upload_step",
 *     "convert_to_adms2",
 *     "remove_joinup_unsupported_data",
 *     "adms_validation",
 *   },
 * )
 */
class SpainCttPipeline extends RdfEtlPipelinePluginBase {}
