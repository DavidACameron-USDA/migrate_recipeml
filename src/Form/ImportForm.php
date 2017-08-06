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
   * Uploaded file entity.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $file;

  /**
   * {@inheritdoc}
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
   * The source configuration form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildSourceForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->t('Import RecipeML');

    $form['file'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload a RecipeML file'),
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
   * The source configuration form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateSourceForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['xml']];
    $this->file = file_save_upload('file', $validators, 'temporary://', 0, FILE_EXISTS_REPLACE);

    // Ensure we have the file uploaded.
    if (!$this->file) {
      $form_state->setErrorByName('file', $this->t('File to import not found.'));
    }
  }

  /**
   * The source configuration form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitSourceForm(array &$form, FormStateInterface $form_state) {
    // Indicate the next step is confirmation.
    $form_state->setValue('step', 'confirm');
    $form_state->setRebuild();
  }

  /**
   * The confirm form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildConfirmForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['filename'] = [
      '#markup' => '<p>' . $this->t('<strong>File:</strong> :filename', [':filename' => $this->file->getFilename()]) . '</p>',
    ];
    $form['actions']['submit']['#submit'] = ['::submitConfirmForm'];
    $form['actions']['submit']['#value'] = $this->t('Import');

    return $form;
  }

  /**
   * The confirm form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
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
          [$migrations, ['source_url' => $this->file->getFileUri()]],
        ],
      ],
      'finished' => [MigrateRecipeMlImportBatch::class, 'finished'],
    ];
    batch_set($batch);
    $form_state->setRedirect('system.admin_content');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('migrate_recipeml.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure?');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This method is intentionally empty, see the specific submit methods for
    // each form step.
  }

}
