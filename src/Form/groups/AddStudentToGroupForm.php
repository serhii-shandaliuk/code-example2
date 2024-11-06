<?php

namespace Drupal\slp_school\Form\groups;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\slp_school\Ajax\ReloadTabData;
use Drupal\slp_school\Entity\SLPGroup;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class AddStudentToGroupForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The current user.
   *
   * @var EntityInterface
   */
  protected EntityInterface $currentUserEntity;

  /**
   * The current user.
   *
   * @var EntityInterface
   */
  protected EntityInterface $selectedUser;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *     The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *    The current user.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountInterface $current_user) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->currentUserEntity = $this->entityTypeManager->getStorage('user')->load($current_user->id());
    $this->selectedUser = $this->getRouteMatch()->getParameter('user');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'slp_add_student_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL): array {
    $form['#prefix'] = '<div class="modal-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $selection_settings = [
      'sort' => ['field' => '_none', 'direction' => 'ASC'],
      'auto_create' => false,
      'auto_create_bundle' => '',
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
    ];
    $data = serialize($selection_settings) . 'slp_group' . 'default:slp_group';
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
    $key_value_storage = \Drupal::service('keyvalue')->get('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }
    $route_parameters = [
      'target_type' => 'slp_group',
      'selection_handler' => 'default:slp_group',
      'selection_settings_key' => $selection_settings_key,
      'node' => $this->selectedUser->id(),
    ];

    $form['groups'] = [
      '#type' => 'autocomplete_deluxe',
      '#target_type' => 'slp_group',
      '#multiple' => TRUE,
      '#limit' => 10,
      '#autocomplete_deluxe_path' => Url::fromRoute('slp_school.autocomplete_users', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#title' => $this->t('Select group'),
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
    $lessons = $form_state->getValue('groups');
    if (empty($lessons)) {
      $form_state->setError($form['groups'], $this->t('Groups field is required'));
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

    $groups = $form_state->getValue('groups');
    if ($groups) {
      foreach ($groups as $group) {
        $group_loaded = SLPGroup::load($group['target_id']);
        if (!$group_loaded) {
          continue;
        }

        $students = $group_loaded->get('field_students')->getValue();
        $students[] = ['target_id' => $this->selectedUser->id()];
        $group_loaded->set('field_students', $students);
        $group_loaded->save();
      }
    }

    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));
    $this->messenger()->addMessage($this->t('You successfully added a student to the group.'));
    $message = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $response->addCommand(new ReloadTabData());
    $build['#attached']['library'][] = 'slp_school/reload_tab_data';
    $response->setAttachments($build['#attached']);
    $response->addCommand(new AfterCommand('#fh5co-page', $message));

    return $response;
  }

}
