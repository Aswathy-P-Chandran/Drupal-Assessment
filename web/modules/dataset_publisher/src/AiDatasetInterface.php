<?php

declare(strict_types=1);

namespace Drupal\dataset_publisher;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an ai dataset entity type.
 */
interface AiDatasetInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
