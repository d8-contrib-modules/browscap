<?php
/**
 * @file
 * Generate user agent reports.
 */

/**
 * Menu callback; presents the user agents monitoring page.
 *
 * @param $view
 *   - "browsers": Only display "real" browsers
 *   - "crawlers": Only display search engine crawlers
 *   - "all": Display all user agents.
 */
function browscap_top_useragents($view = 'all') {
  $header = array(
    0 => array('data' => t('User agent'), 'field' => 'parent'),
    1 => array('data' => t('Count'), 'field' => 'counter', 'sort' => 'desc'),
    2 => array('data' => t('Percent'), 'field' => 'counter'),
    3 => array('data' => t('Crawler?'), 'field' => 'is_crawler')
  );

  $query_total = db_select('browscap_statistics', 'bs');
  $query_total->addExpression('SUM(bs.counter)');

  $query = db_select('browscap_statistics', 'bs')
    ->fields('bs', array('parent', 'counter', 'is_crawler'))
    ->extend('PagerDefault')
    ->extend('TableSort')
    ->limit(50)
    ->orderByHeader($header);

  switch ($view) {
    case 'browsers':
      $title = t('Browsers');
      $header[0]['data'] = t('Browser');
      unset($header[3]);
      $query->condition('is_crawler', 0);
      $query_total->condition('is_crawler', 0);
      break;

    case 'crawlers':
      $title = t('Crawlers');
      $header[0]['data'] = t('Crawler');
      unset($header[3]);
      $query->condition('is_crawler', 1);
      $query_total->condition('is_crawler', 1);
      break;

    default:
      $title = t('All');
      break;
  }
  drupal_set_title($title);

  $total = $query_total
    ->execute()
    ->fetchField();
  if (!$total) {
    $total = 1;
  }

  $result = $query->execute();
  $rows = array();
  foreach ($result as $useragent) {
    $exists = (bool) db_query_range('SELECT 1 FROM {browscap} WHERE useragent = :useragent', 0, 1,
      array(':useragent' => $useragent->parent))
      ->fetchField();

    if ($exists) {
      $parent = l($useragent->parent, 'admin/reports/browscap/useragent/' . $useragent->parent);
    }
    else {
      $parent = check_plain($useragent->parent);
    }
    $tablerow = array();
    $tablerow[] = $parent;
    $tablerow[] = $useragent->counter;
    $tablerow[] = round(100 * $useragent->counter / $total, 4);
    if (!in_array($view, array('browsers', 'crawlers'))) {
      $tablerow[] = $useragent->is_crawler ? t('Yes') : t('No');
    }
    $rows[] = $tablerow;
  }

  $build['browscap_statistics_table'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('It appears that your site has not recorded any visits. If you want to record the visitors to your site you can enable "Monitor browsers" on the <a href="!settings_uri">Browscap settings screen</a>.', array('!settings_uri' => url('admin/config/system/browscap'))),
  );
  $build['browscap_statistics_pager'] = array(
    '#theme' => 'pager',
  );

  return $build;
}
