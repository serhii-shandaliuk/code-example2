<?php

namespace Drupal\slp_school\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("user_statistic_link")
 */
class UserStatisticLink extends FieldPluginBase {

  /**
   * The current display.
   *
   * @var string
   *   The current display of the view.
   */
  protected $currentDisplay;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->currentDisplay = $view->current_display;
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
    if (isset($values->uid)) {
      $uid = $values->uid;
      $build = [
        '#type' => 'link',
        '#title' => [
          '#type' => 'markup',
          '#markup' => '<i class="icon-statistics"></i>',
        ],
        '#url' => Url::fromRoute('slp_school.user_lessons', ['user' => $uid]),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-progress-type' => 'fullscreen',
          'style' => 'font-size: 24px;',
          'title' => t('Lessons statistic'),
        ],
      ];

      $build[] = [
        '#type' => 'link',
        '#title' => [
          '#type' => 'markup',
          '#markup' => '<i class="icon-dictionary"></i>',
        ],
        '#url' => Url::fromRoute('slp_school.user_vocabulary', ['user' => $uid]),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-progress-type' => 'fullscreen',
          'style' => 'font-size: 24px;',
          'title' => t('User vocabulary'),
        ],
      ];

      $build['#prefix'] = '<div class="operations-container">';
      $build['#suffix'] = '</div>';
      $build['#attached']['library'][] = 'core/jquery.form';
      $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    }

    return $build;
  }

}
