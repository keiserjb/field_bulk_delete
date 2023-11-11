<?php
namespace Drupal\field_bulk_delete\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;


class FieldBulkDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_bulk_delete_admin_form';
  }

  /**
   * Form constructor.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);

    // Entity type selection.
    $form['entity_types'] = [
      '#title'        => $this->t('Select Entity Type'),
      '#type'         => 'select',
      '#options'      => $this->getEntityTypes(),
      '#empty_option' => $this->t('- Select -'),
      '#ajax'         => [
        'callback' => '::bundleListCallback',
        'wrapper'  => 'bundles-wrapper',
        'event'    => 'change',
      ],
    ];

    // Bundles wrapper.
    $form['bundles_wrapper'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'bundles-wrapper'],
    ];

    // Initially empty bundles select element.
    $form['bundles_wrapper']['bundles'] = [
      '#type'         => 'select',
      '#title'        => $this->t('Select Bundle'),
      '#options'      => [],
      '#empty_option' => $this->t('- Select -'),
      '#validated'    => TRUE,
      '#ajax'         => [
        'callback' => '::fieldsListCallback',
        'wrapper'  => 'fields-wrapper',
        'event'    => 'change',
      ],
    ];

    // Fields wrapper.
    $form['fields_wrapper'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'fields-wrapper'],
    ];

    // Checkboxes with dynamic options based on selected bundle.
    $selected_bundle = $form_state->getValue('bundles');
    $entity_type = $form_state->getValue('entity_types');

    if ($entity_type && $selected_bundle) {
      $options = $this->getFieldOptionsForBundle(
        $entity_type,
        $selected_bundle
      );
    }
    else {
      $options = []; // Empty options if no bundle is selected
    }

    $form['fields_wrapper']['fields'] = [
      '#type'    => 'checkboxes',
      '#title'   => $this->t('Field List'),
      '#options' => $options,
      '#ajax'    => [
        'callback' => '::fieldsListCallback',
        'wrapper'  => 'fields-wrapper',
        'event'    => 'change',
      ],
    ];

    // Submit button.
    $form['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Delete Selected Fields'),
      '#button_type' => 'primary',
      '#submit'      => ['::submitForm'],
    ];

    return $form;
  }

  /**
   * Helper function to get initial options for checkboxes.
   */
  private function getFieldOptionsForBundle($entity_type, $selected_bundle) {
    $field_definitions = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity_type, $selected_bundle);
    $options = [];
    foreach ($field_definitions as $field_name => $field_definition) {
      if (!$field_definition->getFieldStorageDefinition()->isBaseField()) {
        $options[$field_name] = $field_definition->getLabel();
      }
    }
    return $options;
  }

  /**
   * AJAX callback for the entity type dropdown.
   */
  public function bundleListCallback(
    array &$form,
    FormStateInterface $form_state
  ) {
    $entity_type = $form_state->getValue('entity_types');
    if ($entity_type) {
      // Retrieve the bundles for the selected entity type.
      $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo(
        $entity_type
      );
      // Populate the bundles dropdown options.
      $form['bundles_wrapper']['bundles']['#options'] = [
          '_none' => $this->t(
            '- Select -'
          )
        ] + array_map(function ($bundle_info) {
          return $bundle_info['label'];
        }, $bundles);
    }
    //dpm($form['bundles_wrapper']);
    return $form['bundles_wrapper'];
  }

  /**
   * AJAX callback for the fields list based on selected bundle.
   */
  public function fieldsListCallback(
    array &$form,
    FormStateInterface $form_state
  ) {
    return $form['fields_wrapper'];
  }

  /**
   * Helper method to get entity types.
   */
  private function getEntityTypes() {
    $entityTypes = [];
    $definitions = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($definitions as $entity_type_id => $definition) {
      if ($definition->getBundleEntityType()) {
        $entityTypes[$entity_type_id] = $definition->getLabel();
      }
    }
    return $entityTypes;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation logic goes here.
  }

  /**
   * Form submission handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_fields = array_filter($form_state->getValue('fields'));
    $entity_type = $form_state->getValue('entity_types');
    $bundle = $form_state->getValue('bundles');

    foreach ($selected_fields as $field_name => $value) {
      if ($value) {
        // Debugging: Display the entity type, field name, and bundle
        \Drupal::messenger()->addMessage("Attempting to load field: $field_name of entity type: $entity_type in bundle: $bundle");

        $field_config = FieldConfig::loadByName($entity_type, $bundle, $field_name);

        if ($field_config) {
          $field_config->delete();
          \Drupal::messenger()->addMessage($this->t('Field %field has been deleted from the %bundle bundle.', ['%field' => $field_name, '%bundle' => $bundle]));
        } else {
          // Error message if field is not found
          \Drupal::messenger()->addError("Field $field_name not found in entity type $entity_type and bundle $bundle.");
        }
      }
    }
  }
}



