<?php

namespace Drupal\slp_school\Form\events;

use Drupal\autocomplete_deluxe\Plugin\Field\FieldWidget\AutocompleteDeluxeWidget;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\Entity\Node;
use Drupal\slp_interactive\InteractiveManagerInterface;
use Drupal\slp_school\Ajax\ReloadTabData;
use Drupal\slp_school\Form\SlpSchoolBaseForm;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class AddEventForm extends SlpSchoolBaseForm {

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
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * The interactive manager.
   *
   * @var \Drupal\slp_interactive\InteractiveManagerInterface
   */
  protected InteractiveManagerInterface $interactiveManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *     The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *    The current user.
   * @param \Drupal\slp_interactive\InteractiveManagerInterface $interactiveManager
   * The interactive manager.
   * @param \Drupal\Core\Database\Connection $connection
   *    The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountInterface $current_user, InteractiveManagerInterface $interactiveManager, Connection $connection) {
    parent::__construct($entityTypeManager, $current_user, $interactiveManager);
    $this->currentUserEntity = $this->entityTypeManager->getStorage('user')->load($current_user->id());
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $event = $this->getRouteMatch()->getParameter('event');
    if ($event) {
      $this->entity = $event;
    }
    else {
      $this->entity = $this->nodeStorage->create(['type' => 'event']);
    }
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('slp_interactive.interactive_manager'),
      $container->get('database'),
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
    $form['title'] = [
      '#markup' => '<div class="label">' . $this->t('Start date') . '</div class="label">',
    ];

    $start_date = new \DateTime(date('Y-m-d\TH:i'));
    $start_date = DrupalDateTime::createFromTimestamp($start_date->getTimestamp());
    if (isset($input['start_date'])) {
      $start_date = $input['start_date'];
    }
    elseif (!$this->entity->get('field_start_date')->isEmpty()) {
      $start_date = $this->entity->get('field_start_date')->value;
      $start_date = new \DateTime(date('Y-m-d\TH:i', strtotime($start_date)), new \DateTimeZone('UTC'));
      $start_date = DrupalDateTime::createFromTimestamp($start_date->getTimestamp());
    }
    $form['start_date'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'date',
      '#date_time_element' => 'time',
      '#date_time_format' => 'H',
      '#required' => TRUE,
      '#default_value' => $start_date,
      '#prefix' => '<div class="event-date-container">',
    ];

    $durations = $this->currentUserEntity->get('field_lessons_duration')->getValue();
    $options = [30 => 30, 45 => 45, 60 => 60, 90 => 90];
    if ($durations) {
      $taxonomy_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      foreach ($durations as $duration) {
        $term = $taxonomy_storage->load($duration['target_id']);
        if (!$term) {
          continue;
        }

        $options[(int) $term->getName()] = (int) $term->getName();
      }

      asort($options);
    }

    $url = Url::fromRoute('user.edit', [], ['attributes' => ['target' => '_blank']]);
    $link = Link::fromTextAndUrl($this->t('settings'), $url)->toString();
    $lesson_duration = '';
    if (isset($input['lesson_duration'])) {
      $lesson_duration = $input['lesson_duration'];
    }
    elseif (
      !$this->entity->get('field_start_date')->isEmpty() &&
      !$this->entity->get('field_end_date')->isEmpty()
    ) {
      $start_date = $this->entity->get('field_start_date')->value;
      $end_date = $this->entity->get('field_end_date')->value;
      $lesson_duration = (strtotime($end_date) - strtotime($start_date)) / 60;
    }
    $form['lesson_duration'] = [
      '#title' => $this->t('Lesson duration'),
      '#type' => 'select',
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $lesson_duration,
      '#suffix' => '</div>',
    ];

    $text = $this->t('Select the desired lesson duration in minutes. If you want to change this list you should go to @link.', ['@link' => $link]);
    $form['description'] = [
      '#markup' => '<div class="description bottom">' . $text . '</div>',
    ];
    $selection_settings = [
      'target_bundles' => ['lesson' => 'lesson'],
      'sort' => ['field' => '_none', 'direction' => 'ASC'],
      'auto_create' => false,
      'auto_create_bundle' => '',
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
    ];
    $data = serialize($selection_settings) . 'node' . 'default:node';
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
    $key_value_storage = \Drupal::service('keyvalue')->get('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }
    $route_parameters = [
      'target_type' => 'node',
      'selection_handler' => 'default:node',
      'selection_settings_key' => $selection_settings_key,
    ];

    $lesson = '';
    if (isset($input['lesson'])) {
      $lesson = $input['lesson']['textfield'];
    }
    elseif (!$this->entity->get('field_lesson')->isEmpty()) {
      $lesson = $this->entity->get('field_lesson')->getValue();
      $entities = [];
      foreach ($lesson as $item) {
        $item = Node::load($item['target_id']);
        $entities[$item->id()] = $item;
      }
      $lesson = AutocompleteDeluxeWidget::implodeEntities($entities);
    }

    $form['lesson'] = [
      '#type' => 'autocomplete_deluxe',
      '#required' => TRUE,
      '#target_type' => 'node',
      '#multiple' => FALSE,
      '#limit' => 10,
      '#default_value' => $lesson,
      '#autocomplete_deluxe_path' => Url::fromRoute('slp_school.autocomplete', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#title' => $this->t('Select lesson'),
    ];

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
    if (isset($input['students'])) {
      $students = $input['students']['textfield'];
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

    $form['students'] = [
      '#type' => 'autocomplete_deluxe',
      '#target_type' => 'user',
      '#required' => TRUE,
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

    $form['#after_build'][] = 'slp_school_after_build';
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
    $students = $form_state->getValue('students');
    if (empty($students)) {
      $form_state->setError($form['students'], $this->t('Students field is required'));
    }

    $lesson = $form_state->getValue('lesson');
    if (empty($lesson)) {
      $form_state->setError($form['lesson'], $this->t('Lesson field is required'));
    }

    $start_date = $form_state->getValue('start_date');
    if (empty($start_date)) {
      $form_state->setError($form['start_date'], $this->t('Start date field is required'));
    }

    $lesson_duration = $form_state->getValue('lesson_duration');
    $start_date->setTimezone(new \DateTimeZone('UTC'));
    $timestamp = $start_date->getTimestamp();
    $end_date = ($lesson_duration * 60) + $timestamp;
    $end_date = DrupalDateTime::createFromTimestamp($end_date);
    $end_date->setTimezone(new \DateTimeZone('UTC'));
    $id = $this->currentUser->id();
    $query = $this->connection->select('node_field_data', 'nfd');
    $query->condition('nfd.type', 'event');
    if (!$this->entity->isNew()) {
      $query->condition('nfd.nid', $this->entity->id(), '<>');
    }

    $query->join('node__field_start_date', 'nfsd', 'nfsd.entity_id = nfd.nid');
    $query->join('node__field_end_date', 'nfed', 'nfed.entity_id = nfd.nid');
    $query->where(
      'UNIX_TIMESTAMP(nfsd.field_start_date_value) < UNIX_TIMESTAMP(:start_date) AND
       UNIX_TIMESTAMP(nfed.field_end_date_value) > UNIX_TIMESTAMP(:start_date) OR
       UNIX_TIMESTAMP(nfsd.field_start_date_value) < UNIX_TIMESTAMP(:end_date) AND
       UNIX_TIMESTAMP(nfed.field_end_date_value) > UNIX_TIMESTAMP(:end_date) OR
       UNIX_TIMESTAMP(nfsd.field_start_date_value) > UNIX_TIMESTAMP(:start_date) AND
       UNIX_TIMESTAMP(nfed.field_end_date_value) < UNIX_TIMESTAMP(:end_date)OR
       UNIX_TIMESTAMP(nfsd.field_start_date_value) < UNIX_TIMESTAMP(:start_date) AND
       UNIX_TIMESTAMP(nfed.field_end_date_value) > UNIX_TIMESTAMP(:end_date)',
      [
        ':start_date' => $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        ':end_date' => $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT)]);
    $query->join('node__field_teacher', 'nft', 'nft.entity_id = nfd.nid');
    $query->join('node__field_students', 'nfs', 'nfs.entity_id = nfd.nid');
    $query->where('nft.field_teacher_target_id = :uid or nfs.field_students_target_id = :uid', [':uid' => $id]);
    $query->addField('nfd', 'nid');
    $events = $query->execute()->fetchCol();
    if (!empty($events)) {
      $form_state->setError($form['start_date'], $this->t('You have an event created in this time range.'));
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

    $lesson = $form_state->getValue('lesson');
    $students = $form_state->getValue('students');
    $message = $this->t('You successfully edit the event.');
    if ($this->entity->isNew()) {
      $message = $this->t('You successfully created the event.');
    }
    $lesson_duration = $form_state->getValue('lesson_duration');
    $start_date = $form_state->getValue('start_date');
    $start_date->setTimezone(new \DateTimeZone('UTC'));
    $timestamp = $start_date->getTimestamp();
    $end_date = ($lesson_duration * 60) + $timestamp;
    $end_date = DrupalDateTime::createFromTimestamp($end_date);
    $end_date->setTimezone(new \DateTimeZone('UTC'));
    $lesson = reset($lesson);
    $lesson = $this->nodeStorage->load($lesson['target_id']);
    $this->entity->set('title', $lesson->getTitle());
    $this->entity->set('field_lesson', $lesson->id());
    $this->entity->set('field_start_date', $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
    $this->entity->set('field_end_date', $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
    $this->entity->set('field_students', $students);
    $this->entity->set('field_teacher', $this->currentUser->id());
    $this->entity->save();
    $this->giveUsersAccessForLessons($students, [$lesson->id()], 'add');

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
