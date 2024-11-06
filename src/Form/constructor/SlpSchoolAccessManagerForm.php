<?php

namespace Drupal\slp_school\Form\constructor;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\slp_school\SchoolManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class SlpSchoolAccessManagerForm extends FormBase {

  /**
   * The school manager.
   *
   * @var \Drupal\slp_school\SchoolManagerInterface
   */
  protected SchoolManagerInterface $schoolManager;

  /**
   * The form constructor.
   *
   * @param \Drupal\slp_school\SchoolManagerInterface $school_manager
   *    The school manager.
   */
  public function __construct(SchoolManagerInterface $school_manager) {
    $this->schoolManager = $school_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('slp_school.school_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'slp_school_access_manager_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $uuid = NULL): array {
    _slp_school_add_school($form);

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
      'type' => 'teachers',
    ];

    $options = [
      'teacher' => $this->t('Teacher'),
      'author' => $this->t('Author'),
      'director' => $this->t('Director'),
    ];
    $form['field_school_role'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Select the role'),
      '#empty_option' => '- ' . $this->t('Select the role') . ' -',
      '#required' => TRUE,
    ];

    $form['users'] = [
      '#type' => 'autocomplete_deluxe',
      '#required' => TRUE,
      '#target_type' => 'user',
      '#multiple' => TRUE,
      '#limit' => 10,
      '#autocomplete_deluxe_path' => Url::fromRoute('autocomplete_deluxe.autocomplete', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#title' => $this->t('Select users.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add access'),
      '#name' => 'add',
      '#attributes' => ['class' => ['btn btn-primary btn-md']],
      '#prefix' => '<div class="actions-container">',
      '#submit' => [[$this, 'submitForm']],
    ];

    $form['revoke'] = [
      '#type' => 'submit',
      '#value' => t('Revoke access'),
      '#name' => 'revoke',
      '#attributes' => ['class' => ['btn-danger']],
      '#suffix' => '</div>',
      '#submit' => [[$this, 'submitForm']],
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
    $users = $form_state->getValue('users');
    if (empty($users)) {
      $form_state->setError($form['users'], $this->t('Users field is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $users = $form_state->getValue('users');
    $role = $form_state->getValue('field_school_role');
    $te = $form_state->getTriggeringElement();
    foreach ($users as $user) {
      if ($te['#name'] === 'revoke') {
        $this->schoolManager->revokeTeacherAccess($user['target_id'], $role, FALSE);
      }
      else {
        $school_name = $form_state->getValue('school_name') ?? NULL;
        $this->schoolManager->giveTeacherAccess($user['target_id'], $role, $school_name, '+1 month');
      }
    }
  }

}
