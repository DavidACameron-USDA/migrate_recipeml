<?php

namespace Drupal\migrate_recipeml\Batch;

use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateIdMapMessageEvent;
use Drupal\migrate\Event\MigrateMapDeleteEvent;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate_drupal_ui\Batch\MigrateMessageCapture;

/**
 * Runs a single migration batch.
 */
class MigrateRecipeMlImportBatch {

  /**
   * Maximum number of previous messages to display.
   */
  const MESSAGE_LENGTH = 20;

  /**
   * The processed items for one batch of a given migration.
   *
   * @var int
   */
  protected static $numProcessed = 0;

  /**
   * Ensure we only add the listeners once per request.
   *
   * @var bool
   */
  protected static $listenersAdded = FALSE;

  /**
   * The maximum length in seconds to allow processing in a request.
   *
   * @see self::run()
   *
   * @var int
   */
  protected static $maxExecTime;

  /**
   * MigrateMessage instance to capture messages during the migration process.
   *
   * @var \Drupal\migrate_drupal_ui\Batch\MigrateMessageCapture
   */
  protected static $messages;

  /**
   * Runs a single migrate batch import.
   *
   * @param int[] $initial_ids
   *   The full set of migration IDs to import.
   * @param array $config
   *   An array of additional configuration from the form.
   * @param array $context
   *   The batch context.
   */
  public static function run($initial_ids, $config, &$context) {
    if (!static::$listenersAdded) {
      $event_dispatcher = \Drupal::service('event_dispatcher');
      $event_dispatcher->addListener(MigrateEvents::POST_ROW_SAVE, [static::class, 'onPostRowSave']);
      $event_dispatcher->addListener(MigrateEvents::MAP_SAVE, [static::class, 'onMapSave']);
      $event_dispatcher->addListener(MigrateEvents::IDMAP_MESSAGE, [static::class, 'onIdMapMessage']);

      static::$maxExecTime = ini_get('max_execution_time');
      if (static::$maxExecTime <= 0) {
        static::$maxExecTime = 60;
      }
      // Set an arbitrary threshold of 3 seconds (e.g., if max_execution_time is
      // 45 seconds, we will quit at 42 seconds so a slow item or cleanup
      // overhead don't put us over 45).
      static::$maxExecTime -= 3;
      static::$listenersAdded = TRUE;
    }
    if (!isset($context['sandbox']['migration_ids'])) {
      $context['sandbox']['max'] = count($initial_ids);
      $context['sandbox']['current'] = 1;
      // Total number processed for this migration.
      $context['sandbox']['num_processed'] = 0;
      // migration_ids will be the list of IDs remaining to run.
      $context['sandbox']['migration_ids'] = $initial_ids;
      $context['sandbox']['messages'] = [];
      $context['results']['failures'] = 0;
      $context['results']['successes'] = 0;
    }

    // Number processed in this batch.
    static::$numProcessed = 0;

    $migration_id = reset($context['sandbox']['migration_ids']);
    $configuration = [];
    $configuration['source']['constants']['source_url'] = $config['source_url'];


    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id, $configuration);

    if ($migration) {
      static::$messages = new MigrateRecipeMlMessageCapture();
      $executable = new MigrateExecutable($migration, static::$messages);

      $migration_name = $migration->label() ? $migration->label() : $migration_id;

      try {
        $migration_status = $executable->import();
      }
      catch (\Exception $e) {
        \Drupal::logger('migrate_recipeml')->error($e->getMessage());
        $migration_status = MigrationInterface::RESULT_FAILED;
      }

      switch ($migration_status) {
        case MigrationInterface::RESULT_COMPLETED:
          // Store the number processed in the sandbox.
          $context['sandbox']['num_processed'] += static::$numProcessed;
          $message = new PluralTranslatableMarkup(
            $context['sandbox']['num_processed'], 'Imported @migration (processed 1 item total)', 'Imported @migration (processed @count items total)',
            ['@migration' => $migration_name]);
          $context['sandbox']['messages'][] = (string) $message;
          \Drupal::logger('migrate_recipeml')->notice($message);
          $context['sandbox']['num_processed'] = 0;
          $context['results']['successes']++;
          break;

        case MigrationInterface::RESULT_INCOMPLETE:
          $context['sandbox']['messages'][] = (string) new PluralTranslatableMarkup(
            static::$numProcessed, 'Continuing with @migration (processed 1 item)', 'Continuing with @migration (processed @count items)',
            ['@migration' => $migration_name]);
          $context['sandbox']['num_processed'] += static::$numProcessed;
          break;

        case MigrationInterface::RESULT_STOPPED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Operation stopped by request');
          break;

        case MigrationInterface::RESULT_FAILED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Operation on @migration failed', ['@migration' => $migration_name]);
          $context['results']['failures']++;
          \Drupal::logger('migrate_recipeml')->error('Operation on @migration failed', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_SKIPPED:
          $context['sandbox']['messages'][] = (string) new TranslatableMarkup('Operation on @migration skipped due to unfulfilled dependencies', ['@migration' => $migration_name]);
          \Drupal::logger('migrate_recipeml')->error('Operation on @migration skipped due to unfulfilled dependencies', ['@migration' => $migration_name]);
          break;

        case MigrationInterface::RESULT_DISABLED:
          // Skip silently if disabled.
          break;
      }

      // Unless we're continuing on with this migration, take it off the list.
      if ($migration_status != MigrationInterface::RESULT_INCOMPLETE) {
        array_shift($context['sandbox']['migration_ids']);
        $context['sandbox']['current']++;
      }

      // Add and log any captured messages.
      foreach (static::$messages->getMessages() as $message) {
        $context['sandbox']['messages'][] = (string) $message;
        \Drupal::logger('migrate_recipeml')->error($message);
      }

      // Only display the last MESSAGE_LENGTH messages, in reverse order.
      $message_count = count($context['sandbox']['messages']);
      $context['message'] = '';
      for ($index = max(0, $message_count - self::MESSAGE_LENGTH); $index < $message_count; $index++) {
        $context['message'] = $context['sandbox']['messages'][$index] . "<br />\n" . $context['message'];
      }
      if ($message_count > self::MESSAGE_LENGTH) {
        // Indicate there are earlier messages not displayed.
        $context['message'] .= '&hellip;';
      }
      // At the top of the list, display the next one (which will be the one
      // that is running while this message is visible).
      if (!empty($context['sandbox']['migration_ids'])) {
        $migration_id = reset($context['sandbox']['migration_ids']);
        $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
        $migration_name = $migration->label() ? $migration->label() : $migration_id;
        $context['message'] = (string) new TranslatableMarkup('Currently importing @migration (@current of @max total tasks)', [
            '@migration' => $migration_name,
            '@current' => $context['sandbox']['current'],
            '@max' => $context['sandbox']['max'],
          ]) . "<br />\n" . $context['message'];
      }
    }
    else {
      array_shift($context['sandbox']['migration_ids']);
      $context['sandbox']['current']++;
    }

    $context['finished'] = 1 - count($context['sandbox']['migration_ids']) / $context['sandbox']['max'];
  }

