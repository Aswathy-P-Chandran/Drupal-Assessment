<?php

declare(strict_types=1);

namespace Drupal\dataset_publisher;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the ai dataset entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class AiDatasetAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission($this->entityType->getAdminPermission())) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view ai_dataset'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit ai_dataset'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete ai_dataset'),
      'delete revision' => AccessResult::allowedIfHasPermission($account, 'delete ai_dataset revision'),
      'view all revisions', 'view revision' => AccessResult::allowedIfHasPermissions($account, ['view ai_dataset revision', 'view ai_dataset']),
      'revert' => AccessResult::allowedIfHasPermissions($account, ['revert ai_dataset revision', 'edit ai_dataset']),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create ai_dataset', 'administer ai_dataset'], 'OR');
  }

}
