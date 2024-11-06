<?php
/**
 * ReloadTabData.php contains ReloadTabData class
 * Defines custom ajax command for set value in CKEditor by ajax
 **/
declare(strict_types = 1);

namespace Drupal\slp_school\Ajax;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Asset\AttachedAssets;

/**
 * Class ExtendCommand.
 */
class ReloadTabData implements CommandInterface {

  public function render() {
    return [
      'command' => 'ReloadTabData',
    ];
  }
}
