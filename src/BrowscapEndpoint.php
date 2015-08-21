<?php

/**
 * @file
 * Contains Drupal\browscap\BrowscapEndpoint.
 */

namespace Drupal\browscap;

class BrowscapEndpoint{

  public function getVersion() {
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
      return BrowscapImporter::BROWSCAP_IMPORT_VERSION_ERROR;
    }

    // Sanitize the returned version number
    $current_version = SafeMarkup::checkPlain(trim($current_version));

    return $current_version;
  }

  public function getBrowscapData($cron = TRUE) {

    $client = \Drupal::httpClient();

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

    return $browscap_data;
  }
}