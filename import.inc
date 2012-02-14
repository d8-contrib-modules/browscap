<?php
/**
 * @file
 * Browscap data import functions.
 */

/**
 * If there's a new version of browscap.csv, fetch it and update the
 * database.
 */
function _browscap_import($cron = TRUE) {
  // Politely check the version for updates before fetching the file
  $versionpage = drupal_http_request('http://browsers.garykeith.com/versions/version-number.asp');
  if (isset($versionpage->error)) {
    watchdog('browscap', 'Couldn\'t check version: %error', array('%error' => $versionpage->error), WATCHDOG_ERROR);
    if (!$cron) {
      drupal_set_message(t('Couldn\'t check version: %error', array('%error' => $versionpage->error)), 'error');
    }
    return;
  }
  $browscapversion = trim($versionpage->data);
  $oldversion = variable_get('browscap_version', 0);
  if ($browscapversion == $oldversion) {
    // No update, nothing to do here
    watchdog('browscap', 'No new version of browscap to import');
    if (!$cron) {
      drupal_set_message(t('No new version of browscap to import'));
    }
    return;
  }

  // Fetch the new version, and dump it in the temp directory
  $server = $_SERVER['SERVER_NAME'];
  $path = variable_get('file_temporary_path', '/tmp');
  $browscapfile = "$path/browscap_$server.ini";

  // @todo This probably ought to be in a settings variable.
  $browscap = drupal_http_request('http://browsers.garykeith.com/stream.asp?PHP_BrowsCapINI');
  if (isset($browscap->error) || empty($browscap)) {
    watchdog('browscap', "Couldn't retrieve updated browscap: %error", array('%error' => $browscap->error), WATCHDOG_ERROR);
    if (!$cron) {
      drupal_set_message(t("Couldn't retrieve updated browscap: %error", array('%error' => $browscap->error)), 'error');
    }
    return;
  }

  // The file as downloaded from browser.garykeith.com has semicolon (;) and
  // single-quote (') characters in the section strings, either of which will
  // cause parse_ini_file() to abort and return FALSE.  To fix this, simply
  // escape them with the backslash character.
  $browscap->data = preg_replace(array("/(?<![;\n])([;'])/", '/\A\\\\/'), array('\\\\$1', ''), $browscap->data);

  $browscapfp = fopen($browscapfile, "w");
  fwrite($browscapfp, $browscap->data);
  fclose($browscapfp);

  if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
    $a = parse_ini_file($browscapfile, TRUE, INI_SCANNER_RAW);
  }
  else {
    $a = parse_ini_file($browscapfile, TRUE);
  }
  if ($a) {
    // the first entry in the array is the version info
    $version = array_shift($a);
    foreach ($a as $key => $vals) {
      $e = $vals;
      // some recursive magic!
      $last_parent = array();
      while (isset($vals['Parent']) && $vals['Parent'] !== $last_parent) {
        $vals = isset($a[$vals['Parent']]) ? $a[$vals['Parent']] : array();
        $e = array_merge($vals, $e);
        $last_parent = $vals;
      }
      $useragent = strtr($key, '*?', '%_');
      $e = array_change_key_case($e);
      db_delete('browscap')
        ->condition('useragent', $useragent)
        ->execute();
      db_insert('browscap')
        ->fields(array(
         'useragent' => $useragent,
         'data' => serialize($e)
        ))
        ->execute();
    }

    cache_clear_all('*', 'cache_browscap', TRUE);
    variable_set('browscap_version', $browscapversion);
    watchdog('browscap', 'New version of browscap imported: %version', array('%version' => $browscapversion));
    if (!$cron) {
      drupal_set_message(t('New version of browscap imported: %version', array('%version' => $browscapversion)));
    }
  }
}
