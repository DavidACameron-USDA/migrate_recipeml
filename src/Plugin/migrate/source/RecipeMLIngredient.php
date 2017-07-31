<?php

/**
 * @file
 * Copyright (c) FormatData. All rights reserved.
 *
 * Distribution of RecipeML Processing Software in source and/or binary forms is
 * permitted provided that the following conditions are met:
 * - Distributions in source code must retain the above copyright notice and
 *   this list of conditions.
 * - Distributions in binary form must reproduce the above copyright notice and
 *   this list of conditions in the documentation and/or other materials
 *   provided with the distribution.
 * - All advertising materials and documentation for RecipeML Processing
 *   Software must display the following acknowledgment:
 *   "This product is RecipeML compatible."
 * - Names associated with RecipeML or FormatData must not be used to endorse or
 *   promote RecipeML Processing Software without prior written permission from
 *   FormatData. For written permission, please contact RecipeML@formatdata.com.
 */

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
