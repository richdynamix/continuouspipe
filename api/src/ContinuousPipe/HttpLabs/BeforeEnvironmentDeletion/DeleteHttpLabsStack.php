<?php

namespace ContinuousPipe\HttpLabs\BeforeEnvironmentDeletion;

use ContinuousPipe\Pipe\Events;
use ContinuousPipe\Pipe\Kubernetes\Event\Environment\EnvironmentDeletionEvent;
use ContinuousPipe\HttpLabs\Client\HttpLabsClient;
use ContinuousPipe\HttpLabs\Encryption\EncryptedAuthentication;
use ContinuousPipe\HttpLabs\Encryption\EncryptionNamespace;
use ContinuousPipe\HttpLabs\Endpoint\HttpLabsEndpointTransformer;
use ContinuousPipe\Security\Encryption\Vault;
use Kubernetes\Client\Exception\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeleteHttpLabsStack implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Vault
     */
    private $vault;

    /**
     * @var HttpLabsClient
     */
    private $httpLabsClient;

    public function __construct(LoggerInterface $logger, HttpLabsClient $httpLabsClient, Vault $vault)
    {
        $this->logger = $logger;
        $this->vault = $vault;
        $this->httpLabsClient = $httpLabsClient;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            Events::ENVIRONMENT_PRE_DELETION => 'beforeEnvironmentDeletion',
        ];
    }

    public function beforeEnvironmentDeletion(EnvironmentDeletionEvent $event)
    {
        try {
            $services = $event->getClient()->getServiceRepository()->findAll()->getServices();
        } catch (Exception $e) {
            $this->logger->warning(
                'Unable to get the services of the namespace',
                [
                    'exception' => $e,
                ]
            );

            return;
        }

        foreach ($services as $service) {
            $annotation = $service->getMetadata()->getAnnotationList()->get(
                HttpLabsEndpointTransformer::HTTPLABS_ANNOTATION
            );
            if (null === $annotation) {
                continue;
            }

            try {
                $this->deleteStack($annotation);
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'Something went wrong while deleting the HttpLabs stack',
                    [
                        'exception' => $e,
                    ]
                );
            }
        }
    }

    private function getAuthenticationDetails($metadata)
    {
        $encryptedAuthentication = new EncryptedAuthentication(
            $this->vault,
            EncryptionNamespace::from($metadata['stack_identifier'])
        );

        return $encryptedAuthentication->decrypt($metadata['encrypted_authentication']);
    }

    private function deleteStack($annotation)
    {
        $metadata = \GuzzleHttp\json_decode($annotation->getValue(), true);

        $authentication = $this->getAuthenticationDetails($metadata);

        $this->httpLabsClient->deleteStack(
            $authentication->getApiKey(),
            $metadata['stack_identifier']
        );
    }
}
