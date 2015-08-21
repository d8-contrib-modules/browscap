<?php

/**
 * @file
 * Contains Drupal\browscap\Tests\MockBrowscapEndpoint.
 */

namespace Drupal\browscap\Tests;

use Drupal\browscap\BrowscapEndpoint;

class MockBrowscapEndpoint extends BrowscapEndpoint {

  public function getVersion() {
    // Check the local browscap data version number.
    $config = \Drupal::config('browscap.settings');

    $local_version = $config->get('version');
    $fake_version = $local_version . '1';

    return $fake_version;
  }

  public function getBrowscapData($cron = TRUE) {
    $ini_path = drupal_get_path('module', 'browscap') . '/src/Tests/test_browscap_data.ini';
    $browscap_data = file_get_contents($ini_path);

    return $browscap_data;
  }
}