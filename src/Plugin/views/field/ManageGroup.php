<?php

namespace Drupal\slp_school\Plugin\views\field;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\slp_school\SchoolManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("manage_group")
 */
class ManageGroup extends FieldPluginBase {

  /**
   * The current display.
   *
   * @var string
   *   The current display of the view.
   */
  protected $currentDisplay;

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $userStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The school manager.
   *
   * @var \Drupal\slp_school\SchoolManagerInterface
   */
  protected SchoolManagerInterface $schoolManager;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->currentDisplay = $view->current_display;
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->schoolManager = \Drupal::service('slp_school.school_manager');
    $this->userStorage = $this->entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // First check whether the field should be hidden if the value(hide_alter_empty = TRUE) /the rewrite is empty (hide_alter_empty = FALSE).
    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $build = [];
    if (isset($values->id)) {
      $gid = $values->id;
      $url = Url::fromRoute('slp_school.edit_group', ['group' => $gid]);
      if ($url->access()) {
        $build['edit_group'] = [
          '#type' => 'link',
          '#title' => [
            '#type' => 'markup',
            '#markup' => '<i class="icon-edit_with_border"></i>',
          ],
          '#url' => $url,
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-progress-type' => 'fullscreen',
            'style' => 'font-size: 24px;',
            'title' => t('Edit group'),
            'data-dialog-type' => 'modal',
          ],
        ];
      }

      $url = Url::fromRoute('slp_school.user_delete_group_popup', ['group' => $gid]);
      if ($url->access() && $this->schoolManager->isAuthorOfTheGroup($gid)) {
        $build['delete'] = [
          '#type' => 'link',
          '#title' => [
            '#type' => 'markup',
            '#markup' => '<i class="icon-trash"></i>',
          ],
          '#url' => $url,
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-progress-type' => 'fullscreen',
            'style' => 'font-size: 24px;',
            'title' => t('Delete group'),
            'data-dialog-type' => 'modal',
          ],
        ];
      }

      $build['#prefix'] = '<div class="operations-container">';
      $build['#suffix'] = '</div>';
      $build['#attached']['library'][] = 'core/jquery.form';
      $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    }

    return $build;
  }

}
