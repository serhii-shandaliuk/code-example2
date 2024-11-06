<?php

declare(strict_types=1);

namespace Drupal\slp_school;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of slp school type entities.
 *
 * @see \Drupal\slp_school\Entity\SLPSchoolType
 */
final class SLPSchoolTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['table']['#empty'] = $this->t(
      'No slp school types available. <a href=":link">Add slp school type</a>.',
      [':link' => Url::fromRoute('entity.slp_school_type.add_form')->toString()],
    );

    return $build;
  }

}
