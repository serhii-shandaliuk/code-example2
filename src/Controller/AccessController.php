<?php

namespace Drupal\slp_school\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Builds an example page.
 */
class AccessController extends ControllerBase {

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if (in_array('author_paid', $account->getRoles()) || in_array('teacher_paid', $account->getRoles())) {
      $query = $this->entityTypeManager()->getStorage('commerce_product')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'default')
        ->condition('uid', $account->id());
      $pids = $query->execute();
      if (count($pids) >= 10) {
        return AccessResult::forbidden();
      }
    }

    return AccessResult::allowed();
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return AccessResultInterface
   *   The access result.
   */
  public function accessEditEvent(AccountInterface $account, NodeInterface $event): AccessResultInterface {
    $teacher_id = $event->get('field_teacher')->target_id;
    if ($teacher_id != $account->id()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
