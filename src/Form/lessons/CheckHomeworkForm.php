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
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\slp_school\Ajax\ReloadTabData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class CheckHomeworkForm extends FormBase {

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
   * The node.
   *
   * @var EntityInterface
 */
  protected EntityInterface $currentNode;


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
    $this->currentNode = $this->getRouteMatch()->getParameter('node');

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
    if (!$this->currentNode->get('field_text_answer')->isEmpty()) {
      $text_answer = $this->currentNode->get('field_text_answer')->value;
      $form['student'] = [
        '#type' => 'markup',
        '#markup' => '<div class="info">' . $this->t('Answer') . '</div>',
      ];

      $form['text_answer'] = [
        '#type' => 'markup',
        '#markup' => '<div class="text-answer">' . $text_answer . '</div>',
      ];
    }

    if (!$this->currentNode->get('field_audio_answer')->isEmpty()) {
      $audio_answer = $this->currentNode->get('field_audio_answer')->entity;
      $audio = $this->entityTypeManager->getViewBuilder('media')->view($audio_answer, 'default');
      $check_text = $this->t('This homework check page allows you to record and track student progress on recent assignments.');
      $form['audio_answer'] = [
        '#type' => 'theme',
        '#theme' => 'voice_recorder',
        '#audio' => $audio,
        '#message' => $check_text,
        '#attached' => [
          'library' => [
            'slp_interactive/slp_interactive',
            'slp_interactive/voice_recorder',
          ],
        ],
      ];
    }

    $plan = Paragraph::load($this->currentNode->get('field_plan_item')->value);
    if ($plan) {
      $pid = $plan?->getParentEntity()?->getParentEntity()?->id();
      $options = [
        'query' => ['redirected' => 1],
        'attributes' => ['target' => '_blank', 'class' => 'btn btn-primary btn-md task-button'],
      ];
      $url = Url::fromRoute('entity.node.canonical', ['node' => $pid], $options);
      $url->setOption('fragment', $plan?->getParentEntity()->uuid());

      $form['slp_homework_path'] = [
        '#type' => 'markup',
        '#markup' => Link::fromTextAndUrl(t('Go to the task'), $url)->toString(),
      ];
    }

    $form['check'] = [
      '#type' => 'text_format',
      '#format' => 'basic_html',
      '#allowed_formats' => ['basic_html'],
      '#after_build' => [[get_class($this), 'hideTextFormatHelpText'],],
      '#name' => 'check',
      '#title' => $this->t('Feedback'),
      '#default_value' => $this->currentNode->get('field_check_answer')->value ?? '',
      '#description' => $this->t('Put your thoughts about this homework here and students will see it as answer for homework'),
    ];

    $score = $this->currentNode->get('field_progress')->value ?? 0;
    if ($score) {
      $score *= 10;
    }
    $form['score'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Score', [], ['context' => 'progress']),
      '#max' => 10,
      '#min' => 1,
      '#description' => $this->t('Put score from 1 to 10 for this homework'),
      '#default_value' => $score,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Submit'),
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

    return $form;
  }

  /**
   * Hide help for formated text.
   *
   * @param array $element
   *   Form element.
   *
   * @return array
   *   Form element.
   */
  public static function hideTextFormatHelpText(array $element): array {
    if (isset($element['format']['help'])) {
      $element['format']['help']['#access'] = FALSE;
    }
    if (isset($element['format']['guidelines'])) {
      $element['format']['guidelines']['#access'] = FALSE;
    }
    if (isset($element['format']['#attributes']['class'])) {
      unset($element['format']['#attributes']['class']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $score = $form_state->getValue('score');
    if (empty($score)) {
      $form_state->setError($form['score'], $this->t('Score field is required'));
    }
    else {
      if ($score < 0 || $score > 10) {
        $form_state->setError($form['score'], $this->t('The score value can be in the range 1-10'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $node = $this->currentNode;
      $node->set('field_checked', TRUE);
      $node->set('field_check_answer', $form_state->getValue('check'));
      $node->set('field_progress', $form_state->getValue('score') / 10);
      $node->save();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
      $this->getLogger('slp_school')->error($e->getMessage());
    }

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

    $response->addCommand(new InvokeCommand('.ui-dialog-titlebar-close', 'trigger', ['click']));
    $this->messenger()->addMessage($this->t('You successfully checked homework for student.'));
    $response->addCommand(new ReloadTabData());
    $build['#attached']['library'][] = 'slp_school/reload_tab_data';
    $response->setAttachments($build['#attached']);
    $message = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];
    $response->addCommand(new AfterCommand('#fh5co-page', $message));

    return $response;
  }


  /**
   * Returns a page title.
   */
  public function getTitle(): TranslatableMarkup {
    return  $this->t('Check the homework for @student', ['@student' => $this->currentNode->getTitle()]);
  }

}
