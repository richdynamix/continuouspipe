<?php


namespace ContinuousPipe\River\ClusterPolicies\Resources;

use ContinuousPipe\Model\Component\Resources;
use ContinuousPipe\River\Environment\DeployedEnvironment;

class ResourceCalculator
{
    /**
     * Sum resources for all environments
     *
     * @param DeployedEnvironment[] $environments
     * @return Resources
     */
    public static function sumEnvironmentResources($environments = []) : Resources
    {
        $requests = new AggregateResourcesRequest();
        $limits = new AggregateResourcesRequest();

        foreach ($environments as $environment) {
            $components = $environment->getComponents();

            foreach ($components as $component) {
                $specification = $component->getSpecification();
                $resources = $specification->getResources();

                if ($resources !== null) {
                    $requests->add($resources->getRequests());
                    $limits->add($resources->getLimits());
                }
            }
        }

        return new Resources($requests->toResourcesRequest(), $limits->toResourcesRequest());
    }
}
