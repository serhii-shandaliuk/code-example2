<?php

declare(strict_types=1);

namespace Drupal\slp_school\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\slp_school\SLPSchoolInterface;

/**
 * Defines the slp school entity class.
 *
 * @ContentEntityType(
 *   id = "slp_school",
 *   label = @Translation("SLP school"),
 *   label_collection = @Translation("SLP schools"),
 *   label_singular = @Translation("slp school"),
 *   label_plural = @Translation("slp schools"),
 *   label_count = @PluralTranslation(
 *     singular = "@count slp schools",
 *     plural = "@count slp schools",
 *   ),
 *   bundle_label = @Translation("SLP school type"),
 *   handlers = {
 *     "list_builder" = "Drupal\slp_school\SLPSchoolListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\slp_school\Form\SLPSchoolForm",
 *       "edit" = "Drupal\slp_school\Form\SLPSchoolForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\slp_school\Routing\SLPSchoolHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "slp_school",
 *   admin_permission = "administer slp_school types",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "bundle",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/slp-school",
 *     "add-form" = "/slp-school/add/{slp_school_type}",
 *     "add-page" = "/slp-school/add",
 *     "canonical" = "/slp-school/{slp_school}",
 *     "edit-form" = "/slp-school/{slp_school}",
 *     "delete-form" = "/slp-school/{slp_school}/delete",
 *     "delete-multiple-form" = "/admin/content/slp-school/delete-multiple",
 *   },
 *   bundle_entity_type = "slp_school_type",
 *   field_ui_base_route = "entity.slp_school_type.edit_form",
 * )
 */
final class SLPSchool extends ContentEntityBase implements SLPSchoolInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the slp school was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the slp school was last edited.'));

    return $fields;
  }

}
