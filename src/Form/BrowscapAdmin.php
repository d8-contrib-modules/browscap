<?php

namespace Drupal\browscap\Form;

use \Drupal\browscap\BrowscapImporter;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\browscap\BrowscapEndpoint;

class BrowscapAdmin extends ConfigFormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'browscap_admin';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'browscap.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = \Drupal::config('browscap.settings');
    $form = array();

    // Check the local browscap data version number
    $version = $config->get('version');

    // If the version number is 0 then browscap data has never been fetched
    if ($version == 0) {
      $version = t('Never fetched');
    }

    $form['data'] = array(
      '#type' => 'fieldset',
      '#title' => t('User agent detection settings'),
    );
    $form['data']['browscap_data_version'] = array(
      '#markup' => '<p>' . t('Current browscap data version: %fileversion.', array('%fileversion' => $version)) . '</p>',
    );
    $form['data']['browscap_enable_automatic_updates'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable automatic updates'),
      '#default_value' => $config->get('enable_automatic_updates'),
      '#description' => t('Automatically update the user agent detection information.'),
    );
    $options = array(3600, 10800, 21600, 32400, 43200, 86400, 172800, 259200, 604800, 1209600, 2419200, 4838400, 9676800);
    $dateformatter = \Drupal::service('date.formatter');
    $form['data']['browscap_automatic_updates_timer'] = array(
      '#type' => 'select',
      '#title' => t('Check for new user agent detection information every'),
      '#default_value' => $config->get('automatic_updates_timer'),
      '#options' => array_map(array($dateformatter,'formatInterval'), array_combine($options, $options)),
      '#description' => t('Newer user agent detection information will be automatically downloaded and installed. (Requires a correctly configured '. \Drupal::l("cron maintenance task", Url::fromRoute('system.status')) . '.'),
      '#states' => array(
        'visible' => array(
          ':input[name="browscap_enable_automatic_updates"]' => array('checked' => TRUE),
        ),
      ),
    );

    $form['data']['browscap_version_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Browscap version URL'),
      '#default_value' => $config->get('version_url'),
      '#description' => t('The URL to the information about the current Browscap version available.'),
    );
    $form['data']['browscap_data_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Browscap data URL'),
      '#default_value' => $config->get('data_url'),
      '#description' => t('The URL to Browscap data.'),
    );

    $form['actions']['browscap_refresh'] = array(
      '#type' => 'submit',
      '#value' => t('Refresh browscap data'),
      '#submit' => array('::refreshSubmit'),
      '#weight' => 10,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('browscap.settings')
      ->set('automatic_updates_timer', $form_state->getValue('browscap_automatic_updates_timer'))
      ->set('enable_automatic_updates', $form_state->getValue('browscap_enable_automatic_updates'))
      ->set('data_url', $form_state->getValue('browscap_data_url'))
      ->set('version_url', $form_state->getValue('browscap_version_url'))
      ->save();
    drupal_set_message('Password Strength settings have been stored');
    parent::submitForm($form, $form_state);
  }

  function refreshSubmit(array &$form, FormStateInterface $form_state) {
    // Update the browscap information
    $endpoint = new BrowscapEndpoint();
    BrowscapImporter::import($endpoint, FALSE);

    // Record when the browscap information was updated
    $this->config('browscap.settings')
      ->set('imported', REQUEST_TIME)
      ->save();
  }
}