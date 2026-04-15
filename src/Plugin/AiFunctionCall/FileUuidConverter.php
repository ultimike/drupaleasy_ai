<?php

namespace Drupal\drupaleasy_ai_tools\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tool to convert file UUID to file ID or media ID.
 */
#[FunctionCall(
  id: 'ai_agent:get_file_id',
  function_name: 'get_file_info',
  name: 'Get file or media ID from UUID',
  description: 'This method will get the media ID (mid) or file ID (fid) from a specific UUID.',
  group: 'information_tools',
  module_dependencies: ['file', 'media'],
  context_definitions: [
    'uuid' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("UUID"),
      description: new TranslatableMarkup("The UUID of the file or media to get the ID for."),
      required: TRUE
    ),
  ],
)]
class FileUuidConverter extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->entityRepository = $container->get('entity.repository');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $uuid = $this->getContextValue('uuid');
    try {
      $entity = $this->entityRepository->loadEntityByUuid('file', $uuid);

      if ($entity) {
        $this->setOutput('The file ID for the file with UUID ' . $uuid . 'is: ' . $entity->id());
        return;
      }
      else {
        if (!$this->currentUser->hasPermission('view media')) {
          $this->setOutput('You do not have permission to view media.');
          return;
        }
        $entity = $this->entityRepository->loadEntityByUuid('media', $uuid);
        if ($entity) {
          $this->setOutput('The media ID for the media entity with UUID ' . $uuid . 'is: ' . $entity->id());
          return;
        }
      }
    }
    catch (\Exception $e) {
      $this->setOutput('No media or file ID found for the given UUID: ' . $uuid);
      return;
    }
    $this->setOutput('No media or file ID found for the given UUID: ' . $uuid);
  }

}
