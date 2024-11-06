<?php

namespace Drupal\slp_school\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;


/**
 * Create a new node entity from a webform submission.
 *
 * @WebformHandler(
 *   id = "gmt_send_email_for_author",
 *   label = @Translation("Send email for author"),
 *   category = @Translation("Custom"),
 *   description = @Translation("Send email for author"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class SendEmailForAuthorWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['message']['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message subject'),
      '#default_value' => $this->configuration['subject'],
      '#required' => TRUE,
    ];
    $form['message']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to be displayed when form is completed'),
      '#default_value' => $this->configuration['message'],
      '#required' => TRUE,
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['message'] = $form_state->getValue('message')['message'];
    $this->configuration['subject'] = $form_state->getValue('message')['subject'];
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission): void {
    $pid = \Drupal::request()->query->get('product-id');
    $email = \Drupal::config('system.site')->get('mail');
    $subject = $this->configuration['subject'];
    $message = $this->configuration['message'];
    $message = $this->replaceTokens($message, $this->getWebformSubmission());
    if ($pid) {
      $product = $this->entityTypeManager->getStorage('commerce_product')->load($pid);
      if ($product) {
        $aid = $product->get('uid')->target_id;
        $user = $this->entityTypeManager->getStorage('user')->load($aid);
        $email = $user->getEmail();
      }
    }

    // Send email.
    _slp_interactive_send_message_mail($message, $email, $subject);
  }

}
