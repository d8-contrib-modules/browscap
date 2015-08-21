<?php

/**
 * @file
 * Contains Drupal\browscap\BrowscapEndpoint.
 */

namespace Drupal\browscap;

class BrowscapEndpoint{

  public function getBrowscapData($cron = TRUE) {
    // Check the local browscap data version number.
    $config = \Drupal::config('browscap.settings');

    $local_version = $config->get('version');
    \Drupal::logger('browscap')->notice('Checking for new browscap version...');

    // Retrieve the current browscap data version number using HTTP
    $client = \Drupal::httpClient();
    try {
      $response = $client->get('http://www.browscap.org/version-number');
      // Expected result.
      $current_version = (string) $response->getBody();
    } catch (RequestException $e) {
      \Drupal::logger('browscap')->error($e->getMessage());
    }

    // Log an error if the browscap version number could not be retrieved
    if (isset($current_version->error)) {
      // Log a message with the watchdog
      \Drupal::logger('browscap')
        ->error("Couldn't check version: %error", array('%error' => $current_version->error));

      // Display a message to the user if the update process was triggered manually
      if ($cron == FALSE) {
        drupal_set_message(t("Couldn't check version: %error", array('%error' => $current_version->error)), 'error');
      }
      return BrowscapImporter::BROWSCAP_IMPORT_VERSION_ERROR;
    }

    // Sanitize the returned version number
    $current_version = SafeMarkup::checkPlain(trim($current_version));

    // Compare the current and local version numbers to determine if the browscap
    // data requires updating.
    if ($current_version == $local_version) {
      // Log a message with the watchdog.
      \Drupal::logger('browscap')->info('No new version of browscap to import');

      // Display a message to user if the update process was triggered manually.
      if ($cron == FALSE) {
        drupal_set_message(t('No new version of browscap to import'));
      }

      return BrowscapImporter::BROWSCAP_IMPORT_NO_NEW_VERSION;
    }

    // Set options for downloading data with or without compression.
    /*if (function_exists('gzdecode')) {
      $options = array(
        'headers' => array('Accept-Encoding' => 'gzip'),
      );
    }
    else {*/
    // The download takes over ten times longer without gzip, and may exceed
    // the default timeout of 30 seconds, so we increase the timeout.
    $options = array('timeout' => 600);
    //}

    // Retrieve the browscap data using HTTP
    try {
      $response = $client->get('http://www.browscap.org/stream?q=PHP_BrowsCapINI', $options);
      $browscap_data = (string) $response->getBody();
      // Expected result.
    } catch (RequestException $e) {
      watchdog_exception('browscap', $e->getMessage());
    }

    // Log an error if the browscap data could not be retrieved
    if (isset($response->error) || empty($response)) {
      // Log a message with the watchdog
      \Drupal::logger('browscap')
        ->error("Couldn't retrieve updated browscap: %error", array('%error' => $browscap_data->error));

      // Display a message to the user if the update process was triggered manually
      if ($cron == FALSE) {
        drupal_set_message(t("Couldn't retrieve updated browscap: %error", array('%error' => $response->error)), 'error');
      }

      return BrowscapImporter::BROWSCAP_IMPORT_DATA_ERROR;
    }

    // Decompress the downloaded data if it is compressed.
    /*if (function_exists('gzdecode')) {
      $browscap_data = gzdecode($browscap_data);
    }*/

    return array($current_version, $browscap_data);
  }
}