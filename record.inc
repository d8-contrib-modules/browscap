<?php
/**
 * @file
 * Browscap user agent recording functions.
 */

/**
 * Helper function to record a user agent().
 *
 * @param boolean $skip_futher_recording
 *   Optional flag to denote if user agent monitoring should be skipped for the rest
 *   of the page request. If true, monitoring will be disabled.
 * @param boolean $bypass_monitoring
 *   Optional flag to denote if the current user has permission to bypass monitoring
 */
function _record_user_agent($skip_futher_recording = FALSE, $bypass_monitoring = FALSE) {
  // Create a static variable to track the status of user agent monitoring for
  // the entire page request
  static $record_user_agent = TRUE;

  // Record the current user agent if recording and logging are enabled and the
  // user does not have permission to bypass monitoring
  if ($record_user_agent == TRUE && $bypass_monitoring != TRUE && variable_get('browscap_enable_user_agent_log', FALSE) == TRUE) {
    // Get browscap data about the current user agent
    $user_agent = browscap_get_browser();

    // Identify the user agent using browscap data
    // Otherwise, use the USER_AGENT header from the current request
    if (!empty($user_agent['parent'])) {
      $user_agent_string = substr(check_plain(trim($user_agent['parent'])), 0, 255);
    }
    else {
      $user_agent_string = substr(check_plain(trim($_SERVER['HTTP_USER_AGENT'])), 0, 255);
    }

    // Determine if the user agent is a crawler using browscap data
    // Otherwise, assume that the user agent isn't a crawler
    if (!empty($user_agent['crawler'])) {
      $user_agent_crawler = check_plain(trim($user_agent['crawler']));
    }
    else {
      $user_agent_crawler = 0;
    }

    // Record the user agent in the database
    db_merge('browscap_statistics')
      ->key(array('parent' => $user_agent_string))
      ->fields(array(
        'counter' => 1,
        'is_crawler' => $user_agent_crawler,
      ))
      ->expression('counter', 'counter + 1')
      ->execute();
  }

  // Change the status of user agent monitoring when necessary
  if ($skip_futher_recording == TRUE) {
    // Disable user agent monitoring for the rest of the page request
    $record_user_agent = FALSE;
  }
}
