<?php

namespace Drupal\slp_school\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle AJAX responses.
 */
class AjaxResponseSubscriber implements EventSubscriberInterface {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Constructs a FormatterBase object.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *  The theme manager.
   */
  public function __construct(ThemeManagerInterface $theme_manager) {
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => [['onResponse']]];
  }

  /**
   * Renders the ajax commands right before preparing the result.
   *
   * @param ResponseEvent $event
   *   The response event, which contains the possible AjaxResponse object.
   */
  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    $current_theme = $this->themeManager->getActiveTheme();
    if (
      $response instanceof AjaxResponse &&
      $current_theme->getName() === 'slp'
    ) {
      $apply = FALSE;
      if ($response->getCommands()) {
        foreach ($response->getCommands() as $command) {
          if ($command['command'] === 'openDialog') {
            $apply = TRUE;
            break;
          }
        }
      }

      if ($apply) {
        $attachments = ['library' => ['slp/dialog']];
        $response->addAttachments($attachments);
      }
    }
  }

}
