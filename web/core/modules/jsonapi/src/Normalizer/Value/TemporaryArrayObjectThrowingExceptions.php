<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * An \ArrayObject that throws an exception when used as an ArrayObject.
 *
 * @internal This class implements all methods for class \ArrayObject and throws
 *   an \Exception when one of those methods is called.
 */
class TemporaryArrayObjectThrowingExceptions extends \ArrayObject {

  /**
   * Append a value to the ArrayObject.
   *
   * @param mixed $value
   *   The value to append to the ArrayObject.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function append($value): void {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sort the ArrayObject.
   *
   * @param int $flags
   *   The flags to sort the ArrayObject by.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function asort($flags = SORT_REGULAR): TRUE {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Count the ArrayObject.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function count(): int {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Exchange the current array with another array or object.
   *
   * @param array|object $array
   *   The array to replace for the current array.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function exchangeArray($array): array {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Exports the \ArrayObject to an array.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function getArrayCopy(): array {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Gets the behavior flags of the \ArrayObject.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function getFlags(): int {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Create a new iterator from an ArrayObject instance.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function getIterator(): \Iterator {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Gets the class name of the iterator used by \ArrayObject::getIterator().
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function getIteratorClass(): string {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sort the entries by key.
   *
   * @param int $flags
   *   The flags to sort the ArrayObject by.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function ksort($flags = SORT_REGULAR): TRUE {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sort an array using a case insensitive "natural order" algorithm.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function natcasesort(): TRUE {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sort entries using a "natural order" algorithm.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function natsort(): TRUE {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Returns whether the requested index exists.
   *
   * @param mixed $key
   *   The index being checked.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function offsetExists($key): bool {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Returns the value at the specified index.
   *
   * @param mixed $key
   *   The index with the value.
   *
   * @return mixed
   *   The value at the specified index or null.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function offsetGet($key): mixed {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sets the value at the specified index to new value.
   *
   * @param mixed $key
   *   The index being set.
   * @param mixed $value
   *   The new value for the key.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function offsetSet($key, $value): void {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Unsets the value at the specified index.
   *
   * @param mixed $key
   *   The index being unset.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function offsetUnset($key): void {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sets the behavior flags for the \ArrayObject.
   *
   * @param int $flags
   *   Set the flags that change the behavior of the \ArrayObject.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function setFlags($flags): void {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sets the iterator classname for the \ArrayObject.
   *
   * @param string $iteratorClass
   *   The classname of the array iterator to use when iterating over this
   *   object.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function setIteratorClass($iteratorClass): void {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sort the entries with a user-defined comparison function.
   *
   * @param callable $callback
   *   The comparison function must return an integer less than, equal to, or
   *   greater than zero if the first argument is considered to be respectively
   *   less than, equal to, or greater than the second.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function uasort($callback): TRUE {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

  /**
   * Sort the entries by keys using a user-defined comparison function.
   *
   * @param callable $callback
   *   The comparison function must return an integer less than, equal to, or
   *   greater than zero if the first argument is considered to be respectively
   *   less than, equal to, or greater than the second.
   *
   * @throws \Exception
   *   This class does not support this action but it must implement it, because
   *   it is extending \ArrayObject.
   */
  public function uksort($callback): TRUE {
    throw new \Exception('This ' . __CLASS__ . ' does not support this action but it must implement it, because it is extending \ArrayObject.');
  }

}
