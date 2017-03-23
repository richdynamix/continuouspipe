<?php

namespace ContinuousPipe\Adapter\Kubernetes\ReverseTransformer;

use ContinuousPipe\Model\Component;
use ContinuousPipe\Model\Status;
use Kubernetes\Client\Exception\ServiceNotFound;
use Kubernetes\Client\Model\Container;
use Kubernetes\Client\Model\ContainerStatus;
use Kubernetes\Client\Model\Deployment;
use Kubernetes\Client\Model\Ingress;
use Kubernetes\Client\Model\KubernetesObject;
use Kubernetes\Client\Model\Pod;
use Kubernetes\Client\Model\ReplicationController;
use Kubernetes\Client\Model\Service;
use Kubernetes\Client\Model\ServiceSpecification;
use Kubernetes\Client\NamespaceClient;

class ComponentTransformer
{
    /**
     * @var ComponentPublicEndpointResolver
     */
    private $resolver;

    public function __construct(ComponentPublicEndpointResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param NamespaceClient       $namespaceClient
     * @param ReplicationController $replicationController
     *
     * @throws \InvalidArgumentException
     *
     * @return Component
     */
    public function getComponentFromReplicationController(NamespaceClient $namespaceClient, ReplicationController $replicationController)
    {
        $replicationControllerName = $replicationController->getMetadata()->getName();
        $containers = $replicationController->getSpecification()->getPodTemplateSpecification()->getPodSpecification()->getContainers();
        if (0 == count($containers)) {
            throw new \InvalidArgumentException('The pod specification should have at least one container');
        }

        return new Component(
            $replicationControllerName,
            $replicationControllerName,
            new Component\Specification(
                $this->getComponentSource($containers[0]),
                $this->getComponentAccessibility($namespaceClient, $replicationController->getMetadata()->getName()),
                new Component\Scalability(true, $replicationController->getSpecification()->getReplicas())
            ),
            [],
            [],
            $this->getComponentStatus($namespaceClient, $replicationController),
            new Component\DeploymentStrategy(null, null, false, false)
        );
    }

    /**
     * @param NamespaceClient $namespaceClient
     * @param Deployment      $deployment
     *
     * @return Component
     */
    public function getComponentFromDeployment(NamespaceClient $namespaceClient, Deployment $deployment)
    {
        $name = $deployment->getMetadata()->getName();
        $replicas = $deployment->getSpecification()->getReplicas();

        $containers = $deployment->getSpecification()->getTemplate()->getPodSpecification()->getContainers();
        if (0 === count($containers)) {
            throw new \InvalidArgumentException('No container found in deployment\'s specification');
        }

        return new Component(
            $name,
            $name,
            new Component\Specification(
                $this->getComponentSource($containers[0]),
                $this->getComponentAccessibility($namespaceClient, $name),
                new Component\Scalability(true, $replicas)
            ),
            [],
            [],
            $this->getComponentStatus($namespaceClient, $deployment),
            new Component\DeploymentStrategy(null, null, false, false)
        );
    }

    /**
     * @param Container $container
     *
     * @return Component\Source
     */
    private function getComponentSource(Container $container)
    {
        $imageName = $container->getImage();
        $tagName = null;

        if (($semiColonPosition = strpos($imageName, ':')) !== false) {
            $imageName = substr($imageName, 0, $semiColonPosition);
            $tagName = substr($imageName, $semiColonPosition);
        }

        return new Component\Source($imageName, $tagName);
    }

    /**
     * @param NamespaceClient $namespaceClient
     * @param string          $serviceName
     *
     * @return Component\Accessibility
     */
    private function getComponentAccessibility(NamespaceClient $namespaceClient, $serviceName)
    {
        try {
            $service = $namespaceClient->getServiceRepository()->findOneByName($serviceName);

            $externalService = $service->getSpecification()->getType() == ServiceSpecification::TYPE_LOAD_BALANCER;

            return new Component\Accessibility(true, $externalService);
        } catch (ServiceNotFound $e) {
            return new Component\Accessibility(false, false);
        }
    }

    /**
     * @param NamespaceClient  $namespaceClient
     * @param KubernetesObject $object
     *
     * @return Component\Status
     */
    private function getComponentStatus(NamespaceClient $namespaceClient, KubernetesObject $object)
    {
        if ($object instanceof ReplicationController) {
            $pods = $namespaceClient->getPodRepository()->findByReplicationController($object)->getPods();
        } elseif ($object instanceof Deployment) {
            $pods = $namespaceClient->getPodRepository()->findByLabels(
                $object->getSpecification()->getSelector()->getMatchLabels()
            )->getPods();
        } else {
            throw new \InvalidArgumentException(sprintf('Unable to get status from object %s', get_class($object)));
        }

        $componentName = $object->getMetadata()->getName();
        $replicas = $object->getSpecification()->getReplicas();
        $healthyPods = $this->filterHealthyPods($pods);

        if (count($healthyPods) == $replicas) {
            $status = Component\Status::HEALTHY;
        } elseif (count($healthyPods) > 0) {
            $status = Component\Status::WARNING;
        } else {
            $status = Component\Status::UNHEALTHY;
        }

        return new Component\Status(
            $status,
            $this->getComponentPublicEndpoints($namespaceClient, $componentName),
            $this->getContainerStatuses($pods)
        );
    }

    /**
     * @param NamespaceClient $namespaceClient
     * @param string          $componentName
     *
     * @return array
     */
    private function getComponentPublicEndpoints(NamespaceClient $namespaceClient, $componentName)
    {
        $publicEndpoints = [];

        foreach ($this->getServicesAndIngress($namespaceClient, $componentName) as $serviceOrIngress) {
            $publicEndpoints = array_merge($publicEndpoints, $this->resolver->resolve($serviceOrIngress));
        }

        return $publicEndpoints;
    }

    /**
     * @param Pod[] $pods
     *
     * @return Pod[]
     */
    private function filterHealthyPods(array $pods)
    {
        return array_filter($pods, function (Pod $pod) {
            return $this->isPodHealthy($pod);
        });
    }

    /**
     * @param Pod[] $pods
     *
     * @return Component\Status\ContainerStatus[]
     */
    private function getContainerStatuses(array $pods)
    {
        return array_map(function (Pod $pod) {
            $status = $pod->getStatus();

            return new Component\Status\ContainerStatus(
                $pod->getMetadata()->getName(),
                $this->isPodHealthy($pod) ? Status::HEALTHY : Status::UNHEALTHY,
                array_reduce($status->getContainerStatuses() ?: [], function ($count, ContainerStatus $status) {
                    return $count + $status->getRestartCount();
                }, 0)
            );
        }, $pods);
    }

    /**
     * @param Pod $pod
     *
     * @return bool
     */
    private function isPodHealthy(Pod $pod)
    {
        if (null === ($status = $pod->getStatus())) {
            return false;
        } elseif (count($status->getContainerStatuses()) === 0) {
            return false;
        }

        return array_reduce($status->getContainerStatuses(), function ($ready, ContainerStatus $containerStatus) {
            return $ready && $containerStatus->isReady();
        }, true);
    }

    /**
     * @param NamespaceClient $namespaceClient
     * @param string          $componentName
     *
     * @return Service[]|Ingress[]|null[]
     */
    private function getServicesAndIngress(NamespaceClient $namespaceClient, $componentName)
    {
        $labels = [
            'component-identifier' => $componentName,
        ];

        return array_merge(
            $namespaceClient->getServiceRepository()->findByLabels($labels)->getServices(),
            $namespaceClient->getIngressRepository()->findByLabels($labels)->getIngresses()
        );
    }
}
