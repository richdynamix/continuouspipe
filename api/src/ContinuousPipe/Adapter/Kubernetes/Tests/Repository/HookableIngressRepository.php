<?php

namespace ContinuousPipe\Adapter\Kubernetes\Tests\Repository;

use Kubernetes\Client\Model\Ingress;
use Kubernetes\Client\Model\IngressList;
use Kubernetes\Client\Repository\IngressRepository;

class HookableIngressRepository implements IngressRepository
{
    /**
     * @var callable[]
     */
    private $findOneByNameHooks = [];

    /**
     * @var IngressRepository
     */
    private $decoratedRepository;

    /**
     * @param IngressRepository $decoratedRepository
     */
    public function __construct(IngressRepository $decoratedRepository)
    {
        $this->decoratedRepository = $decoratedRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneByName($name)
    {
        $ingress = $this->decoratedRepository->findOneByName($name);
        $ingress = $this->applyHooks($ingress);

        return $ingress;
    }

    /**
     * {@inheritdoc}
     */
    public function findByLabels(array $labels)
    {
        $found = $this->decoratedRepository->findByLabels($labels);

        return IngressList::fromIngresses(array_map(function (Ingress $ingress) {
            return $this->applyHooks($ingress);
        }, $found->getIngresses()));
    }

    /**
     * {@inheritdoc}
     */
    public function create(Ingress $ingress)
    {
        return $this->decoratedRepository->create($ingress);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        return $this->decoratedRepository->exists($name);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Ingress $ingress)
    {
        return $this->decoratedRepository->update($ingress);
    }

    /**
     * @param callable $hook
     */
    public function addFindOneByNameHooks(callable $hook)
    {
        $this->findOneByNameHooks[] = $hook;
    }

    /**
     * @param Ingress $ingress
     *
     * @return Ingress
     */
    private function applyHooks(Ingress $ingress)
    {
        foreach ($this->findOneByNameHooks as $hook) {
            $ingress = $hook($ingress);
        }

        return $ingress;
    }
}
