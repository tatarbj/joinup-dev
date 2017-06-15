<?php

namespace Drupal\joinup_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with the recommended community content for the current user.
 *
 * @Block(
 *   id = "entity_unpublished",
 *   admin_label = @Translation("Unpublished content of group"),
 *   context = {
 *     "og" = @ContextDefinition("entity:rdf_entity", label = @Translation("Organic group"))
 *   }
 * )
 */
class EntityUnpublishedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The community content bundle ids.
   *
   * @var array
   */
  const COMMUNITY_BUNDLES = [
    'custom_page',
    'discussion',
    'document',
    'event',
    'news',
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RecommendedContentBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The og membership manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $group = $this->getContext('og')->getContextValue();
    if (empty($group)) {
      return [];
    }

    $rows = $this->getRows($group);
    if (empty($rows)) {
      return [];
    }

    $build = [
      // The 'listing' child key is needed to avoid copying the #attributes to
      // the parent block.
      // @see \Drupal\block\BlockViewBuilder::preRender()
      'listing' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['listing', 'listing--grid', 'mdl-grid'],
        ],
      ],
    ];

    $build['listing'] += $rows;
    return $build;
  }

  /**
   * Receives the content of the groups the user is a member of.
   *
   * @param \Drupal\rdf_entity\RdfInterface $group
   *   The group entity.
   *
   * @return array
   *   An array of rows to render.
   */
  protected function getRows(RdfInterface $group) {
    $index = Index::load('unpublished');
    /** @var \Drupal\search_api\Query\QueryInterface $query */
    $query = $index->query();
    $query->addCondition('entity_bundle', self::COMMUNITY_BUNDLES, 'IN');
    $query->addCondition('entity_groups', $group->id());
    $query->sort('created', 'DESC');
    $results = $query->execute();
    $entities = $this->getResultEntities($results);
    $rows = [];

    foreach ($entities as $weight => $entity) {
      $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'view_mode_tile');
      $rows[$weight] = [
        '#type' => 'container',
        '#weight' => $weight,
        '#attributes' => [
          'class' => [
            'listing__item',
            'listing__item--tile',
            'mdl-cell',
            'mdl-cell--4-col',
          ],
        ],
        $weight => $view,
      ];
    }
    return $rows;
  }

  /**
   * Builds a renderable array for the search results.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $result
   *   The query results object.
   *
   * @return array
   *   The render array for the search results.
   */
  protected function getResultEntities(ResultSetInterface $result) {
    $results = [];
    /* @var $item \Drupal\search_api\Item\ItemInterface */
    foreach ($result->getResultItems() as $item) {
      try {
        $entity_id = $item->getOriginalObject()->getValue()->id();
        $entity = $this->getLatestRevision($entity_id);
      }
      catch (SearchApiException $e) {
        $entity = NULL;
      }
      // Search results might be stale, so we check if the entity has been
      // found in the system.
      if (!$entity) {
        continue;
      }
      if (!$entity->access('view')) {
        continue;
      }
      $results[] = $entity;
    }
    return $results;
  }

  /**
   * Loads the latest revision on an entity.
   *
   * @param int $entity_id
   *   The content id.
   *
   * @return \Drupal\node\NodeInterface
   *   The loaded node.
   */
  protected function getLatestRevision($entity_id) {
    $storage = $this->entityTypeManager->getStorage('node');
    $revision_ids = $storage->getQuery()
      ->allRevisions()
      ->condition('nid', $entity_id)
      ->sort('vid', 'DESC')
      ->range(0, 1)
      ->execute();
    if (empty($revision_ids)) {
      return NULL;
    }

    $revision_id = array_keys($revision_ids)[0];
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $storage->loadRevision($revision_id);
    return $entity;
  }

  /**
   * {@inheritdoc}
   *
   * The page should be dependent on the user's groups.
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['og_role']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list']);
  }

}
