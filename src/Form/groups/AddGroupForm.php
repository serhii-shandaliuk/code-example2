<?php

namespace Drupal\slp_school\Form\groups;

use Drupal\autocomplete_deluxe\Plugin\Field\FieldWidget\AutocompleteDeluxeWidget;
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
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\slp_school\Ajax\ReloadTabData;
use Drupal\slp_school\SchoolManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class AddGroupForm extends FormBase {

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
   * The SLP group.
   *
   * @var EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
 */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountInterface $current_user, StateInterface $state) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->currentUserEntity = $this->entityTypeManager->getStorage('user')->load($current_user->id());
    $group = $this->getRouteMatch()->getParameter('group');
    if ($group) {
      $this->entity = $group;
    }
    else {
      $storage = $this->entityTypeManager->getStorage('slp_group');
      $this->entity = $storage->create();
    }
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('state'),
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
  public function buildForm(array $form, FormStateInterface $form_state, string $uuid = NULL): array {
    $form['#prefix'] = '<div class="modal-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $input = $form_state->getUserInput();
    $name = '';
    if (isset($input['name'])) {
      $name = $input['name'];
    }
    elseif (!$this->entity->get('label')->isEmpty()) {
      $name = $this->entity->get('label')->value;
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group name'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Fill in group name'),
      '#default_value' => $name,
    ];

    $role = $this->currentUserEntity->get('field_school_role')->value;
    if ($role === 'director') {
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
        'is_teacher' => TRUE,
      ];

      $teacher = '';
      if (isset($input['teacher'])) {
        $teacher = $input['teacher']['textfield'];
      }
      elseif (!$this->entity->get('field_teacher')->isEmpty()) {
        $teacher = $this->entity->get('field_teacher')->entity;
        $entities[$teacher->id()] = $teacher;
        $teacher = AutocompleteDeluxeWidget::implodeEntities($entities);
      }

      $form['teacher'] = [
        '#type' => 'autocomplete_deluxe',
        '#target_type' => 'user',
        '#multiple' => FALSE,
        '#required' => TRUE,
        '#limit' => 10,
        '#autocomplete_deluxe_path' => Url::fromRoute('slp_school.autocomplete_teachers', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
        '#title' => $this->t('Select teacher'),
        '#default_value' => $teacher,
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

    $students = '';
    if (isset($input['users'])) {
      $students = $input['users']['textfield'];
    }
    elseif (!$this->entity->get('field_students')->isEmpty()) {
      $students = $this->entity->get('field_students')->getValue();
      $entities = [];
      foreach ($students as $item) {
        $item = User::load($item['target_id']);
        $entities[$item->id()] = $item;
      }
      $students = AutocompleteDeluxeWidget::implodeEntities($entities);
    }

    $form['users'] = [
      '#type' => 'autocomplete_deluxe',
      '#target_type' => 'user',
      '#multiple' => TRUE,
      '#limit' => 10,
      '#autocomplete_deluxe_path' => Url::fromRoute('slp_school.autocomplete', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#title' => $this->t('Select students'),
      '#default_value' => $students,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
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
    $name = $form_state->getValue('name');
    if (empty($name)) {
      $form_state->setError($form['name'], $this->t('Name field is required'));
    }
    $teacher = $form_state->getValue('teacher');
    $role = $this->currentUserEntity->get('field_school_role')->value;
    if (empty($teacher) && $role === 'director') {
      $form_state->setError($form['teacher'], $this->t('Teacher field is required'));
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

    $teacher = $form_state->getValue('teacher');
    $uid = $this->currentUser->id();
    $this->entity->set('label', $form_state->getValue('name'));
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $message = $this->t('You successfully edit the group.');
    $users = $form_state->getValue('users');
    $message_title = 'You has been added to new group.';
    $type = 'group_access_add';
    $send_messages_for_deleted = [];
    if ($this->entity->isNew()) {
      $message = $this->t('You successfully created the group.');
      $this->entity->set('field_author', $uid);
      $send_messages_for = $users;
    }
    else {
      $loaded_students = $this->entity->get('field_students')->getValue();
      $loaded_students = array_column($loaded_students, 'target_id');
      if ($users) {
        $users_tmp = array_column($users, 'target_id');
        $send_messages_for = array_diff($users_tmp, $loaded_students);
        $send_messages_for_deleted = array_diff($loaded_students, $users_tmp);
      }
      else {
        $message_title = 'You has been deleted from the group.';
        $type = 'group_access_delete';
        $send_messages_for = $loaded_students;
      }
    }
    $nid = $this->state->get('lesson_node_id_for_user_page', SchoolManagerInterface::LESSON_NODE_ID_FOR_REDIRECT);
    $pid = SchoolManagerInterface::PARAGRAPH_GROUPS_TAB;
    if ($send_messages_for) {
      foreach ($send_messages_for as $item) {
        $message_entity = $storage->create(
          [
            'type' => 'message',
            'field_title' => $message_title,
            'field_description' => ['value' => $nid . ',' . $pid, 'format' => 'full_html'],
            'field_message_for' => ['target_id' => $item],
            'field_answer_id' => ['target_id' => $pid],
            'field_message_type' => ['value' => $type],
            'field_read' => ['value' => FALSE],
          ]
        );
        $message_entity->save();


        // Send email.
        _slp_interactive_send_message_mail($message_entity);
      }

    }


    if ($send_messages_for_deleted) {
      foreach ($send_messages_for_deleted as $item) {
        $message_entity = $storage->create(
          [
            'type' => 'message',
            'field_title' => 'You has been deleted from the group.',
            'field_description' => ['value' => $nid . ',' . $pid, 'format' => 'full_html'],
            'field_message_for' => ['target_id' => $item],
            'field_answer_id' => ['target_id' => $pid],
            'field_message_type' => ['value' => 'group_access_delete'],
            'field_read' => ['value' => FALSE],
          ]
        );
        $message_entity->save();


        // Send email.
        _slp_interactive_send_message_mail($message_entity);
      }
    }

    $this->entity->set('field_teacher', $teacher ?? $uid);
    $this->entity->set('field_students', $users);
    $this->entity->save();

    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));
    $this->messenger()->addMessage($message);
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
