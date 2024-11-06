<?php

namespace Drupal\slp_school\Form\constructor;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\slp_school\Form\SlpSchoolBaseForm;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class AddCourseAccessForm extends SlpSchoolBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'slp_add_course_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $uuid = NULL): array {
    $form['#prefix'] = '<div class="modal-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $selection_settings = [
      'target_bundles' => ['default' => 'default'],
      'sort' => ['field' => '_none', 'direction' => 'ASC'],
      'auto_create' => false,
      'auto_create_bundle' => '',
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
    ];
    $data = serialize($selection_settings) . 'commerce_product' . 'default:commerce_product';
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
    $key_value_storage = \Drupal::service('keyvalue')->get('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }
    $route_parameters = [
      'target_type' => 'commerce_product',
      'selection_handler' => 'default:commerce_product',
      'selection_settings_key' => $selection_settings_key,
    ];
    $form['courses'] = [
      '#type' => 'autocomplete_deluxe',
      '#required' => TRUE,
      '#target_type' => 'commerce_product',
      '#multiple' => TRUE,
      '#limit' => 10,
      '#autocomplete_deluxe_path' => Url::fromRoute('slp_school.autocomplete', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#title' => $this->t('Select courses.'),
    ];

    $roles = $this->currentUser->getRoles();
    if (in_array('administrator', $roles)) {
      $form['courses_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Select courses type'),
        '#required' => TRUE,
        '#options' => [
          'basic' => $this->t('Basic', [], ['context' => 'course_type']),
          'standard' => $this->t('Standard'),
          'pro' => $this->t('Pro'),
        ],
      ];
    }

    $selection_settings = [
      'sort' => ['field' => '_none', 'direction' => 'ASC'],
      'auto_create' => false,
      'auto_create_bundle' => '',
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
    ];
    $data = serialize($selection_settings) . 'user' . 'default:user';
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
    $key_value_storage = \Drupal::service('keyvalue')->get('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }
    $route_parameters = [
      'target_type' => 'user',
      'selection_handler' => 'default:user',
      'selection_settings_key' => $selection_settings_key,
    ];

    $form['users'] = [
      '#type' => 'autocomplete_deluxe',
      '#required' => TRUE,
      '#target_type' => 'user',
      '#multiple' => TRUE,
      '#limit' => 10,
      '#autocomplete_deluxe_path' => Url::fromRoute('slp_school.autocomplete', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#title' => $this->t('Select students.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add'),
      '#name' => 'add',
      '#attributes' => ['class' => ['btn btn-primary btn-md']],
      '#prefix' => '<div class="actions-container">',
      '#ajax' => [
        'callback' => [$this, 'submitFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['delete'] = [
      '#type' => 'submit',
      '#value' => t('Delete'),
      '#name' => 'delete',
      '#attributes' => ['class' => ['btn-danger']],
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'submitFormAjax'],
        'event' => 'click',
      ],
    ];
    $form['#attached']['library'][] = 'gin/dialog';
    $form['#attached']['library'][] = 'gin/gin_base';
    $form['#attached']['library'][] = 'gin/gin_accent';
    $form['#attached']['library'][] = 'gin/autocomplete';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $courses = $form_state->getValue('courses');
    if (empty($courses)) {
      $form_state->setError($form['courses'], $this->t('Courses field is required'));
    }
    $users = $form_state->getValue('users');
    if (empty($users)) {
      $form_state->setError($form['users'], $this->t('Users field is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#drupal-modal .modal-form', $form));
    if ($form_state->hasAnyErrors()) {
      return $response;
    }
    $te = $form_state->getTriggeringElement();
    $courses = $form_state->getValue('courses');
    $courses = array_column($courses, 'target_id');
    $users = $form_state->getValue('users');
    $course_type = $form_state->getValue('courses_type') ?? 'pro';
    $action = 'added';
    if ($te['#name'] === 'delete') {
      $action = 'deleted';
    }
    $this->giveUsersAccessForCourses($users, $courses, $te['#name'], $course_type);
    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));
    $this->messenger()->addMessage($this->t('You successfully @action access to the course.', ['@action' => $this->t($action)]));
    $message = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $response->addCommand(new AfterCommand('#fh5co-page', $message));

    return $response;
  }

}
