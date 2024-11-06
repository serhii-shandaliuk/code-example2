<?php

declare(strict_types=1);

namespace Drupal\slp_school\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\slp_school\SLPGroupInterface;

/**
 * Defines the slp group entity class.
 *
 * @ContentEntityType(
 *   id = "slp_group",
 *   label = @Translation("SLP group"),
 *   label_collection = @Translation("SLP groups"),
 *   label_singular = @Translation("slp group"),
 *   label_plural = @Translation("slp groups"),
 *   label_count = @PluralTranslation(
 *     singular = "@count slp groups",
 *     plural = "@count slp groups",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\slp_school\SLPGroupListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\slp_school\Form\SLPGroupForm",
 *       "edit" = "Drupal\slp_school\Form\SLPGroupForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\slp_school\Routing\SLPGroupHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "slp_group",
 *   admin_permission = "administer slp_group",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/slp-group",
 *     "add-form" = "/slp-group/add",
 *     "canonical" = "/slp-group/{slp_group}",
 *     "edit-form" = "/slp-group/{slp_group}",
 *     "delete-form" = "/slp-group/{slp_group}/delete",
 *     "delete-multiple-form" = "/admin/content/slp-group/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.slp_group.settings",
 * )
 */
final class SLPGroup extends ContentEntityBase implements SLPGroupInterface {

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
      ->setDescription(t('The time that the slp group was created.'))
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
      ->setDescription(t('The time that the slp group was last edited.'));

    return $fields;
  }

}
