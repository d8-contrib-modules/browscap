<?php
/**
 * @file
 * Displays user agent information.
 */

/**
 * Page callback to show details about known useragents.
 *
 * @param $useragent
 *   The useragent object, loaded from the database.
 * @return array
 *   the data about this useragent.
 */
function browscap_useragent_properties($useragent = NULL) {
  $useragent = (array)$useragent;
  $header = array(
    t('Property'),
    t('Value'),
  );
  $rows = array();
  foreach ($useragent as $key => $val) {
    $rows[] = array(check_plain($key), check_plain($val));
  }
  $build = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#attributes' => array('id' => 'browscap-useragent'),
    '#empty' => t('No useragent properties available.'),
  );
  return $build;
}
