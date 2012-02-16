<?php
/**
 * @file
 * Generate configuration form.
 */

/**
 * Implements hook_form().
 */
function browscap_settings_form($form, &$form_state) {
  // Check the local browscap data version number
  $version = variable_get('browscap_version', 0);

  // If the version number is 0 then browscap data has never been fetched
  if ($version == 0) {
    $version = t('Never fetched');
  }

  $form['browscap_data_status'] = array(
    '#markup' => '<p>' . t('Current browscap data version: %fileversion.', array('%fileversion' => $version)) . '</p>',
  );
  $form['browscap_update_interval'] = array(
    '#type' => 'textfield',
    '#title' => t('Update interval'),
    '#default_value' => variable_get('browscap_update_interval', 7),
    '#description' => t('Control how often Browscap checks for new browser information. Enter 0 to disable updating.'),
    '#field_suffix' => t('days'),
    '#size' => 3,
    '#maxlength' => 3,
  );
  $form['browscap_monitor'] = array(
    '#type' => 'checkbox',
    '#title' => t('Monitor browsers'),
    '#default_value' => variable_get('browscap_monitor', FALSE),
    '#description' => t('Monitor all user agents visiting the site. View the reports in the <a href="!reports">Browscap reports</a> area.', array('!reports' => url('admin/reports/browscap'))),
  );
  $form['actions']['browscap_refresh'] = array(
    '#type' => 'submit',
    '#value' => t('Refresh browscap data'),
    '#submit' => array('browscap_refresh_submit'),
    '#weight' => 10,
  );

  return system_settings_form($form);
}

 /**
 * Validate the settings form.
 */
function browscap_settings_form_validate($form, &$form_state) {
  // Check the update interval
  $update_interval = $form_state['values']['browscap_update_interval'];

  // Ensure that the update interval is a number
  if (!is_numeric($update_interval)) {
    form_set_error('browscap_update_interval', t('The update interval must be a number.'));
  }

  // Ensure that the update interval is a non-negative number
  if ($update_interval < 0) {
    form_set_error('browscap_update_interval', t('The update interval must be non-negative.'));
  }
}

/**
 * Submit handler for the refresh browscap button.
 */
function browscap_refresh_submit($form, &$form_state) {
  // Update the browscap information
  _browscap_import(FALSE);

  // Record when the browscap information was updated
  variable_set('browscap_imported', REQUEST_TIME);
}
