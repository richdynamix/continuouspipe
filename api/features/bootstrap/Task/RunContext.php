<?php

namespace Task;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Exception\PendingException;
use ContinuousPipe\Model\Component;
use ContinuousPipe\Pipe\Client\Deployment;
use ContinuousPipe\River\Task\Run\RunTask;
use ContinuousPipe\River\Tests\Pipe\TraceableClient;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Tide\TasksContext;

class RunContext implements Context
{
    /**
     * @var \TideContext
     */
    private $tideContext;

    /**
     * @var \FlowContext
     */
    private $flowContext;

    /**
     * @var TasksContext
     */
    private $tideTasksContext;

    /**
     * @var TraceableClient
     */
    private $traceablePipeClient;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @param Kernel $kernel
     * @param TraceableClient $traceablePipeClient
     * @param Serializer $serializer
     */
    public function __construct(Kernel $kernel, TraceableClient $traceablePipeClient, Serializer $serializer)
    {
        $this->traceablePipeClient = $traceablePipeClient;
        $this->kernel = $kernel;
        $this->serializer = $serializer;
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $this->tideContext = $scope->getEnvironment()->getContext('TideContext');
        $this->flowContext = $scope->getEnvironment()->getContext('FlowContext');
        $this->tideTasksContext = $scope->getEnvironment()->getContext('Tide\TasksContext');
    }

    /**
     * @Given a build and run task is started with a service name
     */
    public function aRunTaskIsStartedWithAServiceName()
    {
        $this->tideContext->aTideIsStartedWithTasks([
            [
                'build' => [],
            ],
            [
                'run' => [
                    'commands' => ['bin/behat'],
                    'image' => ['from_service' => 'image0'],
                    'providerName' => 'foo'
                ]
            ]
        ]);
    }

    /**
     * @Given a run task is started with an image name
     */
    public function aRunTaskIsStartedWithAnImageName()
    {
        $this->tideContext->aTideIsStartedWithTasks([
            [
                'run' => [
                    'commands' => ['bin/behat'],
                    'image' => 'sroze/behat',
                    'providerName' => 'foo'
                ]
            ]
        ]);
    }

    /**
     * @Then a run request should be sent
     */
    public function aRunRequestShouldBeSent()
    {
        $requests = $this->traceablePipeClient->getRequests();

        if (count($requests) == 0) {
            throw new \RuntimeException('Expected to find runner requests, found 0');
        }
    }

    /**
     * @When the run failed
     */
    public function theRunFailed()
    {
        if (null === ($deployment = $this->traceablePipeClient->getLastDeployment())) {
            throw new \RuntimeException('No deployment found');
        }

        $this->sendRunnerNotification(
            new Deployment($deployment->getUuid(), $deployment->getRequest(), Deployment::STATUS_FAILURE)
        );
    }

    /**
     * @Then the run task should be failed
     */
    public function theRunTaskShouldBeFailed()
    {
        if (!$this->getRunTask()->isFailed()) {
            throw new \RuntimeException('Expected the task to be failed');
        }
    }

    /**
     * @Then the run task should be successful
     */
    public function theRunTaskShouldBeSuccessful()
    {
        if (!$this->getRunTask()->isSuccessful()) {
            throw new \RuntimeException('Expected the task to be successful, be it\'s not');
        }
    }

    /**
     * @When the run succeed
     */
    public function theRunSucceed()
    {
        if (null === ($deployment = $this->traceablePipeClient->getLastDeployment())) {
            throw new \RuntimeException('No deployment found');
        }

        $this->sendRunnerNotification(
            new Deployment($deployment->getUuid(), $deployment->getRequest(), Deployment::STATUS_SUCCESS)
        );
    }

    /**
     * @Then the component :name should be deployed as attached
     */
    public function theComponentShouldBeDeployedAsAttached($name)
    {
        $component = $this->getDeployedComponentNamed($name);

        if (null === ($deploymentStrategy = $component->getDeploymentStrategy())) {
            throw new \RuntimeException('The component do not have any deployment strategy');
        }

        if (!$deploymentStrategy->isAttached()) {
            throw new \RuntimeException('Component is not deployed as attached');
        }
    }

    /**
     * @Then the component :name should be deployed as not scaling
     */
    public function thePodShouldBeDeployedAsNotRestartingAfterTermination($name)
    {
        $component = $this->getDeployedComponentNamed($name);

        if ($component->getSpecification()->getScalability()->isEnabled()) {
            throw new \RuntimeException('Component is deployed as scaling');
        }
    }

    /**
     * @param Deployment $deployment
     */
    private function sendRunnerNotification(Deployment $deployment)
    {
        $response = $this->kernel->handle(Request::create(
            sprintf('/runner/notification/tide/%s', (string) $this->tideContext->getCurrentTideUuid()),
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json'
            ],
            $this->serializer->serialize($deployment, 'json')
        ));

        if (!in_array($response->getStatusCode(), [200, 204])) {
            echo $response->getContent();
            throw new \RuntimeException(sprintf(
                'Expected status code 200 but got %d',
                $response->getStatusCode()
            ));
        }
    }

    /**
     * @return RunTask
     */
    private function getRunTask()
    {
        /* @var Task[] $deployTasks */
        $runTasks = $this->tideTasksContext->getTasksOfType(RunTask::class);

        if (count($runTasks) == 0) {
            throw new \RuntimeException('No run task found');
        }

        return current($runTasks);
    }

    /**
     * @param string $name
     *
     * @return Component
     */
    private function getDeployedComponentNamed($name)
    {
        $request = $this->traceablePipeClient->getRequests()[0];
        $matchingComponents = array_filter($request->getSpecification()->getComponents(), function(Component $component) use ($name) {
            return $component->getName() == $name;
        });

        if (0 == count($matchingComponents)) {
            throw new \RuntimeException(sprintf(
                'No component named "%s" found in deployment request',
                $name
            ));
        }

        return current($matchingComponents);
    }
}
