<?php

namespace Drupal\slp_school\Form\constructor;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\slp_school\Ajax\ReloadTabData;
use Drupal\slp_school\SchoolManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class AddStudentForm extends FormBase {

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
   * The school manager.
   *
   * @var \Drupal\slp_school\SchoolManagerInterface
   */
  protected SchoolManagerInterface $schoolManager;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *     The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *    The current user.
   * @param \Drupal\slp_school\SchoolManagerInterface $school_manager
   *    The school manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, AccountInterface $current_user, SchoolManagerInterface $school_manager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $current_user;
    $this->currentUserEntity = $this->entityTypeManager->getStorage('user')->load($current_user->id());
    $this->schoolManager = $school_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('slp_school.school_manager'),
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
    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Student's email or name"),
      '#description' => $this->t('If the student does not have account created yet, they will receive email with instructions.'),
      '#required' => TRUE,
      '#placeholder' => $this->t('Fill in email or name'),
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $previous_url = $this->getRequest()->query->get('previous_url');
    if ($previous_url) {
      $url = Url::fromUserInput($previous_url);
      $form_state->setRedirectUrl($url);
    }
    $mail = $form_state->getValue('email');
    if (empty($mail)) {
      $form_state->setError($form['email'], $this->t('Email or name field is required'));
    }
    else {
      $te = $form_state->getTriggeringElement();
      if (isset($te['#name'])) {
        $user = $this->getUser($mail);
        if ($te['#name'] === 'delete') {
          if (empty($user)) {
            $form_state->setError($form['email'], $this->t('User you have tried to delete from your students does not exists.'));
          }
          else {
            $user = reset($user);
            $students = $this->currentUserEntity->get('field_students')->getValue();
            $existing = array_column($students, 'target_id');
            if (!in_array($user->id(), $existing)) {
              $form_state->setError($form['email'], $this->t('User you have tried to delete from your students does not your student any more.'));
            }
          }
        }
        elseif (!empty($user)) {
          $user = reset($user);
          if ($this->currentUser->id() === $user->id()) {
            $form_state->setError($form['email'], $this->t('You can not add yourself as a student.'));
          }

          if ($user->get('field_school_role')->value !== 'student') {
            $form_state->setError($form['email'], $this->t('The user you have tried to add is not a student.'));
          }

          $students =  $this->currentUserEntity->get('field_students')->getValue();
          $existing = array_column($students, 'target_id');
          if (in_array($user->id(), $existing) && in_array('slp_student', $user->getRoles())) {
            $form_state->setError($form['email'], $this->t('The user you have tried to add is already your student.'));
          }
        }

        if (empty($user)) {
          $form_state->setError($form['email'], $this->t('This user does not exist.'));
        }
      }

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

    $mail = $form_state->getValue('email');
    $te = $form_state->getTriggeringElement();
    if (isset($te['#name'])) {
      $user = $this->getUser($mail);
      if (!empty($user)) {
        $user_id = array_key_first($user);
      }
      else {
        $user_storage = $this->entityTypeManager->getStorage('user');
        $new_user = $user_storage->create();
        $new_user->setUsername($mail);
        $new_user->setPassword($mail);
        $new_user->enforceIsNew();
        $new_user->setEmail($mail);
        $new_user->activate();
        $new_user->save();
        $user_id = $new_user->id();
        _user_mail_notify('register_no_approval_required', $new_user);
      }

      $students = $this->currentUserEntity->get('field_students')->getValue();
      $existing = array_column($students, 'target_id');
      $action = 'deleted';
      if ($te['#name'] === 'add') {
        if (!in_array($user_id, $existing)) {
          $students += ['target_id' => $user_id];
        }
        $this->schoolManager->giveTeacherAccess($user_id, 'student', NULL, '+1 month');
        $action = 'added';
      }
      else {
        $key = array_search($user_id, $existing);
        if (isset($students[$key])) {
          unset($students[$key]);
        }
        $this->schoolManager->revokeTeacherAccess($user_id, 'student', FALSE);
      }

      $this->currentUserEntity->set('field_students', $students);
      $this->currentUserEntity->save();
      $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));
      $this->messenger()->addMessage($this->t('You successfully @action student.', ['@action' => $this->t($action)]));
      $message = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new ReloadTabData());
      $build['#attached']['library'][] = 'slp_school/reload_tab_data';
      $response->setAttachments($build['#attached']);
      $response->addCommand(new AfterCommand('#fh5co-page', $message));
    }

    return $response;
  }

  /**
   * Returns user by name or email.
   *
   * @param $mail
   *   Email or name.
   *
   * @return array
   *   Array of users.
   */
  protected function getUser($mail): array {
    $user = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $mail]);
    if (!$user) {
      $user = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $mail]);
    }

    return $user;
  }

}
