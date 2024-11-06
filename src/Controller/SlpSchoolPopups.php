<?php

declare(strict_types = 1);

namespace Drupal\slp_school\Controller;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\slp_school\Ajax\ReloadTabData;
use Drupal\slp_school\Form\constructor\PaySubscription;
use Drupal\slp_school\SchoolManagerInterface;
use Drupal\slp_school\SLPGroupInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SlpSchoolPopups extends ControllerBase {

  /**
   * The school manager.
   *
   * @var \Drupal\slp_school\SchoolManagerInterface
   */
  protected SchoolManagerInterface $schoolManager;

  /**
   * The current user.
   *
   * @var EntityInterface
   */
  protected EntityInterface $currentUserEntity;

  /**
   * The SlpSchoolPopups constructor.
   *
   * @param \Drupal\slp_school\SchoolManagerInterface $school_manager
   *    The school manager.
   */
  public function __construct(SchoolManagerInterface $school_manager) {
    $this->schoolManager = $school_manager;
    $this->currentUserEntity = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('slp_school.school_manager')
    );
  }

  /**
   * Show user vocabulary.
   *
   * @param UserInterface $user
   *   Current user.
   *
   * @return array
   *   Vocabulary array.
   */
  public function userPopup(UserInterface $user): array {
    return $this->renderEntity($user, 'popup');
  }

  /**
   * Show user lessons.
   *
   * @param UserInterface $user
   *   Current user.
   *
   * @return array
   *   Vocabulary array.
   */
  public function lessonsPopup(UserInterface $user): array {
    return $this->renderEntity($user, 'lessons');
  }

  /**
   * Show user lessons.
   *
   *
   * @return array
   *   Vocabulary array.
   */
  public function paymentPopup(): array {
    $form_state = new FormState();
    $form = $this->formBuilder()->buildForm(PaySubscription::class, $form_state);
    $default_role = $this->currentUserEntity->get('field_school_role')->value;
    if ($default_role === 'director') {
      $default_role = 'school';
    }
    $default_subscription = $this->currentUserEntity->get('field_subscription_type')->value;
    $variation_storage = $this->entityTypeManager()->getStorage('commerce_product_variation');
    if ($default_role && $default_subscription) {
      $variation = $variation_storage->loadByProperties(
        [
          'type' => 'teacher_subsription',
          'field_subscription_type' => $default_role,
          'field_plan' => $default_subscription,
        ]
      );

      if ($variation) {
        $variation = reset($variation);
        $variation_build = $this->schoolManager->getVariationBuild($variation);
      }
    }


    return [
      '#theme' => 'slp_payment_popup',
      '#select_form' => $form,
      '#variation' => $variation_build ?? [],
    ];
  }

  /**
   * Show user event.
   *
   * @param NodeInterface $event
   *   User event.
   *
   * @return array
   *   Vocabulary array.
   */
  public function eventPopup(NodeInterface $event): array {
    $build = $this->renderEntity($event, 'default');
    $url = Url::fromRoute('slp_school.edit_event', ['event' => $event->id()]);
    if ($url->access()) {
      $build['edit_event'] = [
        '#type' => 'link',
        '#title' => [
          '#type' => 'markup',
          '#markup' => '<i class="icon-edit_with_border"></i>',
        ],
        '#url' => $url,
        '#attributes' => [
          'class' => ['use-ajax edit-event'],
          'data-progress-type' => 'fullscreen',
          'style' => 'font-size: 24px;',
          'title' => t('Edit event'),
          'data-dialog-type' => 'modal',
        ],
      ];
    }

    return $build;
  }

  /**
   * Show user event.
   *
   * @param NodeInterface $event
   *   User event.
   *
   * @return string
   *   Page title.
   */
  public function getEventPopupTitle(NodeInterface $event): string {
    return $event->getTitle();
  }

  /**
   * Show user lessons.
   *
   * @param NodeInterface $node
   *   Current node.
   *
   * @return array
   *   Vocabulary array.
   */
  public function nodePopup(NodeInterface $node): array {
    return $this->renderEntity($node, 'vocabulary');
  }

  /**
   * Show pre-delete text.
   *
   * @param UserInterface $user
   *  Current user.
   *
   * @return array
   *   Render array.
   */
  public function deletePopup(UserInterface $user): array {
    $build['text'] = [
      '#type' => 'markup',
      '#markup' => $this->t('This action cannot be undone.'),
    ];
    $build['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Delete'),
      '#url' => Url::fromRoute('slp_school.user_delete_student', ['user' => $user->id()]),
      '#attributes' => [
        'class' => ['use-ajax button-delete'],
        'data-progress-type' => 'fullscreen',
        'title' => t('Delete student'),
      ],
    ];

    return $build;
  }

  /**
   * Show pre-delete text.
   *
   * @param UserInterface $user
   *  Current user.
   *
   * @return array
   *   Render array.
   */
  public function deleteTeacherPopup(UserInterface $user): array {
    $build['text'] = [
      '#type' => 'markup',
      '#markup' => $this->t('This action cannot be undone.') . '<br>' . $this->t('Also all students of this teacher will be disabled.'),
    ];
    $build['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Delete'),
      '#url' => Url::fromRoute('slp_school.user_delete_teacher', ['user' => $user->id()]),
      '#attributes' => [
        'class' => ['use-ajax button-delete'],
        'data-progress-type' => 'fullscreen',
        'title' => t('Delete student'),
      ],
    ];

    return $build;
  }

  /**
   * Show pre-delete text.
   *
   * @param SLPGroupInterface $group
   *  Current user.
   *
   * @return array
   *   Render array.
   */
  public function deleteGroupPopup(SLPGroupInterface $group): array {
    $build['text'] = [
      '#type' => 'markup',
      '#markup' => $this->t('This action cannot be undone.'),
    ];
    $build['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Delete'),
      '#url' => Url::fromRoute('slp_school.user_delete_group', ['group' => $group->id()]),
      '#attributes' => [
        'class' => ['use-ajax button-delete'],
        'data-progress-type' => 'fullscreen',
        'title' => t('Delete group'),
      ],
    ];

    return $build;
  }

  /**
   * Delete user from this teacher.
   *
   * @param UserInterface $user
   *   Current user.
   *
   * @return AjaxResponse
   */
  public function deleteStudent(UserInterface $user): AjaxResponse {
    try {
      $role = $user->get('field_school_role')->value;
      $this->schoolManager->revokeTeacherAccess((int) $user->id(), $role ?? 'student', FALSE);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
      $this->getLogger('slp_school')->error($e->getMessage());
    }

    $response = new AjaxResponse();
    $this->messenger()->addMessage($this->t('You successfully deleted student.'));
    $message = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $response->addCommand(new ReloadTabData());
    $build['#attached']['library'][] = 'slp_school/reload_tab_data';
    $response->setAttachments($build['#attached']);
    $response->addCommand(new AfterCommand('#fh5co-page', $message));
    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));

    return $response;
  }

  /**
   * Delete user from this teacher.
   *
   * @param UserInterface $user
   *   Current user.
   *
   * @return AjaxResponse
   */
  public function deleteTeacher(UserInterface $user): AjaxResponse {
    $uid = $this->currentUser()->id();
    $school_storage = $this->entityTypeManager()->getStorage('slp_school');
    $school_entity = $school_storage->loadByProperties(['field_author' => $uid]);
    if ($school_entity) {
      $school_entity = reset($school_entity);
      $teachers = $school_entity->get('field_teachers')->getValue();
      $existing = array_column($teachers, 'target_id');
      $key = array_search($user->id(), $existing);
      if (isset($teachers[$key])) {
        unset($teachers[$key]);
      }

      $role = $user->get('field_school_role')->value;
      $this->schoolManager->revokeTeacherAccess((int) $user->id(), $role ?? 'teacher', FALSE);
      try {
        $school_entity->set('field_teachers', $teachers);
        $school_entity->save();
      }
      catch (\Exception $e) {
        $this->messenger()->addError($e->getMessage());
        $this->getLogger('slp_school')->error($e->getMessage());
      }
    }

    $response = new AjaxResponse();
    $this->messenger()->addMessage($this->t('You successfully deleted the teacher.'));
    $message = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $response->addCommand(new ReloadTabData());
    $build['#attached']['library'][] = 'slp_school/reload_tab_data';
    $response->setAttachments($build['#attached']);
    $response->addCommand(new AfterCommand('#fh5co-page', $message));
    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));

    return $response;
  }

  /**
   * Delete user from this teacher.
   *
   * @param SLPGroupInterface $group
   *   Slp group entity.
   *
   * @return AjaxResponse
 */
  public function deleteGroup(SLPGroupInterface $group): AjaxResponse {
    $groups = $this->schoolManager->getSlpGroups();
    $key = array_search($group->id(), $groups);
    if (isset($groups[$key])) {
      try {
        $group->delete();
      }
      catch (\Exception $e) {
        $this->messenger()->addError($e->getMessage());
        $this->getLogger('slp_school')->error($e->getMessage());
      }
    }

    $response = new AjaxResponse();
    $this->messenger()->addMessage($this->t('You successfully deleted the group.'));
    $message = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $response->addCommand(new ReloadTabData());
    $build['#attached']['library'][] = 'slp_school/reload_tab_data';
    $response->setAttachments($build['#attached']);
    $response->addCommand(new AfterCommand('#fh5co-page', $message));
    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));

    return $response;
  }

  /**
   * Render user.
   *
   * @param EntityInterface $entity
   *   Current user.
   *
   * @return array
   *   Vocabulary array.
   */
  protected function renderEntity(EntityInterface $entity, $view_mode): array {
    $viewBuilder = $this->entityTypeManager()->getViewBuilder($entity->getEntityTypeId());
    $build = $viewBuilder->view($entity, $view_mode);
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $build;
  }

}
