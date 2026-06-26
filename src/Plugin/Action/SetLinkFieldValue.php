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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sets all sub-properties of a Link field widget value in one action.
 *
 * @Action(
 *   id = "drupaleasy_ai_tools_set_link_widget_value",
 *   label = @Translation("Set Link field widget value"),
 *   eca_version_introduced = "1.0.0"
 * )
 */
#[Action(
  id: 'drupaleasy_ai_tools_set_link_widget_value',
  label: new TranslatableMarkup('Set Link field widget value'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Sets all sub-properties of a Link field widget value in one action.'),
  version_introduced: '1.0.0',
)]
class SetLinkFieldValue extends ConfigurableActionBase {

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
    $instance->setMessenger($container->get('messenger'));
    return $instance;
  }

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

    $link_props = ['uri', 'title'];

    $link_values = [];
    foreach ($link_props as $prop) {
      $value = $this->tokenService->replaceClear($this->configuration[$prop] ?? '');
      if ($value !== '') {
        $link_values[$prop] = $value;
      }
    }

    if (empty($link_values)) {
      $this->logger->warning('SetLinkFieldValue: no link values resolved after token replacement.');
      $this->messenger()->addWarning($this->t('No link data found.'));
      return;
    }

    $event->setWidgetValue($link_values);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'uri' => '',
      'title' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $fields = [
      'uri' => $this->t('URL (e.g. https://example.com or internal:/node/1)'),
      'title' => $this->t('Link text'),
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
