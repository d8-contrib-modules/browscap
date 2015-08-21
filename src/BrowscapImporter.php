<?php

/**
 * @file
 * Contains Drupal\browscap\BrowscapImporter.
 */

namespace Drupal\browscap;

use Drupal\Core\Database\Database;

/**
 * Class BrowscapImporter.
 *
 * @package Drupal\browscap
 */
class BrowscapImporter {

  const BROWSCAP_IMPORT_OK = 0;
  const BROWSCAP_IMPORT_VERSION_ERROR = 1;
  const BROWSCAP_IMPORT_NO_NEW_VERSION = 2;
  const BROWSCAP_IMPORT_DATA_ERROR = 3;

  /**
   * Helper function to update the browscap data.
   *
   * @param bool $cron
   *   Optional import environment. If false, display status messages to the user
   *   in addition to logging information with the watchdog.
   *
   * @return int
   *   A code indicating the result:
   *   - BROWSCAP_IMPORT_OK: New data was imported.
   *   - BROWSCAP_IMPORT_NO_NEW_VERSION: No new data version was available.
   *   - BROWSCAP_IMPORT_VERSION_ERROR: Checking the current data version failed.
   *   - BROWSCAP_IMPORT_DATA_ERROR: The data could not be downloaded or parsed.
   */
  static function import(BrowscapEndpoint $browscap, $cron = TRUE) {
    // Check the local browscap data version number.
    $config = \Drupal::configFactory()->getEditable('browscap.settings');

    $browscap_data = $browscap->getBrowscapData($cron);
    $current_version = array_shift($browscap_data);
    $browscap_data = array_shift($browscap_data);

    // Process the browscap data.
    $result = static::processData($browscap_data);
    //If it's not an array, it's an error.
    if ($result != static::BROWSCAP_IMPORT_OK) {
      return FALSE;
    }

    // Clear the browscap data cache.
    \Drupal::cache('browscap')->invalidateAll();

    // Update the browscap version and imported time.
    $config->set('version', $current_version)
      ->set('imported', REQUEST_TIME)
      ->save();

    // Log a message with the watchdog.
    \Drupal::logger('browscap')->notice('New version of browscap imported: %version', array('%version' => $current_version));

    // Display a message to user if the update process was triggered manually.
    if ($cron == FALSE) {
      drupal_set_message(t('New version of browscap imported: %version', array('%version' => $current_version)));
    }

    return TRUE;
  }

  /**
   * Saves parsed Browscap data.
   *
   * The purpose of this function is to perform the queries on the {browscap}
   * table as a transaction. This vastly improves performance with database
   * engines such as InnoDB and ensures that queries will work while new data
   * is being imported.
   *
   * @param array $browscap_data
   *   Browscap data that has been parsed with parse_ini_string() or
   *   parse_ini_file().
   */
  private static function processData(&$browscap_data) {
    // Start a transaction. The variable is unused. That's on purpose.
    $transaction = Database::getConnection()->startTransaction();;

    // Delete all data from database.
    Database::getConnection()->delete('browscap')->execute();

    // Skip the header division.
    $header_division = static::getNextIniDivision($browscap_data);
    // Assert that header division less than length of entire INI string.
    if (strlen($header_division) >= strlen($browscap_data)) {
      return static::BROWSCAP_IMPORT_DATA_ERROR;
    }

    // Skip the version division.
    $version_divison = static::getNextIniDivision($browscap_data);
    // Assert that Version section in division string.
    if (strpos($version_divison, "Browscap Version") === FALSE) {
      return static::BROWSCAP_IMPORT_DATA_ERROR;
    }

    // Get default properties division.
    // Assumption: The default properties division is the third division.
    $default_properties_division = static::getNextIniDivision($browscap_data);
    // Assert that DefaultProperties section in division string.
    if (strpos($default_properties_division, "[DefaultProperties]") === FALSE) {
      return static::BROWSCAP_IMPORT_DATA_ERROR;
    }

    // Parse and save remaining divisions.
    while ($division = static::getNextIniDivision($browscap_data)) {
      // The division is concatenated with the default properties division
      // because each division has at least one section that inherits properties
      // from the default properties section.
      $divisions = $default_properties_division . $division;
      $parsed_divisions = static::parseData($divisions);
      if (!$parsed_divisions) {
        // There was an error parsing the data.
        return static::BROWSCAP_IMPORT_DATA_ERROR;
      }
      static::saveParsedData($parsed_divisions);
    }
    return static::BROWSCAP_IMPORT_OK;
  }

