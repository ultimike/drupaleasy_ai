<?php

namespace Drupal\drupaleasy_ai_tools\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_field_widget_actions\Event\FieldWidgetEvent;

/**
 * Sets all sub-properties of an Address field widget value in one action.
 *
 * @Action(
 *   id = "drupaleasy_ai_tools_set_address_widget_value",
 *   label = @Translation("Set Address field widget value"),
 *   eca_version_introduced = "1.0.0"
 * )
 */
#[Action(
  id: 'drupaleasy_ai_tools_set_address_widget_value',
  label: new TranslatableMarkup('Set Address field widget value'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Sets all sub-properties of an Address field widget value in one action.'),
  version_introduced: '1.0.0',
)]
class SetAddressFieldValue extends ConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResult|bool {
    $result = AccessResult::allowedIf($this->getEvent() instanceof FieldWidgetEvent);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    /** @var \Drupal\eca_field_widget_actions\Event\FieldWidgetEvent $event */
    $event = $this->getEvent();

    $address_props = [
      'country_code',
      'address_line1',
      'address_line2',
      'locality',
      'administrative_area',
      'postal_code',
      'organization',
      'given_name',
      'family_name',
    ];

    $address_values = [];
    foreach ($address_props as $prop) {
      $value = $this->tokenService->replaceClear($this->configuration[$prop] ?? '');
      if ($value !== '') {
        $address_values[$prop] = $value;
      }
    }

    if (empty($address_values)) {
      $this->logger->warning('SetAddressFieldValue: no address values resolved after token replacement.');
      \Drupal::messenger()->addWarning($this->t('No address data found.'));
      return;
    }

    $event->setWidgetValue($address_values);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'country_code' => '',
      'address_line1' => '',
      'address_line2' => '',
      'locality' => '',
      'administrative_area' => '',
      'postal_code' => '',
      'organization' => '',
      'given_name' => '',
      'family_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $fields = [
      'country_code' => $this->t('Country code (ISO 3166-1 alpha-2, e.g. US)'),
      'address_line1' => $this->t('Address line 1'),
      'address_line2' => $this->t('Address line 2'),
      'locality' => $this->t('City'),
      'administrative_area' => $this->t('State'),
      'postal_code' => $this->t('Postal code'),
      'organization' => $this->t('Organization'),
      'given_name' => $this->t('Given name'),
      'family_name' => $this->t('Family name'),
    ];

    foreach ($fields as $key => $label) {
      $form[$key] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $this->configuration[$key],
        '#eca_token_replacement' => TRUE,
      ];
    }

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    foreach (array_keys($this->defaultConfiguration()) as $key) {
      $this->configuration[$key] = $form_state->getValue($key, '');
    }
    parent::submitConfigurationForm($form, $form_state);
  }

}
