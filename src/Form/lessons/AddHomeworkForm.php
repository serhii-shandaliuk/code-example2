<?php

namespace Drupal\slp_school\Form\lessons;

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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class AddHomeworkForm extends FormBase {

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
   * The node id.
   *
   * @var int
   */
  protected int $currentNodeId;

  /**
   * The current paragraph.
   *
   * @var EntityInterface
   */
  protected EntityInterface $currentParagraph;

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
    $this->currentNodeId = $this->getRouteMatch()->getParameter('node')?->id();
    $this->currentParagraph = $this->getRouteMatch()->getParameter('paragraph');

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
  public function buildForm(array $form, FormStateInterface $form_state, string $uuid = NULL): array {
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
      'node' => $this->currentNodeId,
    ];

    $form['users'] = [
      '#type' => 'autocomplete_deluxe',
      '#required' => TRUE,
      '#target_type' => 'user',
      '#multiple' => TRUE,
      '#limit' => 10,
      '#autocomplete_deluxe_path' => Url::fromRoute('slp_school.autocomplete_users', $route_parameters, ['absolute' => TRUE])->getInternalPath(),
      '#title' => $this->t('Select students.'),
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
    $users = $form_state->getValue('users');
    if (empty($users)) {
      $form_state->setError($form['users'], $this->t('Users field is required'));
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
    $users = $form_state->getValue('users');

    $user_storage = $this->entityTypeManager->getStorage('user');
    $storage = $this->entityTypeManager->getStorage('paragraph');
    foreach ($users as $user) {
      $load_user = $user_storage->load($user['target_id']);
      $pid = $this->currentParagraph->id();
      $_url = Url::fromRoute('entity.node.canonical', ['node' => $this->currentNodeId]);
      $exists = $storage->loadByProperties(['field_message_for' => $user['target_id'], 'field_answer_id' => $pid]);

      if ($_url->access($load_user) && !$exists) {
        $message = $storage->create(
          [
            'type' => 'message',
            'field_title' => 'You have a new homework.',
            'field_description' => ['value' => $this->currentNodeId . ',' . $pid, 'format' => 'full_html'],
            'field_message_for' => ['target_id' => $user['target_id']],
            'field_answer_id' => ['target_id' => $pid],
            'field_message_type' => ['value' => 'homework'],
            'field_read' => ['value' => FALSE],
          ]
        );
        $message->save();


        // Send email.
        _slp_interactive_send_message_mail($message);
      }
    }
    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));
    $this->messenger()->addMessage($this->t('You successfully added homework for the student.'));
    $message = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $response->addCommand(new AfterCommand('#fh5co-page', $message));

    return $response;
  }

}
