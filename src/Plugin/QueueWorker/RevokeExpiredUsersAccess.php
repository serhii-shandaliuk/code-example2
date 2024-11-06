<?php

namespace Drupal\slp_school\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\user\Entity\User;

/**
 * Process batch API data fetcher.
 *
 * @QueueWorker(
 *   id = "revoke_expired_users_access",
 *   title = @Translation("Revoke expired users access"),
 *   cron = {"time" = 2400}
 * )
 */
class RevokeExpiredUsersAccess extends QueueWorkerBase
{

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    try {
      $user = User::load($data);
      $role = $user->get('field_school_role')->value;
      if (!$user->get('field_expired')->value) {
        \Drupal::service('slp_school.school_manager')->revokeTeacherAccess((int)$data, $role ?? 'teacher');
      }
    } catch (\Exception $exception) {
      \Drupal::logger('slp_school')->error($exception->getMessage());
    }
  }

}