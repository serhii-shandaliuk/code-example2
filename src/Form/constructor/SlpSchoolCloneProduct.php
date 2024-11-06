<?php

namespace Drupal\slp_school\Form\constructor;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\slp_school\SchoolManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class SlpSchoolCloneProduct extends FormBase {

  /**
   * The school manager.
   *
   * @var \Drupal\slp_school\SchoolManagerInterface
   */
  protected SchoolManagerInterface $schoolManager;

  /**
   * The product storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $productStorage;

  /**
   * The current user.
   *
   * @var User
   */
  protected User $user;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\slp_school\SchoolManagerInterface $school_manager
   *    The school manager.
   */
  public function __construct(SchoolManagerInterface $school_manager, EntityTypeManagerInterface $entityTypeManager) {
    $this->schoolManager = $school_manager;
    $this->productStorage = $entityTypeManager->getStorage('commerce_product');
    $this->user = $entityTypeManager->getStorage('user')->load($this->currentUser()->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('slp_school.school_manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'slp_school_clone_product';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $uuid = NULL): array {
    $selection_settings = [
      //'target_bundles' => ['teacher_subsription' => 'teacher_subsription'],
      'sort' => ['field' => '_none', 'direction' => 'ASC'],
      'auto_create' => false,
      'auto_create_bundle' => '',
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
    ];
    $data = serialize($selection_settings) . 'commerce_product' . 'default:commerce_product';
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
    $key_value_storage = \Drupal::service('keyvalue')->get('entity_autocomplete');
    if (!$key_value_storage->has($selection_settings_key)) {
      $key_value_storage->set($selection_settings_key, $selection_settings);
    }
    $route_parameters = [
      'target_type' => 'commerce_product',
      'selection_handler' => 'default:commerce_product',
      'selection_settings_key' => $selection_settings_key,
    ];
    $form['courses'] = [
      '#type' => 'autocomplete_deluxe',
      '#required' => TRUE,
      '#target_type' => 'commerce_product',
      '#multiple' => TRUE,
      '#limit' => 10,
      '#autocomplete_deluxe_path' => Url::fromRoute('autocomplete_deluxe.autocomplete', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#title' => $this->t('Select courses.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Clone'),
      '#name' => 'add',
      '#attributes' => ['class' => ['btn btn-primary btn-md']],
      '#prefix' => '<div class="actions-container">',
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
    $courses = $form_state->getValue('courses');
    if (empty($courses)) {
      $form_state->setError($form['courses'], $this->t('Users field is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $courses = $form_state->getValue('courses');
    foreach ($courses as $course) {
      $product = $this->productStorage->load($course['target_id']);
      $pid = $this->schoolManager->cloneEntity($product, $this->user);
    }
  }

}
