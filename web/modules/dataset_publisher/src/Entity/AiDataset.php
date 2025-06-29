<?php

declare(strict_types=1);

namespace Drupal\dataset_publisher\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Form\DeleteMultipleForm;
use Drupal\Core\Entity\Form\RevisionDeleteForm;
use Drupal\Core\Entity\Form\RevisionRevertForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\dataset_publisher\AiDatasetAccessControlHandler;
use Drupal\dataset_publisher\AiDatasetInterface;
use Drupal\dataset_publisher\AiDatasetListBuilder;
use Drupal\dataset_publisher\Form\AiDatasetForm;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\file\Entity\File;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Drupal\taxonomy\Entity\Term;

/**
 * Defines the ai dataset entity class.
 */
#[ContentEntityType(
  id: 'ai_dataset',
  label: new TranslatableMarkup('Ai dataset'),
  label_collection: new TranslatableMarkup('Ai datasets'),
  label_singular: new TranslatableMarkup('ai dataset'),
  label_plural: new TranslatableMarkup('ai datasets'),
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'langcode' => 'langcode',
    'label' => 'label',
    'owner' => 'uid',
    'published' => 'status',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => AiDatasetListBuilder::class,
    'views_data' => EntityViewsData::class,
    'access' => AiDatasetAccessControlHandler::class,
    'form' => [
      'add' => AiDatasetForm::class,
      'edit' => AiDatasetForm::class,
      'delete' => ContentEntityDeleteForm::class,
      'delete-multiple-confirm' => DeleteMultipleForm::class,
      'revision-delete' => RevisionDeleteForm::class,
      'revision-revert' => RevisionRevertForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
      'revision' => RevisionHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/ai-dataset',
    'add-form' => '/ai-dataset/add',
    'canonical' => '/ai-dataset/{ai_dataset}',
    'edit-form' => '/ai-dataset/{ai_dataset}/edit',
    'delete-form' => '/ai-dataset/{ai_dataset}/delete',
    'delete-multiple-form' => '/admin/content/ai-dataset/delete-multiple',
    'revision' => '/ai-dataset/{ai_dataset}/revision/{ai_dataset_revision}/view',
    'revision-delete-form' => '/ai-dataset/{ai_dataset}/revision/{ai_dataset_revision}/delete',
    'revision-revert-form' => '/ai-dataset/{ai_dataset}/revision/{ai_dataset_revision}/revert',
    'version-history' => '/ai-dataset/{ai_dataset}/revisions',
  ],
  admin_permission: 'administer ai_dataset',
  base_table: 'ai_dataset',
  data_table: 'ai_dataset_field_data',
  revision_table: 'ai_dataset_revision',
  revision_data_table: 'ai_dataset_field_revision',
  translatable: TRUE,
  show_revision_ui: TRUE,
  label_count: [
    'singular' => '@count ai datasets',
    'plural' => '@count ai datasets',
  ],
  field_ui_base_route: 'entity.ai_dataset.settings',
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
)]
class AiDataset extends EditorialContentEntityBase implements AiDatasetInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    // Set owner if not set.
    if (!$this->getOwnerId()) {
      $this->setOwnerId(0);
    }

    // Extract metadata from uploaded file.
    if ($this->hasField('file') && !$this->get('file')->isEmpty()) {
      $file = $this->get('file')->entity;

      if ($file instanceof File) {
        $real_path = \Drupal::service('file_system')->realpath($file->getFileUri());

        try {
          $spreadsheet = IOFactory::load($real_path);
          $sheet = $spreadsheet->getActiveSheet();
          $highestRow = $sheet->getHighestRow();
          $highestColumn = $sheet->getHighestColumn();

          // Get column names from first row
          $columnNames = $sheet->rangeToArray('A1:' . $highestColumn . '1')[0] ?? [];

          // Get sample data (first 5 rows after header)
          $sampleRows = $sheet->rangeToArray('A2:' . $highestColumn . min(6, $highestRow));
          $sampleData = [];
          $sampleData[] = implode(',', $columnNames); // header
          foreach ($sampleRows as $row) {
            $sampleData[] = implode(',', array_map('strval', $row));
          }

          // Set basic metadata
          $this->set('row_count', $highestRow - 1); // Exclude header
          $this->set('column_names', implode(', ', array_map('trim', $columnNames)));
          $this->set('file_size', $file->getSize());

          // 🔥 Call Hugging Face service for metadata generation
          /** @var \Drupal\dataset_publisher\Service\HuggingFaceService $hf */
          $hf = \Drupal::service('dataset_publisher.openai_service');
          $metadata = $hf->generateMetadata($sampleData, 'en');
          if (!empty($metadata)) {
            if ($this->hasField('title') && !empty($metadata['title'])) {
              $this->set('title', $metadata['title']);
            }
            if ($this->hasField('description') && !empty($metadata['description'])) {
              $this->set('description', $metadata['description']);
            }
            if ($this->hasField('tags') && !empty($metadata['tags'])) {
              $this->set('tags', $this->convertTagsToTermIds($metadata['tags']));
            }
            // if ($this->hasField('category') && !empty($metadata['category'])) {
            //   $this->set('category', $metadata['category']);
            // }
          }

        } catch (ReaderException $e) {
          \Drupal::logger('ai_dataset')->error('Failed to parse spreadsheet: @error', [
            '@error' => $e->getMessage(),
          ]);
        }
      }
    }
  }



  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
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
      ->setRevisionable(TRUE)
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
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
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

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the ai dataset was created.'))
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
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the ai dataset was last edited.'));

      $fields['file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Dataset File'))
      ->setSettings([
        'file_extensions' => 'csv xlsx',
        'uri_scheme' => 'public',
      ])
      ->setDisplayOptions('form', ['type' => 'file_generic', 'weight' => 2])
      ->setDisplayOptions('view', ['type' => 'file_default', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  
    // Row count
    $fields['row_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Row Count'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 3])
      ->setDisplayOptions('view', ['type' => 'number_integer', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  
    // Column names
    $fields['column_names'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Column Names'))
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => 4])
      ->setDisplayOptions('view', ['type' => 'text_default', 'weight' => 4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  
    // File size
    $fields['file_size'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('File Size (Bytes)'))
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => 5])
      ->setDisplayOptions('view', ['type' => 'number_integer', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  
    // Metadata status
    $fields['metadata_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Metadata Status'))
      ->setSettings([
        'allowed_values' => [
          'pending' => 'Pending',
          'processed' => 'Processed',
          'failed' => 'Failed',
        ],
      ])
      ->setDefaultValue('pending')
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 6])
      ->setDisplayOptions('view', ['type' => 'list_default', 'weight' => 6])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  
    // Tags
    $fields['tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tags'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => ['tags' => 'tags'],
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete_tags', 'weight' => 7])
      ->setDisplayOptions('view', ['type' => 'entity_reference_label', 'weight' => 7])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  protected function convertTagsToTermIds(array $tags): array {
    $termIds = [];
  
    foreach ($tags as $name) {
      $name = trim($name);
      if ($name === '') {
        continue;
      }
  
      // Check if term exists
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties([
          'name' => $name,
          'vid' => 'tags', // Adjust vocabulary machine name as needed
        ]);
  
      if ($term = reset($terms)) {
        $termIds[] = $term->id();
      }
      else {
        // Create new term
        $term = Term::create([
          'vid' => 'tags',
          'name' => $name,
        ]);
        $term->save();
        $termIds[] = $term->id();
      }
    }
  
    return $termIds;
  }

}
