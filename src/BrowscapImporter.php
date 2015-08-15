<?php

/**
 * @file
 * Contains Drupal\browscap\BrowscapImporter.
 */

namespace Drupal\browscap;

use Drupal\Component\Utility\SafeMarkup;

/**
 * Class BrowscapImporter.
 *
 * @package Drupal\browscap
 */
class BrowscapImporter {
  /**
   * Helper function to update the browscap data.
   *
   * @param boolean $cron
   *   Optional import environment. If false, display status messages to the user in addition to logging information with the watchdog.
   */
  static function import($cron = TRUE) {
    // Check the local browscap data version number
    $config = \Drupal::configFactory()->getEditable('browscap.settings');

    $local_version = $config->get('browscap_version');

    // Retrieve the current browscap data version number using HTTP
    $client = \Drupal::httpClient();
    try {
      $response = $client->get('http://www.browscap.org/version-number');
      // Expected result.
      $current_version = $response->getBody();
    }
    catch (RequestException $e) {
      watchdog_exception('browscap', $e->getMessage());
    }

    // Log an error if the browscap version number could not be retrieved
    if (isset($current_version->error)) {
      // Log a message with the watchdog
      watchdog('browscap', "Couldn't check version: %error", array('%error' => $current_version->error), WATCHDOG_ERROR);

      // Display a message to the user if the update process was triggered manually
      if ($cron == FALSE) {
        drupal_set_message(t("Couldn't check version: %error", array('%error' => $current_version->error)), 'error');
      }

      return;
    }

    // Sanitize the returned version number
    $current_version = SafeMarkup::checkPlain(trim($current_version));

    // Compare the current and local version numbers to determine if the browscap
    // data requires updating
    if ($current_version == $local_version) {
      // Log a message with the watchdog
      watchdog('browscap', 'No new version of browscap to import');

      // Display a message to the user if the update process was triggered manually
      if ($cron == FALSE) {
        drupal_set_message(t('No new version of browscap to import'));
      }

      return;
    }

    // Retrieve the browscap data using HTTP
    try {
      $response = $client->get('http://www.browscap.org/stream?q=PHP_BrowsCapINI');
      // Expected result.
      $browscap_data = $response->getBody();
    }
    catch (RequestException $e) {
      watchdog_exception('browscap', $e->getMessage());
    }

    // Log an error if the browscap data could not be retrieved
    if (isset($browscap_data->error) || empty($browscap_data)) {
      // Log a message with the watchdog
      \Drupal::logger('browscap')->error("Couldn't retrieve updated browscap: %error", array('%error' => $browscap_data->error));

      // Display a message to the user if the update process was triggered manually
      if ($cron == FALSE) {
        drupal_set_message(t("Couldn't retrieve updated browscap: %error", array('%error' => $browscap_data->error)), 'error');
      }

      return;
    }

    // Parse the returned browscap data
    // The parse_ini_string function is preferred but only available in PHP 5.3.0
    if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
      // Retrieve the browscap data
      $browscap_data = $browscap_data;

      // Replace 'true' and 'false' with '1' and '0'
      $browscap_data = strtr($browscap_data, array(
        "=true\r" => "=1\r",
        "=false\r" => "=0\r"
      ));

      // Parse the browscap data as a string
      $browscap_data = parse_ini_string($browscap_data, TRUE, INI_SCANNER_RAW);
    }
    else {
      // Create a path and filename
      $server = $_SERVER['SERVER_NAME'];
      $path = \Drupal::config('system.file')->get('path.temporary');
      $file = "$path/browscap_$server.ini";

      // Write the browscap data to a file
      $browscap_file = fopen($file, "w");
      fwrite($browscap_file, $browscap_data->data);
      fclose($browscap_file);

      // Parse the browscap data as a file
      $browscap_data = parse_ini_file($file, TRUE);
    }

    if ($browscap_data) {
      // Find the version information
      // The version information is the first entry in the array
      $version = array_shift($browscap_data);

      // Store the data available for each user agent
      foreach ($browscap_data as $key => $values) {
        // Store the current value
        $e = $values;

        // Create an array to hold the last parent
        $last_parent = array();

        // Recurse through the available user agent information
        while (isset($values['Parent']) && $values['Parent'] !== $last_parent) {
          $values = isset($browscap_data[$values['Parent']]) ? $browscap_data[$values['Parent']] : array();
          $e = array_merge($values, $e);
          $last_parent = $values;
        }

        // Replace '*?' with '%_'
        $user_agent = strtr($key, '*?', '%_');

        // Change all array keys to lowercase
        $e = array_change_key_case($e);

        // Delete all data about the current user agent from the database
        db_delete('browscap')
          ->condition('useragent', $user_agent)
          ->execute();

        // Insert all data about the current user agent into the database
        db_insert('browscap')
          ->fields(array(
            'useragent' => $user_agent,
            'data' => serialize($e)
          ))
          ->execute();
      }

      echo "TEST3";

      // Clear the browscap data cache
      \Drupal::cache('browscap')->invalidateAll();

      // Update the browscap version
      $config->set('browscap_version', $current_version);

      // Log a message with the watchdog
      \Drupal::logger('browscap')->notice('New version of browscap imported: %version', array('%version' => $current_version));

      // Display a message to the user if the update process was triggered manually
      if ($cron == FALSE) {
        drupal_set_message(t('New version of browscap imported: %version', array('%version' => $current_version)));
      }
    }
  }
}