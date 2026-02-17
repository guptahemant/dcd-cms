<?php

namespace Drupal\paper_review_access\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the "Add Review" form block for paper detail pages.
 *
 * @Block(
 *   id = "add_review_form",
 *   admin_label = @Translation("Add Review Form"),
 *   category = @Translation("DCD Reviews"),
 * )
 */
class AddReviewFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * Constructs a new AddReviewFormBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, EntityFormBuilderInterface $entity_form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity.form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'papers') {
      return [];
    }

    $uid = $this->currentUser->id();

    // Check if user already reviewed this paper.
    $existing = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'review')
      ->condition('uid', $uid)
      ->condition('field_review_paper', $node->id())
      ->count()
      ->execute();

    if ($existing > 0) {
      return [];
    }

    // Create a new review node pre-populated with the current paper.
    $review = $this->entityTypeManager
      ->getStorage('node')
      ->create([
        'type' => 'review',
        'field_review_paper' => ['target_id' => $node->id()],
      ]);

    return $this->entityFormBuilder->getForm($review, 'default');
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'papers') {
      return AccessResult::forbidden();
    }

    // Only users who can view their own reviews (members) see the form.
    // Users with 'view any review content' (leads) should not see it.
    if ($account->hasPermission('view any review content')) {
      return AccessResult::forbidden()
        ->addCacheContexts(['user.permissions', 'route']);
    }

    return AccessResult::allowedIfHasPermission($account, 'create review content')
      ->addCacheContexts(['user', 'route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return array_merge(parent::getCacheContexts(), ['user', 'route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return array_merge(parent::getCacheTags(), ['node_list:review']);
  }

}
