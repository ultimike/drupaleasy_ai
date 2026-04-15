<?php

namespace Drupal\drupaleasy_ai_tools\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Geocodes a location string via Geoapify Geocoding API.
 *
 * @Action(
 *   id = "drupaleasy_ai_tools_geoapify_geocode",
 *   label = @Translation("Geocode location via Geoapify"),
 *   type = "",
 *   eca_version_introduced = "1.0.0"
 * )
 */
class GeoapifyGeocodeAction extends ConfigurableActionBase {

  /**
   * The Guzzle http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    mixed $plugin_id,
    mixed $plugin_definition,
  ): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'api_key' => '',
      'input_key' => '',
      'output_key' => 'geoapify_geocode',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Geoapify API key'),
      '#description' => $this->t('Your Geoapify API key. Get a free key at <a href="https://myprojects.geoapify.com/" target="_blank">myprojects.geoapify.com</a>.'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];
    $form['input_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Location'),
      '#description' => $this->t('The location or business name to geocode. Supports ECA tokens.'),
      '#default_value' => $this->configuration['input_key'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['output_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token name for results'),
      '#description' => $this->t('Results available as <code>[token_name:formatted]</code>, <code>[token_name:lat]</code>, <code>[token_name:lon]</code>, <code>[token_name:name]</code>, <code>[token_name:city]</code>, <code>[token_name:state]</code>, <code>[token_name:country]</code>, <code>[token_name:postcode]</code>.'),
      '#default_value' => $this->configuration['output_key'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['api_key'] = $form_state->getValue('api_key');
    $this->configuration['input_key'] = $form_state->getValue('input_key');
    $this->configuration['output_key'] = $form_state->getValue('output_key');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $query = $this->tokenService->replaceClear($this->configuration['input_key']);
    $token_name = trim($this->configuration['output_key']) ?: 'geoapify_geocode';
    $api_key = $this->configuration['api_key'];

    if (empty($query)) {
      $this->logger->warning('Geoapify geocode: empty query after token replacement.');
      return;
    }

    if (empty($api_key)) {
      $this->logger->error('Geoapify geocode: API key is not configured.');
      return;
    }

    try {
      $response = $this->httpClient->request('GET',
        'https://api.geoapify.com/v1/geocode/search',
        [
          'query' => [
            'text' => $query,
            'format' => 'json',
            'limit' => 1,
            'apiKey' => $api_key,
          ],
        ]
      );

      $body = (string) $response->getBody();
      $data = json_decode($body, TRUE);

      if (!empty($data['results'][0])) {
        $place = $data['results'][0];

        $dto = DataTransferObject::create([
          'formatted' => $place['formatted'] ?? '',
          'name'      => $place['name'] ?? '',
          'lat'       => (string) ($place['lat'] ?? ''),
          'lon'       => (string) ($place['lon'] ?? ''),
          'city'      => $place['city'] ?? '',
          'state'     => $place['state'] ?? '',
          'country'   => $place['country'] ?? '',
          'postcode'  => $place['postcode'] ?? '',
          'street'    => $place['street'] ?? '',
        ]);
        $this->tokenService->addTokenData($token_name, $dto);

        $this->logger->notice('Geoapify geocode: "@query" resolved to @formatted (@lat, @lon) — stored in [@token].', [
          '@query' => $query,
          '@formatted' => $place['formatted'] ?? 'unknown',
          '@lat' => $place['lat'] ?? '',
          '@lon' => $place['lon'] ?? '',
          '@token' => $token_name,
        ]);
      }
      else {
        $this->logger->warning('Geoapify geocode: no results for "@query".', ['@query' => $query]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Geoapify geocode failed: @message', ['@message' => $e->getMessage()]);
    }
  }

}
