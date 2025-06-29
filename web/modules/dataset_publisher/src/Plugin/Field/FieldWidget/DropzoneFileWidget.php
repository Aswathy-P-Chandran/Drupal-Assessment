<?php

namespace Drupal\dataset_publisher\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;

/**
 * Plugin implementation of the 'dropzone_file_widget' widget.
 *
 * @FieldWidget(
 *   id = "dropzone_file_widget",
 *   label = @Translation("Dropzone File Upload"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class DropzoneFileWidget extends FileWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    
    // Add custom dropzone container
    $element['#theme'] = 'dropzone_file_widget';
    $element['#attached']['library'][] = 'dataset_publisher/dropzone';
    
    // Configure file upload settings
    $element['#upload_validators'] = [
      'file_validate_extensions' => ['xlsx csv'],
    ];
    
    // Add dropzone specific attributes
    $element['#attributes']['class'][] = 'dropzone-widget';
    $element['#attributes']['data-accepted-files'] = '.xlsx,.csv';
    $element['#attributes']['data-max-filesize'] = $this->getSetting('max_filesize');
    $element['#attributes']['data-max-files'] = $this->getSetting('max_files');
    $element['#attributes']['data-field-name'] = $items->getName();
    
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'max_filesize' => '10',
      'accepted_files' => 'xlsx,csv',
      'max_files' => 10,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    
    $element['max_filesize'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum file size (MB)'),
      '#default_value' => $this->getSetting('max_filesize'),
      '#description' => $this->t('Maximum file size in megabytes'),
      '#min' => 1,
      '#max' => 100,
    ];
    
    $element['max_files'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of files'),
      '#default_value' => $this->getSetting('max_files'),
      '#min' => 1,
      '#max' => 50,
    ];
    
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    
    $summary[] = $this->t('Max file size: @size MB', [
      '@size' => $this->getSetting('max_filesize'),
    ]);
    
    $summary[] = $this->t('Max files: @count', [
      '@count' => $this->getSetting('max_files'),
    ]);
    
    return $summary;
  }

}