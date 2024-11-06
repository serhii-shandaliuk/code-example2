<?php

namespace Drupal\slp_school\Form\lessons;

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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class AddWordForm extends FormBase {

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
   * The taxonomy term.
   *
   * @var EntityInterface
   */
  protected EntityInterface $entity;

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
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $vocabulary = $this->getRouteMatch()->getParameter('taxonomy_vocabulary');
    $taxonomy_term = $this->getRouteMatch()->getParameter('taxonomy_term');
    if ($taxonomy_term) {
      $this->entity = $taxonomy_term;
    }
    else {
      $this->entity = $term_storage->create(['vid' => $vocabulary]);
    }
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
    return 'slp_add_word_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#prefix'] = '<div class="modal-form slp-interactive-record-voice-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $input = $form_state->getUserInput();
    $name = $translation = '';
    if (isset($input['name'])) {
      $name = $input['name'];
    }
    elseif ($this->entity->getName()) {
      $name = $this->entity->getName();
    }

    if (isset($input['translation'])) {
      $translation = $input['translation'];
    }
    elseif (!$this->entity->get('field_translation')->isEmpty()) {
      $translation = $this->entity->get('field_translation')->value;
    }

    $form['status_messages'] = [
      '#type' => 'status_messages',
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#default_value' => $name,
      '#placeholder' => $this->t('Fill in name'),
    ];

    $form['translation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Translation'),
      '#required' => TRUE,
      '#default_value' => $translation,
      '#placeholder' => $this->t('Fill in translation'),
    ];

    $form['voice_recorder'] = [
      '#type' => 'theme',
      '#theme' => 'voice_recorder',
      '#attached' => [
        'library' => [
          'slp_interactive/slp_interactive',
          'slp_interactive/voice_recorder',
        ],
      ],
    ];

    $form['recorded_audio'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'recorded_audio'],
    ];

    $audio = NULL;
    if (!empty($input['recorded_audio'])) {
      $audio = $this->entityTypeManager->getStorage('file')->load($input['recorded_audio']);
    }
    if ($this->entity->hasField('field_audio') && !$this->entity->get('field_audio')->isEmpty()) {
      $audio = $this->entity->get('field_audio')->entity;
    }

    if ($audio) {
      $url = $audio->createFileUrl();
      $form['voice_recorder']['#audio_url'] = $url;
      $form['#prefix'] = '<div class="modal-form slp-interactive-record-voice-form submitted">';
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add'),
      '#name' => 'add',
      '#attributes' => ['class' => ['btn btn-primary btn-md']],
      '#prefix' => '<div class="actions-container">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => [$this, 'submitFormAjax'],
        'event' => 'click',
      ],
    ];
    $form['#validate'][] = '_slp_general_identical_titles_validation';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Returns taxonomy term.
   *
   * @return EntityInterface
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
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

    $fid = $form_state->getValue('recorded_audio');
    $this->entity->set('name', $form_state->getValue('name'));
    $this->entity->set('field_translation', $form_state->getValue('translation'));
    // Set permanent.
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if ($file) {
      $file->setPermanent();
      $file->save();
      $this->entity->set('field_audio', $fid);
    }

    $this->entity->save();

    $response->addCommand(new ReplaceCommand('#drupal-modal form', $form));
    $message = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $response->addCommand(new AfterCommand('.dialog-off-canvas-main-canvas', $message));
    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));
    $response->addCommand(new ReloadTabData());
    $build['#attached']['library'][] = 'slp_school/reload_tab_data';
    $response->setAttachments($build['#attached']);

    return $response;
  }

}
