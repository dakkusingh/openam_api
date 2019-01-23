<?php

namespace Drupal\openam_api\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin form for OpenAM API settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Settings constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openam_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'openam_api.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormTitle() {
    return 'OpenAM API Settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('openam_api.settings');

    $form['openam_api'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('OpenAM API Settings'),
    ];

    $form['openam_api']['openam_api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAM API Url'),
      '#description' => $this->t('The OpenAM API Url to use.'),
      '#default_value' => $config->get('openam_api_url'),
    ];

    $form['openam_api']['openam_api_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your OpenAM Username'),
      '#description' => $this->t('Your OpenAM Username.'),
      '#default_value' => $config->get('openam_api_username'),
    ];

    $form['openam_api']['openam_api_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your OpenAM Password'),
      '#description' => $this->t('Your OpenAM Password.'),
      '#default_value' => $config->get('openam_api_password'),
    ];

    // Check for devel module.
    $devel_module_present = $this->moduleHandler->moduleExists('devel');

    $form['debug'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('OpenAM API Debugging'),
    ];

    // Add debugging options.
    $form['debug']['debug_response'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug OpenAM Responses (requires Devel module)'),
      '#description' => $this->t('Show OpenAM Responses'),
      '#default_value' => $config->get('debug_response') && $devel_module_present,
      '#disabled' => !$devel_module_present,
    ];

    $form['debug']['debug_exception'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug OpenAM Exception (requires Devel module)'),
      '#description' => $this->t('Show OpenAM Exception'),
      '#default_value' => $config->get('debug_exception') && $devel_module_present,
      '#disabled' => !$devel_module_present,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('openam_api.settings')
      // TODO: Update the commented out settings for OpenAM
      // ->set('openam_api_key', $form_state->getValue('openam_api_key'))
      ->set('openam_api_key', $form_state->getValue('openam_api_url'))
      ->set('openam_api_key', $form_state->getValue('openam_api_username'))
      ->set('openam_api_key', $form_state->getValue('openam_api_password'))
      ->set('debug_response', $form_state->getValue('debug_response'))
      ->set('debug_exception', $form_state->getValue('debug_exception'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
