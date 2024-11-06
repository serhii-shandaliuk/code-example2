<?php

declare(strict_types = 1);

namespace Drupal\slp_school\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\slp_interactive\InteractiveManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes a Login Form for React UI.
 */
abstract class SlpSchoolBaseForm extends FormBase {

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
   * The interactive manager.
   *
   * @var \Drupal\slp_interactive\InteractiveManagerInterface
   */
  protected InteractiveManagerInterface $interactiveManager;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *     The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *    The current user.
   * @param \Drupal\slp_interactive\InteractiveManagerInterface $interactiveManager
   *    The interactive manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountInterface $current_user, InteractiveManagerInterface $interactiveManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->currentUserEntity = $this->entityTypeManager->getStorage('user')->load($current_user->id());
    $this->interactiveManager = $interactiveManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('slp_interactive.interactive_manager'),
    );
  }

  /**
   * Gives or revoke access for users to lessons.
   *
   * @param array $users
   *   Users ids array.
   * @param array $lessons
   *   Lessons ids array.
   * @param string $action
   *   Action can be delete or add.
   */
  protected function giveUsersAccessForLessons(array $users, array $lessons, string $action): void {
    $user_storage = $this->entityTypeManager->getStorage('user');
    foreach ($users as $user) {
      $load_user = $user_storage->load($user['target_id']);
      $loaded_lessons = $load_user->get('field_lessons')->getValue();
      $loaded_lessons_check = array_column($loaded_lessons, 'target_id');
      if ($action === 'delete') {
        foreach ($lessons as $lesson) {
          $key = array_search($lesson, $loaded_lessons_check);
          if ($key !== FALSE) {
            unset($loaded_lessons[$key]);
            $load_user->set('field_lessons', $loaded_lessons);
          }
        }
        $load_user->save();
      }
      else {
        $storage = $this->entityTypeManager->getStorage('paragraph');
        foreach ($lessons as $lesson) {
          if (!in_array($lesson, $loaded_lessons_check)) {
            $loaded_lessons[] = ['target_id' => $lesson];
            $message = $storage->create(
              [
                'type' => 'message',
                'field_title' => 'Access for new lesson has been added.',
                'field_description' => ['value' => $lesson ?? '', 'format' => 'full_html'],
                'field_message_for' => ['target_id' => $user['target_id']],
                'field_message_type' => ['value' => 'grand_lesson'],
              ]
            );
            $message->save();

            // Send email.
            _slp_interactive_send_message_mail($message);
          }
          $load_user->set('field_lessons', $loaded_lessons);
        }
        $load_user->save();
      }
    }
  }

  /**
   * Gives or revoke access for users to courses.
   *
   * @param array $users
   *   Users ids array.
   * @param array $courses
   *   Courses ids array.
   * @param string $action
   *   Action can be delete or add.
   * @param string $course_type
   *   Course type can be basic, standard or pro.
   */
  protected function giveUsersAccessForCourses(array $users, array $courses, string $action, string $course_type): void {
    $user_storage = $this->entityTypeManager->getStorage('user');
    foreach ($users as $user) {
      $load_user = $user_storage->load($user['target_id']);
      $loaded_courses = $load_user->get('field_courses')->getValue();
      $loaded_courses_check = array_column($loaded_courses, 'target_id');
      if ($action === 'delete') {
        foreach ($courses as $course) {
          $key = array_search($course, $loaded_courses_check);
          if ($key !== FALSE) {
            unset($loaded_courses[$key]);
            $load_user->set('field_courses', $loaded_courses);
          }
        }
        $load_user->save();
      }
      else {
        $storage = $this->entityTypeManager->getStorage('paragraph');
        $node_storage = $this->entityTypeManager->getStorage('node');
        $product_storage = $this->entityTypeManager->getStorage('commerce_product');
        foreach ($courses as $course) {
          if (!in_array($course, $loaded_courses_check)) {
            $loaded_courses[] = ['target_id' => $course];

            $properties = ['field_course' => $course];
            $lessons = $node_storage->loadByProperties($properties);
            $product = $product_storage->load($course);
            $lesson_id = $product->get('field_demo_node')->target_id ?: '';
            if (!empty($lessons)) {
              foreach ($lessons as $lesson) {
                if (str_contains($lesson->getTitle(), 'Lesson 1.')) {
                  $lesson_id = $lesson->id();
                  break;
                }
              }
            }

            $message = $storage->create(
              [
                'type' => 'message',
                'field_title' => 'Access for new course has been added.',
                'field_description' => ['value' => $lesson_id ?? '', 'format' => 'full_html'],
                'field_message_for' => ['target_id' => $user['target_id']],
                'field_message_type' => ['value' => 'grand_course'],
              ]
            );
            $message->save();

            // Send email.
            _slp_interactive_send_message_mail($message);
          }
          $load_user->set('field_courses', $loaded_courses);
          $progress = $load_user->get('field_progress')->value;
          $this->interactiveManager->saveData($progress, [$course => ['course_type' => $course_type]], $load_user);
        }
      }
    }
  }

}
