<?php

/**
 * @file
 * Contains Drupal\browscap\Tests\MockBrowscapEndpoint.
 */

namespace Drupal\browscap\Tests;

use Drupal\browscap\BrowscapEndpoint;

class MockBrowscapEndpoint extends BrowscapEndpoint {

  const BROWSCAP_IMPORT_OK = 0;
  const BROWSCAP_IMPORT_VERSION_ERROR = 1;
  const BROWSCAP_IMPORT_NO_NEW_VERSION = 2;
  const BROWSCAP_IMPORT_DATA_ERROR = 3;

  public function getBrowscapData($cron = TRUE) {
    // Check the local browscap data version number.
    $config = \Drupal::config('browscap.settings');

    $local_version = $config->get('version');
    $fake_version = $local_version . '1';

    $ini_path = drupal_get_path('module', 'browscap') . '/src/Tests/test_browscap_data.ini';
    $browscap_data = file_get_contents($ini_path);

    return array($fake_version, $browscap_data);
  }
}