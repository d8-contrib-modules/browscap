<?php

/**
 * @file
 * Contains Drupal\browscap\BrowscapService.
 */

namespace Drupal\browscap;

/**
 * Class BrowscapService.
 *
 * @package Drupal\browscap
 */
class BrowscapService {

  /**
   * Provide data about a user agent string or the current user agent.
   *
   * @param string $user_agent
   *   Optional user agent string to test. If empty, use the value from the current request.
   * @return array
   *   An array of information about the user agent.
   */
  function getBrowser($user_agent = NULL) {

    $cache = \Drupal::cache('browscap');

    // Determine the current user agent if a user agent was not specified
    if ($user_agent != NULL) {
      $user_agent = \Drupal\Component\Utility\Html::escape(trim($user_agent));
    }
    elseif ($user_agent == NULL && isset($_SERVER['HTTP_USER_AGENT'])) {
      $user_agent = \Drupal\Component\Utility\Html::escape(trim($_SERVER['HTTP_USER_AGENT']));
    }
    else {
      $user_agent = 'Default Browser';
    }

    // Check the cache for user agent data
    $cache_data = $cache->get($user_agent);

    // Attempt to find a cached user agent
    // Otherwise store the user agent data in the cache
    if (!empty($cache_data) && ($cache_data->created > REQUEST_TIME - 60 * 60 * 24)) {
      $user_agent_properties = unserialize($cache_data->data);
    }
    else {
      // Find the user agent's properties
      // The useragent column contains the wildcarded pattern to match against our
      // full-length string while the ORDER BY chooses the most-specific matching
      // pattern
      $user_agent_properties = db_query("SELECT * FROM {browscap} WHERE :useragent LIKE useragent ORDER BY LENGTH(useragent) DESC", array(':useragent' => $user_agent))
        ->fetchObject();

      // Serialize the property data for caching
      $serialized_property_data = serialize($user_agent_properties);

      // Store user agent data in the cache
      $cache->set($user_agent, $serialized_property_data);
    }

    // Create an array to hold the user agent's properties
    $properties = array();

    // Return an array of user agent properties
    if (isset($user_agent_properties)) {
      // Unserialize the user agent data found in the cache or the database
      $properties = unserialize($user_agent_properties->data);

      // Set the user agent name and name pattern
      $properties['useragent'] = $user_agent;
      $properties['browser_name_pattern'] = strtr($user_agent_properties->useragent, '%_', '*?');
    }
    else {
      // Set the user agent name and name pattern to 'unrecognized'
      $properties['useragent'] = 'unrecognized';
      $properties['browser_name_pattern'] = strtr('unrecognized', '%_', '*?');
    }

    return $properties;
  }
}