<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

class CsvExporterFormBase extends EntityForm
{
  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  public function __construct(EntityStorageInterface $entity_storage, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info)
  {
    $this->entityStorage = $entity_storage;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  public static function create(ContainerInterface $container)
  {
    $form = new static(
      $container->get('entity_type.manager')->getStorage('csv_exporter'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this
        ->t('Machine name'),
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [
          $this,
          'exists',
        ],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'error' => 'The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores. Additionally, it can not be the reserved word "custom".',
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->getDescription(),
    ];

    $form['entity_fields_export_list'] = $this->buildEntityFieldMapping();

    $form['field_type_specific'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t("Field Type Settings")
    ];

    $form['field_type_specific']['file'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t("File")
    ];

    $form['field_type_specific']['file']['field_file'] = [
      '#type' => 'radios',
      '#title' => $this->t('File export handling'),
      '#default_value' => $entity->getFileFieldSetting(),
      '#options' => [
        'id' => $this->t('Export the identifier (file ID or UUID) only'),
        'path_with_binary' => $this->t('Package the binary file and export the relative path.'),
        'file_entity' => $this->t('Package the binary file and export the referenced file entity'),
      ],
    ];

    $form['field_type_specific']['image'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t("Image")
    ];

    $form['field_type_specific']['image']['field_image'] = [
      '#type' => 'radios',
      '#title' => $this->t('Image export handling'),
      '#default_value' => $entity->getImageFieldSetting(),
      '#options' => [
        'id' => $this->t('Export the identifier (file ID or UUID) only'),
        'path_with_binary' => $this->t('Package the binary image file and export the relative path.'),
        'file_entity' => $this->t('Package the binary image file and export the referenced image file entity'),
      ],
    ];

    $form['csv'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t("CSV File Settings")
    ];

    $form['csv']['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field delimiter'),
      '#maxlength' => 2,
      '#size' => 2,
      '#default_value' => $entity->getSeparator(),
      '#required' => TRUE,
    ];

    $form['csv']['enclosure'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field enclosure'),
      '#maxlength' => 2,
      '#size' => 2,
      '#default_value' => $entity->getEnclosure(),
      '#required' => TRUE,
    ];

    $form['csv']['escape'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Escape character'),
      '#maxlength' => 2,
      '#size' => 2,
      '#default_value' => $entity->getEscape(),
      '#required' => FALSE,
    ];

    /*     // Only in PHP 8.1
        $form['csv']['eol'] = [
          '#type' => 'textfield',
          '#title' => $this->t('End of line sequence'),
          '#maxlength' => 255,
          '#size' => 5,
          '#default_value' => $entity->getEol(),
          '#required' => TRUE,
        ]; */

    $form['csv']['multivalue_delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Multi-value delimiter'),
      '#maxlength' => 255,
      '#size' => 5,
      '#default_value' => $entity->getMultivalueDelimiter(),
      '#required' => TRUE,
    ];

    return $form;
  }

  protected function buildEntityFieldMapping() {
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->entity;

    $build = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t("Field Mappings")
    ];

    $all_bundle_info = $this->entityTypeBundleInfo->getAllBundleInfo();

    foreach ($entity->getSupportedEntityTypes() as $type) {
      $entity_type = $this->entityTypeManager->getStorage($type)->getEntityType();

      $build[$type] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $entity_type->getLabel(),
      ];

      foreach($all_bundle_info[$type] as $bundle => $bundle_info) {
        $build[$type][$bundle] = [
          '#type' => 'details',
          '#open' => FALSE,
          '#title' => $bundle_info['label'],
        ];
        $table = "{$type}__{$bundle}";
        $build[$type][$bundle][$table] = [
          '#type' => 'table',
          //'#caption' => $bundle_info['label'],
          '#header' => [
            $this->t('Export'),
            $this->t('Field name'),
            $this->t('Field label'),
            $this->t('CSV header label'),
            $this->t('Weight')
          ],
          '#tabledrag' => [
            [
              'action' => 'order',
              'relationship' => 'sibling',
              'group' => 'table-sort-weight',
            ],
          ],
        ];

        foreach($entity->getMappedFields($type, $bundle) as $weight => $mapped_field) {
          $row = [
            '#attributes' => ['class' => ['draggable']],
            '#weight' => 0,
          ];
          // Export.
          $row['export'] = [
            '#type' => 'checkbox',
            '#default_value' => $mapped_field['export'],
          ];

          // Field name.
          $row['field_name'] = [
            '#type' => 'item',
            '#markup' => $mapped_field['field_name'],
          ];

          // Field label.
          $row['field_label'] = [
            '#type' => 'item',
            '#markup' => $mapped_field['field_label'],
          ];

          // CSV header label.
          $row['csv_header_label'] = [
            '#type' => 'textfield',
            '#default_value' => $mapped_field['csv_header_label'],
          ];

          $row['weight'] = [
            '#type' => 'weight',
            '#title' => $this
              ->t('Weight for @title', [
                '@title' => $mapped_field['field_label'],
              ]),
            '#title_display' => 'invisible',
            '#default_value' => $weight,
            '#attributes' => [
              'class' => [
                'table-sort-weight',
              ],
            ],
          ];

          $build[$type][$bundle][$table][$mapped_field['field_name']] = $row;
        }
      }
    }

    return $build;
  }

  public function exists($entity_id, array $element, FormStateInterface $form_state)
  {
    $query = $this->entityStorage->getQuery();

    $result = $query
      ->condition('id', $element['#field_prefix'] . $entity_id)
      ->execute();

    return (bool) $result;
  }

  protected function actions(array $form, FormStateInterface $form_state)
  {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }




  public function save(array $form, FormStateInterface $form_state)
  {
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $entity */
    $entity = $this->getEntity();
    $values = $form_state->getValues();

    // Field mappings.
    $field_list = [];
    $all_bundle_info = $this->entityTypeBundleInfo->getAllBundleInfo();

    foreach ($entity->getSupportedEntityTypes() as $type) {
      foreach($all_bundle_info[$type] as $bundle => $bundle_info) {
        $key = "{$type}__{$bundle}";
        $entity_type_field_mapping = [];
        if (isset($values[$key])) {
          foreach ($values[$key] as $fieldname => $field_values) {
            if ($field_values['export'] == "1") {
              $entity_type_field_mapping[$fieldname] = $field_values['csv_header_label'];
            }
          }
        }
        $field_list[$key] = $entity_type_field_mapping;
      }
    }

    $entity->setMultivalueDelimiter($form_state->getValue('multivalue_delimiter'));
    $entity->setImageFieldSetting($form_state->getValue('field_image'));
    $entity->set('entity_fields_export_list', $field_list);
    $status = $entity->save();
    $form_state->setRedirect('mukurtu_export.export_settings');
  }
}
