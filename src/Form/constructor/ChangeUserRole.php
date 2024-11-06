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
use Drupal\slp_school\Ajax\ReloadTabData;
use Drupal\slp_school\SchoolManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class ChangeUserRole extends FormBase {

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
    $this->selectedUser = $this->getRouteMatch()->getParameter('user');
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
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL): array {
    $form['#prefix'] = '<div class="modal-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $options = [
      'student' => $this->t('Student'),
      'teacher' => $this->t('Teacher'),
      'author' => $this->t('Author'),
    ];
    $form['field_school_role'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Select the role'),
      '#empty_option' => '- ' . $this->t('Select the role') . ' -',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Change'),
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
    $role = $form_state->getValue('field_school_role');
    if (empty($role)) {
      $form_state->setError($form['field_school_role'], $this->t('Role field is required'));
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
    $role = $form_state->getValue('field_school_role');
    $this->schoolManager->giveTeacherAccess($this->selectedUser->id(), $role, NULL, '', '', TRUE);

    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));
    $this->messenger()->addMessage($this->t('You successfully changed a user role.'));
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
