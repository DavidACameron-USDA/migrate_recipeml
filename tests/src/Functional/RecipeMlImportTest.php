<?php

namespace Drupal\Tests\migrate_recipeml\Functional;

use Drupal\Tests\BrowserTestBase;

class RecipeMlImportTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  public static $modules = [
    'ingredient',
    'recipe',
    'migrate',
    'migrate_recipeml',
  ];

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create and log in the admin user with Recipe content permissions.
    $permissions = [
      'create recipe content',
      'import recipeml',
      'view ingredient'
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test import recipes in RecipeML format with the import form.
   */
  public function testRecipeMlImport() {
    $this->drupalGet('admin/content/import-recipeml');


    // Import the RecipeML test file using the import form.
    $edit = array(
      'url' => 'http://localhost/drupal8/modules/migrate_recipeml/tests/test_recipes.xml',
    );
    $this->drupalPostForm('admin/content/import-recipeml', $edit, 'Continue');

    // Check for the confirm form.
    $this->assertSession()->pageTextContains('Are you sure?');
    $this->assertSession()->pageTextContains('Source URL: http://localhost/drupal8/modules/migrate_recipeml/tests/test_recipes.xml');
    $this->drupalPostForm(NULL, [], 'Import');

    // Check for the confirmation messages.
    $this->assertSession()->pageTextContains('Completed 2 import tasks successfully');
    $this->assertSession()->pageTextContains('Your recipes were imported.');

    // Verify that the first recipe was imported correctly.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('Salt water');
    $this->assertSession()->pageTextContains('1 servings');
    $this->assertSession()->pageTextContains('John Doe');
    $this->assertSession()->pageTextContains('2 c');
    $this->assertSession()->pageTextContains('water (cold)');
    $this->assertSession()->pageTextContains('1 T');
    $this->assertSession()->pageTextContains('salt');
    $this->assertSession()->pageTextContains('Combine water and salt in a glass.');
    $this->assertSession()->pageTextContains('Stir.');
    $this->assertSession()->pageTextContains('Do not consume!');

    // Verify that the second recipe was imported correctly.
    $this->drupalGet('node/2');
    $this->assertSession()->pageTextContains('Hard-boiled eggs');
    $this->assertSession()->pageTextContains('2 servings');
    $this->assertSession()->pageTextContains('Jane Doe');
    $this->assertSession()->pageTextContains('2 q');
    $this->assertSession()->pageTextContains('water (hot)');
    $this->assertSession()->responseContains('<span class="quantity-unit">4</span>');
    $this->assertSession()->pageTextContains('eggs');
    $this->assertSession()->pageTextContains('Boil the water.');
    $this->assertSession()->pageTextContains('Put the eggs in the boiling water for 5 minutes.');
    $this->assertSession()->pageTextContains('Allow the eggs to cool.');
    $this->assertSession()->pageTextContains('Break the shells and consume.');
  }

}
