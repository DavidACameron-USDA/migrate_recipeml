<?php

namespace Drupal\migrate_recipeml\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\migrate_recipeml\Batch\MigrateRecipeMlImportBatch;

/**
 * Provides a form for importing RecipeML.
 */
class ImportForm extends ConfirmFormBase {

  /**
   * (@inheritdoc}.
   */
  public function getFormId() {
    return 'import_recipeml';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $step = $form_state->getValue('step', 'source');
    switch ($step) {
      case 'source':
        return $this->buildSourceForm($form, $form_state);

      case 'confirm':
        return $this->buildConfirmForm($form, $form_state);

      default:
        drupal_set_message($this->t('Unrecognized form step @step', ['@step' => $step]), 'error');
        return [];
    }
  }

  /**
   *
   */
  public function buildSourceForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->t('Import RecipeML');

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Source file URL'),
      '#description' => $this->t('Enter the URL of a file containing RecipeML for import.'),
      '#default_value' => '',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#button_type' => 'primary',
      '#validate' => ['::validateSourceForm'],
      '#submit' => ['::submitSourceForm'],
    ];
    return $form;
  }

  /**
   *
   */
  public function validateSourceForm(array &$form, FormStateInterface $form_state) {
    // Store the source URL in form storage.
    $form_state->set('source_url', $form_state->getValue('url'));
  }

  /**
   *
   */
  public function submitSourceForm(array &$form, FormStateInterface $form_state) {
    // Indicate the next step is confirmation.
    $form_state->setValue('step', 'confirm');
    $form_state->setRebuild();
  }

  /**
   *
   */
  public function buildConfirmForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['source_url'] = [
      '#markup' => '<p>' . $this->t('<strong>Source URL:</strong> :source_url', [':source_url' => $form_state->get('source_url')]) . '</p>',
    ];
    $form['actions']['submit']['#submit'] = ['::submitConfirmForm'];
    $form['actions']['submit']['#value'] = $this->t('Import');

    return $form;
  }

  /**
   *
   */
  public function submitConfirmForm(array &$form, FormStateInterface $form_state) {
    $migrations = [
      'recipeml_ingredient',
      'recipeml',
    ];

    $batch = [
      'title' => $this->t('Importing recipes'),
      'progress_message' => '',
      'operations' => [
        [
          [MigrateRecipeMlImportBatch::class, 'run'],
          [$migrations, ['source_url' => $form_state->get('source_url')]],
        ],
      ],
      'finished' => [MigrateRecipeMlImportBatch::class, 'finished'],
    ];
    batch_set($batch);
    $form_state->setRedirect('system.admin_content');
  }

  /**
   * (@inheritdoc}.
   */
  public function getCancelUrl() {
    return new Url('migrate_recipeml.admin');
  }

  /**
   * (@inheritdoc}.
   */
  public function getQuestion() {
    return $this->t('Are you sure?');
  }

  /**
   * (@inheritdoc}.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This method is intentionally empty, see the specific submit methods for
    // each form step.
  }

}
