<?php

namespace Drupal\migrate_recipeml\Plugin\migrate\source;

/**
 * A source that reads RecipeML markup.
 *
 * @MigrateSource(
 *   id = "recipeml_ingredient"
 * )
 */
class RecipeMLIngredient extends RecipeMLBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'name' => $this->t("The ingredient's name"),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function parseRecipeML() {
    $data = [];

    // Check all of the nodes in the RecipeML file.  Look for <ing> elements.
    while ($this->reader->read()) {
      if ($this->reader->nodeType == \XMLReader::ELEMENT && $this->reader->localName == 'ing') {
        $ingredient_data = [];
        $ingredient = $this->getSimpleXml();
        if ($ingredient !== FALSE && !is_null($ingredient)) {
          // Extract data from the recipe's subelements.
          $ingredient_data['name'] = $this->getValuesByXPath($ingredient, 'item');
        }
        $data[] = $ingredient_data;
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return ['name' => ['type' => 'string']];
  }

}