  private static function parseData(&$browscap_data) {
    // Parse the returned browscap data.
    // The parse_ini_string function is preferred but only available in PHP 5.3.0.
    if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
      // Replace 'true' and 'false' with '1' and '0'
      $browscap_data = preg_replace(
        array(
          "/=\s*true\s*\n/",
          "/=\s*false\s*\n/",
        ),
        array(
          "=1\n",
          "=0\n",
        ),
        $browscap_data
      );

      // Parse the browscap data as a string.
      $browscap_data = parse_ini_string($browscap_data, TRUE, INI_SCANNER_RAW);
    } else {
      // Create a path and filename
      $server = $_SERVER['SERVER_NAME'];
      $path = \Drupal::config('system.file')->get('path.temporary');
      $file = "$path/browscap_$server.ini";

      // Write the browscap data to a file
      $browscap_file = fopen($file, "w");
      fwrite($browscap_file, $browscap_data);
      fclose($browscap_file);

      // Parse the browscap data as a file
      $browscap_data = parse_ini_file($file, TRUE);
    }

    return $browscap_data;
  }

  private static function saveParsedData(&$browscap_data) {
    // Prepare the data for insertion.
    $import_data = array();
    foreach ($browscap_data as $key => $values) {
      // Store the current value.
      $original_values = $values;

      // Replace '*?' with '%_'.
      $user_agent = strtr($key, '*?', '%_');

      // Remove trailing spaces to prevent "duplicate entry" errors. Databases
      // such as MySQL do not preserve trailing spaces when storing VARCHARs.
      $user_agent = rtrim($user_agent);

      // Change all array keys to lowercase.
      $original_values = array_change_key_case($original_values);

      // Add to array of data to import.
      $import_data[$user_agent] = $original_values;

      // Remove processed data to reduce memory usage.
      unset($browscap_data[$key]);
    }

    $query = db_insert('browscap')->fields(array('useragent', 'data'));
    foreach ($import_data as $user_agent => $values) {
      // Recurse through the available user agent information.
      $previous_parent = NULL;
      $parent = isset($values['parent']) ? $values['parent'] : FALSE;
      while ($parent && $parent !== $previous_parent) {
        $parent_values = isset($import_data[$parent]) ? $import_data[$parent] : array();
        $values = array_merge($parent_values, $values);
        $previous_parent = $parent;
        $parent = isset($parent_values['parent']) ? $parent_values['parent'] : FALSE;
      }

      // Do not import DefaultProperties user agent.
      // It is currently only needed for inheriting properties prior to import.
      if ($user_agent == 'DefaultProperties') {
        continue;
      }

      $query->values(array(
        'useragent' => $user_agent,
        'data' => serialize($values),
      ));
    }
    $query->execute();
  }

  private static function getNextIniDivision(&$ini) {
    static $offset = 0;
    $division_begin = $offset;
    $division_end = static::findIniDivisionEnd($ini, $division_begin);
    $division_length = $division_end - $division_begin;
    $division = substr($ini, $division_begin, $division_length);
    $offset += $division_length;
    return $division;
  }

  private static function findIniDivisionEnd(&$ini, $division_begin) {
    $header_prefix = ';;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;';

    // Start search from one character after offset so the header at the
    // beginning of the part is not matched.
    $offset = $division_begin + 1;

    $division_end = strpos($ini, $header_prefix, $offset);
    // When the beginning of the next division cannot be found, the end of the
    // INI string has been reached.
    if ($division_end === FALSE) {
      $division_end = strlen($ini) - 1;
    }

    return $division_end;
  }
}