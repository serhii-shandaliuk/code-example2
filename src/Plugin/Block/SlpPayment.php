<?php

namespace Drupal\slp_school\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Slp payment' Block.
 *
 * @Block(
 *   id = "slp_payment",
 *   admin_label = @Translation("SLP payment"),
 *   category = @Translation("slp"),
 * )
 */
class SlpPayment extends BlockBase implements ContainerFactoryPluginInterface {

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
   * Constructs a new SlpMenuBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *  The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->currentUserEntity = $this->entityTypeManager->getStorage('user')->load($current_user->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $role = $this->currentUserEntity->get('field_school_role')->value;
    if ($role === 'student') {
      return [];
    }

    $today = strtotime(date('Y-m-d'));
    $class = $text = '';
    if (!$this->currentUserEntity->get('field_due_date')->isEmpty()) {
      $due_date = strtotime($this->currentUserEntity->get('field_due_date')->value);
      $days_left = $due_date - $today;
      $days_left = intval($days_left / 86400);
      $text = 'You have @days left to make the payment in order to access the platform. Thanks!';
      if ($days_left <= 7) {
        $class = 'normal';
      }
      if ($days_left <= 3) {
        $class = 'critical';
      }
      if ($days_left < 0) {
        $class = 'critical';
        $text = 'It looks like you have run out of access to the platform. Please make a payment to regain access. Thank you!';
      }
      $text = $this->t($text, ['@days' => $days_left]);
    }

    if ($class) {
      $url = Url::fromRoute('slp_school.pay_subscription');
      if ($url->access()) {
        $payment_link = [
          '#type' => 'link',
          '#title' => $this->t('Card payment (Apple Pay, Google Pay)'),
          '#url' => $url,
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-progress-type' => 'fullscreen',
            'data-dialog-type' => 'modal',
          ],
        ];
      }

      return [
        '#theme' => 'slp_payment',
        '#text' => $text,
        '#class' => $class,
        '#payment_link' => $payment_link ?? [],
      ];
    }


    return [];
  }
}
