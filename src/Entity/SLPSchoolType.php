<?php

declare(strict_types=1);

namespace Drupal\slp_school\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the SLP school type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "slp_school_type",
 *   label = @Translation("SLP school type"),
 *   label_collection = @Translation("SLP school types"),
 *   label_singular = @Translation("slp school type"),
 *   label_plural = @Translation("slp schools types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count slp schools type",
 *     plural = "@count slp schools types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\slp_school\Form\SLPSchoolTypeForm",
 *       "edit" = "Drupal\slp_school\Form\SLPSchoolTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\slp_school\SLPSchoolTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer slp_school types",
 *   bundle_of = "slp_school",
 *   config_prefix = "slp_school_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/slp_school_types/add",
 *     "edit-form" = "/admin/structure/slp_school_types/manage/{slp_school_type}",
 *     "delete-form" = "/admin/structure/slp_school_types/manage/{slp_school_type}/delete",
 *     "collection" = "/admin/structure/slp_school_types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   },
 * )
 */
final class SLPSchoolType extends ConfigEntityBundleBase {

  /**
   * The machine name of this slp school type.
   */
  protected string $id;

  /**
   * The human-readable name of the slp school type.
   */
  protected string $label;

}
