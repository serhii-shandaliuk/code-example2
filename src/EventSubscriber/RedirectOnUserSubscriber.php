<?php

namespace Drupal\slp_school\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Drupal\slp_school\SchoolManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event Subscriber RedirectOn403Subscriber.
 */
class RedirectOnUserSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The current language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * RedirectOn403Subscriber Constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
 */
  public function __construct(AccountInterface $current_user, RouteMatchInterface $route_match, EntityTypeManagerInterface $entityTypeManager, StateInterface $state, LanguageManagerInterface $language_manager) {
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entityTypeManager;
    $this->state = $state;
    $this->langcode = $language_manager->getCurrentLanguage()->getId();
  }

  /**
   * Redirect from the default user page.
   *
   * @param RequestEvent $event
   *  Current event.
   */
  public function checkAuthStatus(RequestEvent $event): void {
    if (!$this->currentUser->isAnonymous() && $this->routeMatch->getRouteName() === 'entity.user.canonical') {
      $nid = $this->state->get('lesson_node_id_for_user_page', SchoolManagerInterface::LESSON_NODE_ID_FOR_REDIRECT);
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      $url = '/en/my-account';
      if ($node->hasTranslation($this->langcode)) {
        $translation = $node->getTranslation($this->langcode);
        $url = $translation->toUrl()->toString();
      }

      $response = new RedirectResponse($url, 301);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['checkAuthStatus'];

    return $events;
  }

}
