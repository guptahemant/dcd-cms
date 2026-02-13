<?php

namespace Drupal\paper_review_access\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
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

  protected RouteMatchInterface $routeMatch;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
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

    $uid = \Drupal::currentUser()->id();

    // Check if user already reviewed this paper.
    $existing = \Drupal::entityTypeManager()
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
    $review = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->create([
        'type' => 'review',
        'field_review_paper' => ['target_id' => $node->id()],
      ]);

    $form = \Drupal::service('entity.form_builder')->getForm($review, 'default');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'papers') {
      return AccessResult::forbidden();
    }

    // Only members see the form, not leads.
    if (in_array('track_team_lead', $account->getRoles(), TRUE)) {
      return AccessResult::forbidden()
        ->addCacheContexts(['user.roles', 'route']);
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

  public function getCacheTags() {
    return array_merge(parent::getCacheTags(), ['node_list:review']);
  }

}
