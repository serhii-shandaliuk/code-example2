<?php

namespace Drupal\slp_school;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\config_entity_cloner\Service\ConfigEntityCloner;
use Drupal\config_entity_cloner\Tools\Batch;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;
use Drupal\zoomapi\Plugin\ApiTools\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class InteractiveManager
 *
 * @package Drupal\slp_school
 */
class SchoolManager implements SchoolManagerInterface {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The logger chanel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The user storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $userStorage;

  /**
   * The taxonomy vocabulary storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $vocabularyStorage;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * The product storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $productStorage;

  /**
   * EntityConfig cloner.
   *
   * @var \Drupal\config_entity_cloner\Service\ConfigEntityCloner
   */
  protected $cloner;

  /**
   * The Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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
   * The zoom client.
   *
   * @var Client
   */
  protected Client $zoomClient;

  /**
   * InteractiveManager Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\config_entity_cloner\Service\ConfigEntityCloner $cloner
   *   The cloner.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
 */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger, LoggerChannelFactoryInterface $logger_factory, ConfigEntityCloner $cloner, ConfigFactoryInterface $configFactory, StateInterface $state, AccountInterface $current_user, Client $zoom_client) {
    $this->entityTypeManager = $entityTypeManager;
    $this->userStorage = $entityTypeManager->getStorage('user');
    $this->vocabularyStorage = $entityTypeManager->getStorage('taxonomy_vocabulary');
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->productStorage = $entityTypeManager->getStorage('commerce_product');
    $this->messenger = $messenger;
    $this->logger = $logger_factory->get('slp_school');
    $this->cloner = $cloner;
    $this->configFactory = $configFactory;
    $this->state = $state;
    $this->currentUser = $current_user;
    $this->currentUserEntity = $this->userStorage->load($current_user->id());
    $this->zoomClient = $zoom_client;
  }

  /**
   * {@inheritdoc}
   */
  public function revokeTeacherAccess(int $uid = 0, ?string $role = 'teacher', bool $with_references = TRUE): void {
    try {
      $user = $this->userStorage->load($uid);
      foreach (static::ACCESS_ROLES as $access_role) {
        $user->removeRole($access_role);
      }
      if ($role !== 'student') {
        $user->addRole(static::REVOKE_ROLES[$role]);
      }
      else {
        $school_storage = $this->entityTypeManager->getStorage('slp_school');
        $school_entity = $school_storage->loadByProperties(['field_author' => $this->currentUser->id()]);
        if ($school_entity) {
          $school_entity = reset($school_entity);
          $teachers = $school_entity->get('field_teachers')->getValue();
          $teachers = array_column($teachers, 'target_id');
          $teacher = array_search($uid, $teachers);
          unset($teachers[$teacher]);
          $school_entity->set('field_teachers', array_unique($teachers));
          $school_entity->save();
        }
      }

      $message = $this->t('Access for @user has been revoked.', ['@user' => $user->getDisplayName()]);
      $this->messenger->addMessage((string) $message);
      $user->set('field_expired', TRUE);
      $user->save();

      $students = $user->get('field_students')->getValue();
      if ($students && $with_references) {
        foreach ($students as $student) {
          $student_entity = $this->userStorage->load($student['target_id']);
          $role = $student_entity->get('field_school_role')->value;
          $this->revokeTeacherAccess($student['target_id'], $role);
        }
      }

      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
      $nid = $this->state->get('lesson_node_id_for_user_page', SchoolManagerInterface::LESSON_NODE_ID_FOR_REDIRECT);
      $message_entity = $paragraph_storage->create(
        [
          'type' => 'message',
          'field_title' => 'Access has been revoked',
          'field_description' => ['value' => $nid, 'format' => 'full_html'],
          'field_message_for' => ['target_id' => $uid],
          'field_message_type' => ['value' => 'user_access_revoke'],
        ]
      );
      $message_entity->save();

      // Send email.
      _slp_interactive_send_message_mail($message_entity);
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
      $this->logger->error($e->getMessage());
    }

  }

  /**
   * {@inheritdoc}
   */
  public function giveTeacherAccess(int $uid = 0, ?string $role = 'teacher', $school = NULL, ?string $time = '+1 day', ?string $today = NULL, bool $save_due_date = FALSE): void {
    try {
      /** @var User $user */
      [$role, $plan] = array_pad(explode(';', $role), 2, NULL);

      if ($role === 'school') {
        $role = 'director';
      }
      $user = $this->userStorage->load($uid);
      foreach (static::ACCESS_ROLES as $access_role) {
        $user->removeRole($access_role);
      }
      $user->removeRole(static::REVOKE_ROLES[$role] ?? '');
      $user->addRole(static::ACCESS_ROLES[$role] ?? '');
      if (!$save_due_date) {
        if (!$today) {
          $today = date('Y-m-d');
          if (!$user->get('field_due_date')->isEmpty()) {
            if (strtotime($today) < strtotime($user->get('field_due_date')->value)) {
              $today = $user->get('field_due_date')->value;
            }
          }
        }
        $date = date('Y-m-d', strtotime($time, strtotime($today)));
        $user->set('field_due_date', $date);
      }
      else {
        $school_storage = $this->entityTypeManager->getStorage('slp_school');
        $school_entity = $school_storage->loadByProperties(['field_author' => $this->currentUser->id()]);
        if ($school_entity) {
          $school_entity = reset($school_entity);
          $teachers = $school_entity->get('field_teachers')->getValue();
          $teachers = array_column($teachers, 'target_id');
          if ($role !== 'student') {
            $teachers[] = $uid;
          }
          else {
            $teacher = array_search($uid, $teachers);
            unset($teachers[$teacher]);
          }
          $school_entity->set('field_teachers', array_unique($teachers));
          $school_entity->save();
        }

      }

      $user->set('field_expired', FALSE);
      $user->set('field_school_role', $role);
      if ($plan) {
        $user->set('field_subscription_type', $plan);
      }
      $message = $this->t('Access for @user has been added.', ['@user' => $user->getDisplayName()]);
      $this->messenger->addMessage((string) $message);
      $school_value = $user->get('field_school')->target_id;
      if ($school_value) {
        $user->set('field_school', $school_value);
      }
      $user->save();
      if ($save_due_date) {
        return;
      }

      if ($role === 'student') {
        return;
      }

      if ($user->get('field_zoom_id')->isEmpty()) {
        try {
          $user_data = [
            'action' => 'create',
            'user_info' => [
              'email' => $user->getEmail(),
              'display_name' => $user->getDisplayName(),
              'type' => 1,
            ]
          ];

          // Make the POST request to the zoom api.
          $zoom_request = $this->zoomClient->post('users', ['json' => $user_data]);
          if (isset($zoom_request['id'])) {
            $user->set('field_zoom_id', $zoom_request['id']);
            $user->save();
          }

          $this->messenger->addStatus(t('This user was successfully created in Zoom.'));
        }
        catch (RequestException $exception) {
          // Zoom api already logs errors, but you could log more.
          $this->messenger->addWarning(t('This user could not be created in Zoom.'));
        }
      }

      $school_storage = $this->entityTypeManager->getStorage('slp_school');
      if ($role === 'director') {
        $school_entity = $school_storage->loadByProperties(['field_author' => $user->id()]);
        if (empty($school_entity)) {
          $school_entity = $school_storage->create(
            [
              'label' => $school ?? $this->currentUser->getDisplayName() . ' school',
              'field_author' => $user->id(),
              'bundle' => 'default',
            ]
          );

          $school_entity->save();
        }
        else {
          $school_entity = reset($school_entity);
        }

        $user->set('field_school', $school_entity->id());
      }

      $vid = 'vocabulary_' . $uid;
      $vocabulary = $this->vocabularyStorage->load($vid);
      $name = 'Vocabulary for ' . $uid;
      $main_vocabulary = $this->vocabularyStorage->load('vocabulary');

      if (!$vocabulary) {
        // Clone fields operations.
        $newEntity = $this->cloner->duplicateEntity($name, $vid, $main_vocabulary);
        $context = [];
        $batch = (new Batch('Clone config entity...'))
          ->setInitMessage('Start')
          ->setProgressMessage('Processed @current out of @total.')
          ->setErrorMessage('An error occurred during processing')
          ->addCommonBatchData('newEntity',
            [
              'type' => $newEntity->getEntityTypeId(),
              'id' => $newEntity->id(),
              'label' => $newEntity->label(),
            ])
          ->addCommonBatchData('originalEntity',
            [
              'type' => $main_vocabulary->getEntityTypeId(),
              'id' => $main_vocabulary->id(),
              'label' => $main_vocabulary->label(),
            ]);
        foreach ($this->cloner->getListOfProcess($newEntity, $main_vocabulary) as $process) {
          ConfigEntityCloner::doCloneProcess(['processId' => $process->getId()], $context, $batch);
        }
      }

      // Clone first course for user on first access give.
      $product_query = $this->productStorage->getQuery();
      $product_query->accessCheck();
      $product_query->condition('type', 'default');
      $product_query->condition('uid', $uid);
      $courses = $product_query->execute();
      if (empty($courses)) {
        if ($role === 'teacher') {
          $course = $this->productStorage->create(
            [
              'type' => 'default',
              'title' => $user->getDisplayName() . ' course',
              'uid' => $uid,
            ]
          );
          $course->save();
          $pid = $course->id();
        }
        else {
          $pid_clone = $this->state->get('course_product_id_for_clone', static::COURSE_PRODUCT_ID_FOR_CLONE);
          $product = $this->productStorage->load($pid_clone);
          $pid = $this->cloneEntity($product, $user);
        }
      }
      else {
        $pid = reset($courses);
      }

      // Clone first lesson for user on first access give.
      $node_query = $this->nodeStorage->getQuery();
      $node_query->accessCheck();
      $node_query->condition('type', 'lesson');
      $node_query->condition('uid', $uid);
      $lessons = $node_query->execute();
      if (empty($lessons)) {
        $lid_clone = $this->state->get('lesson_node_id_for_clone', static::LESSON_NODE_ID_FOR_CLONE);
        $entity = $this->nodeStorage->load($lid_clone);
        $additional_settings = [];
        if ($pid) {
          $additional_settings = ['field_course' => ['target_id' => $pid], 'uid' => $uid];
        }
        $this->cloneEntity($entity, $user, $additional_settings);
      }

      $students = $this->getActiveStudents($uid);
      if ($students && $role && $role !== 'student') {
        foreach ($students as $student) {
          $student_entity = $this->userStorage->load($student);
          $role = $student_entity->get('field_school_role')->value;
          $this->giveTeacherAccess($student, $role, $school, $time, $today, $save_due_date);
        }
      }

      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
      $nid = $this->state->get('lesson_node_id_for_user_page', SchoolManagerInterface::LESSON_NODE_ID_FOR_REDIRECT);
      $message_entity = $paragraph_storage->create(
        [
          'type' => 'message',
          'field_title' => 'Access has been added',
          'field_description' => ['value' => $nid, 'format' => 'full_html'],
          'field_message_for' => ['target_id' => $uid],
          'field_message_type' => ['value' => 'user_access_grand'],
        ]
      );
      $message_entity->save();

      // Send email.
      _slp_interactive_send_message_mail($message_entity);
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
      $this->logger->error($e->getMessage());
    }

  }

  /**
   * {@inheritdoc}
   */
  public function cloneEntity($original_entity, User $user, array $additional_settings = []): int {
    // Clone the node using the awesome createDuplicate() core function.
    /** @var \Drupal\node\Entity\Node $new_node */
    $new_node = $original_entity->createDuplicate();
    $new_node->set('uid', $user->id());
    $new_node->set('created', time());
    $new_node->set('changed', time());
    if ($original_entity->getEntityTypeId() === 'node') {
      $new_node->set('revision_timestamp', time());
    }

    // Get default status value of node bundle.
    $default_bundle_status = $this->entityTypeManager->getStorage($original_entity->getEntityTypeId())->create(['type' => $new_node->bundle()])->get('status')->value;

    // Clone all translations of a node.
    foreach ($new_node->getTranslationLanguages() as $langcode => $language) {
      /** @var \Drupal\node\Entity\Node $translated_node */
      $translated_node = $new_node->getTranslation($langcode);
      $translated_node = $this->cloneParagraphs($translated_node, $user);
      if ($additional_settings) {
        foreach ($additional_settings as $field => $additional_setting) {
          $translated_node->set($field, $additional_setting);
        }
      }
      // Unset excluded fields.
      $config_name = 'exclude.node.' . $translated_node->bundle();
      if ($exclude_fields = $this->getConfigSettings($config_name)) {
        foreach ($exclude_fields as $field) {
          unset($translated_node->{$field});
        }
      }

      $prepend_text = "";
      $title_prepend_config = $this->getConfigSettings('text_to_prepend_to_title');
      if (!empty($title_prepend_config)) {
        $prepend_text = $title_prepend_config . " ";
      }
      $clone_status_config = $this->getConfigSettings('clone_status');
      if (!$clone_status_config) {
        $key = $translated_node->getEntityType()->getKey('published');
        $translated_node->set($key, $default_bundle_status);
      }

      $translated_node->setTitle($this->t('@prepend_text@title',
        [
          '@prepend_text' => $prepend_text,
          '@title' => $translated_node->getTitle(),
        ],
        [
          'langcode' => $langcode,
        ]
      )
      );
    }
    $new_node->save();

    return $new_node->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getSlpGroups(int $uid = NULL): array {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $storage = $this->entityTypeManager->getStorage('slp_group');
    $user = $this->userStorage->load($uid);
    if ($user->get('field_school_role')->value === 'student') {
      $new_query = $storage->getQuery()->accessCheck(FALSE);
      $new_query->condition('field_students', $uid);

      return $new_query->execute();
    }
    $new_query = $storage->getQuery()->accessCheck(FALSE);
    $new_query->condition('field_author', $uid);
    $groups = $new_query->execute();

    $new_query = $storage->getQuery()->accessCheck(FALSE);
    $new_query->condition('field_teacher', $uid);
    $groups = array_merge($groups, $new_query->execute());
    if ($user->get('field_school_role')->value === 'director') {
      $school_storage = $this->entityTypeManager->getStorage('slp_school');
      $new_query = $school_storage->getQuery()->accessCheck(FALSE);
      $new_query->condition('field_author', $uid);
      $school = $new_query->execute();
      if ($school) {
        $school = reset($school);
        $school = $school_storage->load($school);
        $teachers = $school->get('field_teachers')->getValue();
        if ($teachers) {
          foreach ($teachers as $teacher) {
            $new_query = $storage->getQuery()->accessCheck(FALSE);
            $new_query->condition('field_teacher', $teacher['target_id']);
            $groups = array_merge($groups, $new_query->execute());
          }
        }
      }
    }

    return array_unique($groups);
  }

  /**
   * {@inheritdoc}
   */
  public function getTeachers(int $uid = NULL): array {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $teachers = [];
    $user = $this->userStorage->load($uid);
    if ($user->get('field_school_role')->value === 'director') {
      $school_storage = $this->entityTypeManager->getStorage('slp_school');
      $school_entity = $school_storage->loadByProperties(['field_author' => $uid]);
      if ($school_entity) {
        $school_entity = reset($school_entity);
        $teachers = $school_entity->get('field_teachers')->getValue();
        $teachers = array_column($teachers, 'target_id');
        $key = array_search($uid, $teachers);
        if ($key) {
          unset($teachers[$key]);
        }
      }
    }

    return array_unique($teachers);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveTeachers(int $uid = NULL): array {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $teachers = $this->getTeachers($uid);
    if (!$teachers) {
      return [];
    }

    foreach ($teachers as $key => $teacher) {
      $teacher_entity = $this->userStorage->load($teacher);
      if (!$teacher_entity) {
        continue;
      }
      if ($teacher_entity->get('field_expired')->value) {
        unset($teachers[$key]);
      }
    }

    return array_unique($teachers);
  }

  /**
   * {@inheritdoc}
   */
  public function getStudents(int $uid = NULL): array {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $students_output = [];
    $storage = $this->entityTypeManager->getStorage('slp_group');
    $new_query = $storage->getQuery()->accessCheck(FALSE);
    $new_query->condition('field_author', $uid);
    $groups = $new_query->execute();
    $new_query = $storage->getQuery()->accessCheck(FALSE);
    $new_query->condition('field_teacher', $uid);
    $groups += $new_query->execute();
    if ($groups) {
      foreach ($groups as $group) {
        $loaded = $storage->load($group);
        $students = $loaded->get('field_students')->getValue();
        $students_output = array_merge($students_output, array_column($students, 'target_id'));
      }
    }

    $user = $this->userStorage->load($uid);
    $students = $user->get('field_students')->getValue();
    $students_output = array_merge($students_output, array_column($students, 'target_id'));
    $key = array_search($uid, $students_output);
    if ($key) {
      unset($students_output[$key]);
    }

    return array_unique($students_output);
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveStudents(int $uid = NULL): array {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $students = $this->getStudents($uid);
    if (!$students) {
      return [];
    }

    foreach ($students as $key => $student) {
      $student_entity = $this->userStorage->load($student);
      if (!$student_entity) {
        continue;
      }
      if ($student_entity->get('field_expired')->value) {
        unset($students[$key]);
      }
    }

    return array_unique($students);
  }

  /**
   * {@inheritdoc}
   */
  public function getLessons(int $uid = NULL): array {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $lessons = [];
    $teachers = $this->getActiveTeachers($uid);
    $teachers[] = $uid;
    if ($teachers) {
      $storage = $this->entityTypeManager->getStorage('node');
      $new_query = $storage->getQuery()->accessCheck(FALSE);
      $new_query->condition('uid', $teachers, 'IN');
      $lessons = $new_query->execute();
    }

    return array_unique($lessons);
  }

  /**
   * {@inheritdoc}
   */
  public function getCourses(int $uid = NULL): array {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $courses = [];
    $teachers = $this->getActiveTeachers($uid);
    $teachers[] = $uid;
    if ($teachers) {
      $storage = $this->entityTypeManager->getStorage('commerce_product');
      $new_query = $storage->getQuery()->accessCheck(FALSE);
      $new_query->condition('uid', $teachers, 'IN');
      $courses = $new_query->execute();
    }

    return array_unique($courses);
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(int $uid = NULL): array {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $node_query = $this->nodeStorage->getQuery();
    $node_query->accessCheck();

    $group = $node_query
      ->orConditionGroup()
      ->condition('field_teacher', $uid)
      ->condition('field_students', $uid);
    $node_query->condition($group);

    $node_query->condition('type', 'event');
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof Node) {
      $node_query->condition('field_lesson', $node->id());
    }
    $events = $node_query->execute();

    return array_unique($events);
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthorOfTheStudent(int $uid = NULL): bool {
    if (!$uid) {
      $uid = $this->currentUser->id();
    }

    $is_author = TRUE;
    $user = $this->userStorage->load($uid);
    if ($user->get('field_school_role')->value !== 'director') {
      $students = $user->get('field_students')->getValue();
      $students = array_column($students, 'target_id');
      $is_author = in_array($uid, $students);
    }

    return $is_author;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthorOfTheGroup(int $gid): bool {
    $is_author = TRUE;
    if ($this->currentUserEntity->get('field_school_role')->value !== 'director') {
      $storage = $this->entityTypeManager->getStorage('slp_group');
      $new_query = $storage->getQuery()->accessCheck(FALSE);
      $new_query->condition('field_author', $this->currentUser->id());
      $groups = $new_query->execute();
      if (!isset($groups[$gid])) {
        $is_author = FALSE;
      }
    }

    return $is_author;
  }

  /**
   * {@inheritdoc}
   */
  public function getSchoolRoles(): array {
    $roles = static::SCHOOL_ROLES;
    foreach ($roles as &$role) {
      $role = $this->t($role);
    }

    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariationBuild(ProductVariation $variation): array {
    $variation_price = $variation->getPrice();
    $variation_price_number = $variation_price->getNumber();
    $variation_price_currency = $variation_price->getCurrencyCode();

    return [
      '#theme' => 'variation_add_to_cart_formatter',
      '#variation' => $variation,
      '#product_id' => $variation->getProductId(),
      '#variation_id' => $variation->id(),
      '#show_title' => TRUE,
      '#title' => $variation->getTitle(),
      '#show_price' => TRUE,
      '#price_number' => $variation_price_number,
      '#price_format' => 2,
      '#show_currency' => TRUE,
      '#price_currency' => $variation_price_currency,
      '#show_quantity' => FALSE ? 'number' : 'hidden',
      '#destination' => \Drupal::request()->getRequestUri(),
      '#attributes' => ['data-button-label' => $this->t('Card payment (Apple Pay, Google Pay)')],
    ];
  }

  /**
   * Clone the paragraphs of a node.
   *
   * If we do not clone the paragraphs attached to the node, the linked
   * paragraphs would be linked to two nodes which is not ideal.
   *
   * @param EntityInterface $node
   *   The node to clone.
   *
   * @param User $user
   *   User entity.
   *
   * @return EntityInterface
   *   The node with cloned paragraph fields.
   */
  protected function cloneParagraphs(EntityInterface $node, User $user): EntityInterface {
    foreach ($node->getFieldDefinitions() as $field_definition) {
      $field_storage_definition = $field_definition->getFieldStorageDefinition();
      $field_settings = $field_storage_definition->getSettings();
      $field_name = $field_storage_definition->getName();
      if (isset($field_settings['target_type']) && ($field_settings['target_type'] == 'paragraph' || $field_settings['target_type'] == 'commerce_product_variation')) {
        if (!$node->get($field_name)->isEmpty()) {
          foreach ($node->get($field_name) as $value) {
            if ($value->entity) {
              $entity = $value->entity->createDuplicate();
              if ($field_settings['target_type'] == 'commerce_product_variation') {
                $sku = $entity->getSku() . ' ' . $user->getAccountName();
                $entity->setSku($sku);
                $entity->set('product_id', $node->id());
              }
              $value->entity = $entity;
              foreach ($value->entity->getFieldDefinitions() as $field_definition) {
                $field_storage_definition = $field_definition->getFieldStorageDefinition();
                $pfield_name = $field_storage_definition->getName();

                // Check whether this field is excluded and if so unset.
                if ($this->excludeParagraphField($pfield_name, $value->entity->bundle())) {
                  unset($value->entity->{$pfield_name});
                }
              }
            }
          }
        }
      }
    }

    return $node;
  }

  /**
   * Check whether to exclude the paragraph field.
   *
   * @param string $field_name
   *   The field name.
   * @param string $bundle_name
   *   The bundle name.
   *
   * @return bool|null
   *   TRUE or FALSE depending on config setting, or NULL if config not found.
   */
  protected function excludeParagraphField($field_name, $bundle_name) {
    $config_name = 'exclude.paragraph.' . $bundle_name;
    if ($exclude_fields = $this->getConfigSettings($config_name)) {
      return in_array($field_name, $exclude_fields);
    }
  }

  /**
   * Get the settings.
   *
   * @param string $value
   *   The setting name.
   *
   * @return array|mixed|null
   *   Returns the setting value if it exists, or NULL.
   */
  protected function getConfigSettings($value) {
    $settings = $this->configFactory->get('quick_node_clone.settings')
      ->get($value);

    return $settings;
  }

}
