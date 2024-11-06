<?php

declare(strict_types=1);

namespace Drupal\slp_school;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a slp school entity type.
 */
interface SLPSchoolInterface extends ContentEntityInterface, EntityChangedInterface {

}
