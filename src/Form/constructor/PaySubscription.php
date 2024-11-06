<?php

namespace Drupal\slp_school\Form\constructor;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\slp_school\Ajax\ReloadTabData;
use Drupal\slp_school\SchoolManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class PaySubscription extends FormBase {

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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#prefix'] = '<div class="modal-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $default_role = $this->currentUserEntity->get('field_school_role')->value;
    $form['field_school_role'] = [
      '#type' => 'select',
      '#options' => [
        'teacher' => $this->t('Teacher'),
        'author' => $this->t('Author'),
        'director' => $this->t('Director'),
      ],
      '#title' => $this->t('Select the role'),
      '#empty_option' => '- ' . $this->t('Select the role') . ' -',
      '#required' => TRUE,
      '#default_value' => $default_role,
      '#ajax' => ['callback' => '::showVariationBuildCallback'],
    ];

    $default_st = $this->currentUserEntity->get('field_subscription_type')->value;
    $form['subscription_type'] = [
      '#type' => 'select',
      '#options' => [
        'basic' => $this->t('Basic', [], ['context' => 'course_type']),
        'standard' => $this->t('Standard'),
        'pro' => $this->t('Pro'),
      ],
      '#title' => $this->t('Select the subscription type'),
      '#empty_option' => '- ' . $this->t('Select the subscription type') . ' -',
      '#required' => TRUE,
      '#default_value' => $default_st,
      '#ajax' => ['callback' => '::showVariationBuildCallback'],
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
    $subscription_type = $form_state->getValue('subscription_type');
    if (empty($subscription_type)) {
      $form_state->setError($form['subscription_type'], $this->t('Subscription type field is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Show the correct answers.
   *
   * @param array $form
   *   Form array.
   *
   * @return AjaxResponse
   *   Ajax response.
   */
  public function showVariationBuildCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $selector = '.payment-variation';
    $role = $form_state->getValue('field_school_role');
    if ($role === 'director') {
      $role = 'school';
    }
    $subscription_type = $form_state->getValue('subscription_type');
    if ($role && $subscription_type) {
      $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
      $variation = $variation_storage->loadByProperties(
        [
          'type' => 'teacher_subsription',
          'field_subscription_type' => $role,
          'field_plan' => $subscription_type,
        ]
      );

      if ($variation) {
        $variation = reset($variation);
        $variation_build = $this->schoolManager->getVariationBuild($variation);
        $response->addCommand(new HtmlCommand($selector, $variation_build));
      }
    }

    return $response;
  }

}