  /**
   * Callback executed when the Migrate RecipeML Import batch process completes.
   *
   * @param bool $success
   *   TRUE if batch successfully completed.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   An array of methods run in the batch.
   * @param string $elapsed
   *   The time to run the batch.
   */
  public static function finished($success, $results, $operations, $elapsed) {
    $successes = $results['successes'];
    $failures = $results['failures'];

    // If we had any successes log that for the user.
    if ($successes > 0) {
      drupal_set_message(\Drupal::translation()
        ->formatPlural($successes, 'Completed 1 import task successfully', 'Completed @count import tasks successfully'));
    }
    // If we had failures, log them and show the migration failed.
    if ($failures > 0) {
      drupal_set_message(\Drupal::translation()
        ->formatPlural($failures, '1 import failed', '@count imports failed'));
      drupal_set_message(t('Import process not completed'), 'error');
    }
    else {
      // Everything went off without a hitch. We may not have had successes
      // but we didn't have failures so this is fine.
      drupal_set_message(t('Your recipes were imported.'));
    }
  }

  /**
   * Reacts to item import.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The post-save event.
   */
  public static function onPostRowSave(MigratePostRowSaveEvent $event) {
    // We want to interrupt this batch and start a fresh one.
    if ((time() - \Drupal::time()->getRequestTime()) > static::$maxExecTime) {
      $event->getMigration()->interruptMigration(MigrationInterface::RESULT_INCOMPLETE);
    }
  }

  /**
   * Reacts to item deletion.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   The post-save event.
   */
  public static function onPostRowDelete(MigrateRowDeleteEvent $event) {
    // We want to interrupt this batch and start a fresh one.
    if ((time() - \Drupal::time()->getRequestTime()) > static::$maxExecTime) {
      $event->getMigration()->interruptMigration(MigrationInterface::RESULT_INCOMPLETE);
    }
  }

  /**
   * Counts up any map save events.
   *
   * @param \Drupal\migrate\Event\MigrateMapSaveEvent $event
   *   The map event.
   */
  public static function onMapSave(MigrateMapSaveEvent $event) {
    static::$numProcessed++;
  }

  /**
   * Counts up any map delete events.
   *
   * @param \Drupal\migrate\Event\MigrateMapDeleteEvent $event
   *   The map event.
   */
  public static function onMapDelete(MigrateMapDeleteEvent $event) {
    static::$numProcessed++;
  }

  /**
   * Displays any messages being logged to the ID map.
   *
   * @param \Drupal\migrate\Event\MigrateIdMapMessageEvent $event
   *   The message event.
   */
  public static function onIdMapMessage(MigrateIdMapMessageEvent $event) {
    if ($event->getLevel() == MigrationInterface::MESSAGE_NOTICE || $event->getLevel() == MigrationInterface::MESSAGE_INFORMATIONAL) {
      $type = 'status';
    }
    else {
      $type = 'error';
    }
    $source_id_string = implode(',', $event->getSourceIdValues());
    $message = t('Source ID @source_id: @message', ['@source_id' => $source_id_string, '@message' => $event->getMessage()]);
    static::$messages->display($message, $type);
  }

}
