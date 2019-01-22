<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity_provenance\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a field is unique in combination with other fields.
 */
class UniqueFieldGroupInBundleValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    $bundles = $constraint->bundles;
    if (!$item = $items->first()) {
      return;
    }
    $field_name = $items->getFieldDefinition()->getName();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    $bundle = $entity->bundle();
    if (!in_array($bundle, $bundles)) {
      // The constraint does not apply to this bundle.
      return;
    }

    $bundle_key = $entity->getEntityType()->getKey('bundle');
    $entity_type_id = $entity->getEntityTypeId();
    $id_key = $entity->getEntityType()->getKey('id');

    $query = \Drupal::entityQuery($entity_type_id)
      ->condition($field_name, $item->value)
      ->condition($bundle_key, $bundles, 'IN');

    foreach ($constraint->fields as $field_name => $field_property) {
      $query->condition($field_name . '.' . $field_property, $entity->get($field_name)->{$field_property});
    }

    if (!empty($entity->id())) {
      $query->condition($id_key, $items->getEntity()->id(), '<>');
    }
    $value_taken = (bool) $query
      ->range(0, 1)
      ->count()
      ->execute();

    if ($value_taken) {
      $this->context->addViolation($constraint->message, [
        '%value' => $item->value,
        '@entity_type' => $entity->getEntityType()->getLowercaseLabel(),
        '@field_name' => mb_strtolower($items->getFieldDefinition()->getLabel()),
      ]);
    }
  }

}
