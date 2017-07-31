<?php

namespace Drupal\migrate_recipeml\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for all RecipeML source plugins.
 */
abstract class RecipeMLBase extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The XMLReader we are encapsulating.
   *
   * @var \XMLReader
   */
  protected $reader;

  /**
   * The URL of the source RecipeML.
   *
   * @var string
   */
  protected $sourceUrl;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    // The plugin may be instantiated during non-import Migrate operations.  In
    // those cases, the config array won't contain the source_url value.
    if (isset($configuration['constants']['source_url'])) {
      $this->sourceUrl = $configuration['constants']['source_url'];

      $this->reader = new \XMLReader();
      $this->reader->open($this->sourceUrl, NULL, \LIBXML_NOWARNING);

      // Suppress errors during parsing, so we can pick them up after.
      libxml_use_internal_errors(TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIterator() {
    return new \ArrayIterator($this->parseRecipeML());
  }

  /**
   * Parses RecipeML into an array of data.
   */
  abstract protected function parseRecipeML();

  /**
   * Builds a \SimpleXmlElement rooted at the iterator's current location.
   *
   * The resulting SimpleXmlElement also contains any child nodes of the current
   * element.
   *
   * Shamelessly copied from the Migrate Plus module's XML parser.
   *
   * @return \SimpleXmlElement|false
   *   A \SimpleXmlElement when the document is parseable, or false if a
   *   parsing error occurred.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function getSimpleXml() {
    $node = $this->reader->expand();
    if ($node) {
      // We must associate the DOMNode with a DOMDocument to be able to import
      // it into SimpleXML. Despite appearances, this is almost twice as fast as
      // simplexml_load_string($this->readOuterXML());
      $dom = new \DOMDocument();
      $node = $dom->importNode($node, TRUE);
      $dom->appendChild($node);
      $sxml_elem = simplexml_import_dom($node);
      // $this->registerNamespaces($sxml_elem);
      return $sxml_elem;
    }
    else {
      foreach (libxml_get_errors() as $error) {
        $error_string = self::parseLibXmlError($error);
        throw new MigrateException($error_string);
      }
      return FALSE;
    }
  }

  /**
   * Returns the values found in a SimpleXMLElement at a given XPath.
   *
   * @param \SimpleXMLElement $element
   *   The parent element to search within.
   * @param string $xpath
   *   An xpath within the element.
   *
   * @return array|mixed
   *   An array of values at the xpath or a string if there was only one value.
   */
  protected function getValuesByXPath(\SimpleXMLElement $element, $xpath) {
    $values = [];
    foreach ($element->xpath($xpath) as $value) {
      $values[] = (string) $value;
    }
    if (count($values) == 1) {
      $values = reset($values);
    }
    return $values;
  }

  /**
   * Parses a LibXMLError to a error message string.
   *
   * @param \LibXMLError $error
   *   Error thrown by the XML.
   *
   * @return string
   *   Error message
   */
  public static function parseLibXmlError(\LibXMLError $error) {
    $error_code_name = 'Unknown Error';
    switch ($error->level) {
      case LIBXML_ERR_WARNING:
        $error_code_name = t('Warning');
        break;

      case LIBXML_ERR_ERROR:
        $error_code_name = t('Error');
        break;

      case LIBXML_ERR_FATAL:
        $error_code_name = t('Fatal Error');
        break;
    }

    return t(
      "@libxmlerrorcodename @libxmlerrorcode: @libxmlerrormessage\n" .
      "Line: @libxmlerrorline\n" .
      "Column: @libxmlerrorcolumn\n" .
      "File: @libxmlerrorfile",
      [
        '@libxmlerrorcodename' => $error_code_name,
        '@libxmlerrorcode' => $error->code,
        '@libxmlerrormessage' => trim($error->message),
        '@libxmlerrorline' => $error->line,
        '@libxmlerrorcolumn' => $error->column,
        '@libxmlerrorfile' => (($error->file)) ? $error->file : NULL,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return $this->sourceUrl;
  }

}
