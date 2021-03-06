<?php

namespace ContinuousPipe\River\Task\Deploy;

use ContinuousPipe\Pipe\Client\Client;
use ContinuousPipe\River\Task\Task;
use ContinuousPipe\River\Task\TaskRunner;
use ContinuousPipe\River\Task\TaskRunnerException;
use ContinuousPipe\River\Tide;

class DeployTaskRunner implements TaskRunner
{
    /**
     * @var DeploymentRequestFactory
     */
    private $deploymentRequestFactory;

    /**
     * @var Client
     */
    private $pipeClient;

    /**
     * @param DeploymentRequestFactory $deploymentRequestFactory
     * @param Client                   $pipeClient
     */
    public function __construct(DeploymentRequestFactory $deploymentRequestFactory, Client $pipeClient)
    {
        $this->deploymentRequestFactory = $deploymentRequestFactory;
        $this->pipeClient = $pipeClient;
    }

    /**
     * {@inheritdoc}
     */
    public function run(Tide $tide, Task $task)
    {
        if (!$task instanceof DeployTask) {
            throw new TaskRunnerException('This runner only runs the deploy tasks', 0, null, $task);
        }

        try {
            $task->startDeployment($tide, $this->deploymentRequestFactory, $this->pipeClient);
        } catch (\Exception $e) {
            throw new TaskRunnerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Tide $tide, Task $task): bool
    {
        return $task instanceof DeployTask;
    }
}
