<?php

namespace Drupal\Tests\migrate_recipeml\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate_recipeml\Batch\MigrateRecipeMlMessageCapture;

/**
 * Tests the functionality of the Migrate RecipeML import plugins.
 */
class RecipeMlImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'ingredient',
    'recipe',
    'migrate',
    'migrate_recipeml',
    'node',
    'path',
    'rdf',
    'system',
    'text',
    'user',
  ];

  /**
   * @covers \Drupal\migrate_recipeml\Plugin\migrate\source\RecipeMLIngredient
   */
  public function testIngredientImport() {
    $this->installEntitySchema('ingredient');

    $configuration = [
      'source' => [
        'constants' => [
          'source_url' => drupal_get_path('module', 'migrate_recipeml') . '/tests/test_recipes.xml',
        ],
      ],
    ];
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createInstance('recipeml_ingredient', $configuration);

    // Run the Ingredient migration.
    $messages = new MigrateRecipeMlMessageCapture();
    $executable = new MigrateExecutable($migration, $messages);
    $executable->import();

    // Check for the three ingredients from the RecipeML file.
    /** @var \Drupal\Core\Entity\EntityTypeManager $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    /** @var \Drupal\ingredient\IngredientInterface[] $ingredients */
    $ingredients = $entity_type_manager->getStorage('ingredient')->loadMultiple();
    $this->assertEquals(3, count($ingredients), 'Found three ingredients.');
    $this->assertEquals('water', $ingredients[1]->label(), 'Found the first ingredient.');
    $this->assertEquals('salt', $ingredients[2]->label(), 'Found the second ingredient.');
    $this->assertEquals('eggs', $ingredients[3]->label(), 'Found the third ingredient.');
  }

  /**
   * @covers \Drupal\migrate_recipeml\Plugin\migrate\source\RecipeML
   */
  public function testRecipeImport() {
    // Run the ingredient import test since it will create the ingredient
    // entities that the recipes will need to reference.
    $this->testIngredientImport();

    $this->installEntitySchema('node_type');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    $this->installConfig(['node', 'recipe']);

    // Create a minimal ingredient.units configuration object so that
    // ingredient_unit_fuzzymatch() will work.
    $this->createUnits();

    $configuration = [
      'source' => [
        'constants' => [
          'source_url' => drupal_get_path('module', 'migrate_recipeml') . '/tests/test_recipes.xml',
        ],
      ],
    ];
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createInstance('recipeml', $configuration);

    // Run the Ingredient migration.
    $messages = new MigrateRecipeMlMessageCapture();
    $executable = new MigrateExecutable($migration, $messages);
    $executable->import();

    // Check for the two recipes from the RecipeML file.
    /** @var \Drupal\Core\Entity\EntityTypeManager $entity_type_manager */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $entity_type_manager->getStorage('node')->loadMultiple();
    $this->assertEquals(2, count($nodes), 'Found two nodes.');

    // Verify that the first recipe was imported correctly.
    $this->assertEquals('Salt water', $nodes[1]->label());
    $this->assertEquals('Basic salt water.', $nodes[1]->recipe_description->value);
    $this->assertEquals('Servings', $nodes[1]->recipe_yield_unit->value);
    $this->assertEquals('1', $nodes[1]->recipe_yield_amount->value);
    $this->assertEquals('John Doe', trim($nodes[1]->recipe_source->value));
    $first_ingredient = [
      'target_id' => '1',
      'quantity' => '2',
      'unit_key' => 'cup',
      'note' => 'cold',
    ];
    $this->assertEquals($first_ingredient, $nodes[1]->get('recipe_ingredient')->getValue()[0]);
    $second_ingredient = [
      'target_id' => '2',
      'quantity' => '1',
      'unit_key' => 'tablespoon',
      'note' => '',
    ];
    $this->assertEquals($second_ingredient, $nodes[1]->get('recipe_ingredient')->getValue()[1]);
    $this->assertEquals('Combine water and salt in a glass.

Stir.', trim($nodes[1]->recipe_instructions->value));
    $this->assertEquals('Do not consume!', trim($nodes[1]->recipe_notes->value));

    // Verify that the second recipe was imported correctly.
    $this->assertEquals('Hard-boiled eggs', $nodes[2]->label());
    $this->assertEquals('Basic hard-boiled eggs.', $nodes[2]->recipe_description->value);
    $this->assertEquals('Servings', $nodes[2]->recipe_yield_unit->value);
    $this->assertEquals('2', $nodes[2]->recipe_yield_amount->value);
    $this->assertEquals('Jane Doe', trim($nodes[2]->recipe_source->value));
    $first_ingredient = [
      'target_id' => '1',
      'quantity' => '2',
      'unit_key' => 'us liquid quart',
      'note' => 'hot',
    ];
    $this->assertEquals($first_ingredient, $nodes[2]->get('recipe_ingredient')->getValue()[0]);
    $second_ingredient = [
      'target_id' => '3',
      'quantity' => '4',
      'unit_key' => '',
      'note' => '',
    ];
    $this->assertEquals($second_ingredient, $nodes[2]->get('recipe_ingredient')->getValue()[1]);
    $this->assertEquals('Boil the water.

Put the eggs in the boiling water for 5 minutes.

Allow the eggs to cool.

Break the shells and consume.', trim($nodes[2]->recipe_instructions->value));
  }

  /**
   * Creates a set of units in configuration to use for testing.
   *
   * Doing this prevents tests from having to load all of the Ingredient
   * module's default configuration.
   */
  protected function createUnits() {
    $units = [
      'unit_sets' => [
        'test' => [
          'name' => 'Test set',
          'units' => [
            'cup' => [
              'name' => 'cup',
              'abbreviation' => 'c',
            ],
            'tablespoon' => [
              'name' => 'tablespoon',
              'abbreviation' => 'T',
            ],
            'us liquid quart' => [
              'name' => 'quart',
              'abbreviation' => 'q',
            ],
          ],
        ],
      ],
    ];
    \Drupal::service('config.storage')->write('ingredient.units', $units);
  }

}
