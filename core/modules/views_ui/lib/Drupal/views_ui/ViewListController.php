<?php

/**
 * @file
 * Contains \Drupal\views_ui\ViewListController.
 */

namespace Drupal\views_ui;

use Drupal\Component\Utility\String;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityListController;
use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Views.
 */
class ViewListController extends ConfigEntityListController implements EntityControllerInterface {

  /**
   * The views display plugin manager to use.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $displayManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorageController($entity_type->id()),
      $container->get('plugin.manager.views.display')
    );
  }

  /**
   * Constructs a new EntityListController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $storage.
   *   The entity storage controller class.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $display_manager
   *   The views display plugin manager to use.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageControllerInterface $storage, PluginManagerInterface $display_manager) {
    parent::__construct($entity_type, $storage);

    $this->displayManager = $display_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = array(
      'enabled' => array(),
      'disabled' => array(),
    );
    foreach (parent::load() as $entity) {
      if ($entity->status()) {
        $entities['enabled'][] = $entity;
      }
      else {
        $entities['disabled'][] = $entity;
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $view) {
    $row = parent::buildRow($view);
    return array(
      'data' => array(
        'view_name' => array(
          'data' => array(
            '#theme' => 'views_ui_view_info',
            '#view' => $view,
            '#displays' => $this->getDisplaysList($view)
          ),
        ),
        'description' => array(
          'data' => array(
            '#markup' => String::checkPlain($view->get('description')),
          ),
          'class' => array('views-table-filter-text-source'),
        ),
        'tag' => $view->get('tag'),
        'path' => implode(', ', $this->getDisplayPaths($view)),
        'operations' => $row['operations'],
      ),
      'title' => $this->t('Machine name: @name', array('@name' => $view->id())),
      'class' => array($view->status() ? 'views-ui-list-enabled' : 'views-ui-list-disabled'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return array(
      'view_name' => array(
        'data' => $this->t('View name'),
        'class' => array('views-ui-name'),
      ),
      'description' => array(
        'data' => $this->t('Description'),
        'class' => array('views-ui-description'),
      ),
      'tag' => array(
        'data' => $this->t('Tag'),
        'class' => array('views-ui-tag'),
      ),
      'path' => array(
        'data' => $this->t('Path'),
        'class' => array('views-ui-path'),
      ),
      'operations' => array(
        'data' => $this->t('Operations'),
        'class' => array('views-ui-operations'),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);

    if ($entity->hasLinkTemplate('clone')) {
      $operations['clone'] = array(
        'title' => $this->t('Clone'),
        'weight' => 15,
      ) + $entity->urlInfo('clone');
    }

    // Add AJAX functionality to enable/disable operations.
    foreach (array('enable', 'disable') as $op) {
      if (isset($operations[$op])) {
        $operations[$op]['route_name'] = "views_ui.$op";
        $operations[$op]['route_parameters'] = array('view' => $entity->id());

        // Enable and disable operations should use AJAX.
        $operations[$op]['attributes']['class'][] = 'use-ajax';
      }
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entities = $this->load();
    $list['#type'] = 'container';
    $list['#attributes']['id'] = 'views-entity-list';

    $list['#attached']['css'] = ViewFormControllerBase::getAdminCSS();
    $list['#attached']['library'][] = array('core', 'drupal.ajax');
    $list['#attached']['library'][] = array('views_ui', 'views_ui.listing');

    $form['filters'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array('table-filter', 'js-show'),
      ),
    );

    $list['filters']['text'] = array(
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#size' => 30,
      '#placeholder' => $this->t('Enter view name'),
      '#attributes' => array(
        'class' => array('views-filter-text'),
        'data-table' => '.views-listing-table',
        'autocomplete' => 'off',
        'title' => $this->t('Enter a part of the view name or description to filter by.'),
      ),
    );

    $list['enabled']['heading']['#markup'] = '<h2>' . $this->t('Enabled') . '</h2>';
    $list['disabled']['heading']['#markup'] = '<h2>' . $this->t('Disabled') . '</h2>';
    foreach (array('enabled', 'disabled') as $status) {
      $list[$status]['#type'] = 'container';
      $list[$status]['#attributes'] = array('class' => array('views-list-section', $status));
      $list[$status]['table'] = array(
        '#theme' => 'table',
        '#attributes' => array(
          'class' => array('views-listing-table'),
        ),
        '#header' => $this->buildHeader(),
        '#rows' => array(),
      );
      foreach ($entities[$status] as $entity) {
        $list[$status]['table']['#rows'][$entity->id()] = $this->buildRow($entity);
      }
    }
    // @todo Use a placeholder for the entity label if this is abstracted to
    // other entity types.
    $list['enabled']['table']['#empty'] = $this->t('There are no enabled views.');
    $list['disabled']['table']['#empty'] = $this->t('There are no disabled views.');

    return $list;
  }

  /**
   * Gets a list of displays included in the view.
   *
   * @param \Drupal\Core\Entity\EntityInterface $view
   *   The view entity instance to get a list of displays for.
   *
   * @return array
   *   An array of display types that this view includes.
   */
  protected function getDisplaysList(EntityInterface $view) {
    $displays = array();
    foreach ($view->get('display') as $display) {
      $definition = $this->displayManager->getDefinition($display['display_plugin']);
      if (!empty($definition['admin'])) {
        $displays[$definition['admin']] = TRUE;
      }
    }

    ksort($displays);
    return array_keys($displays);
  }

  /**
   * Gets a list of paths assigned to the view.
   *
   * @param \Drupal\Core\Entity\EntityInterface $view
   *   The view entity.
   *
   * @return array
   *   An array of paths for this view.
   */
  protected function getDisplayPaths(EntityInterface $view) {
    $all_paths = array();
    $executable = $view->getExecutable();
    $executable->initDisplay();
    foreach ($executable->displayHandlers as $display) {
      if ($display->hasPath()) {
        $path = $display->getPath();
        if ($view->status() && strpos($path, '%') === FALSE) {
          $all_paths[] = l('/' . $path, $path);
        }
        else {
          $all_paths[] = String::checkPlain('/' . $path);
        }
      }
    }
    return array_unique($all_paths);
  }

}
