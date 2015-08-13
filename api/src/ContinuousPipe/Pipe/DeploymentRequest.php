<?php

namespace ContinuousPipe\Pipe;

use JMS\Serializer\Annotation as JMS;

class DeploymentRequest
{
    /**
     * Environment name.
     *
     * @JMS\Type("string")
     * @JMS\SerializedName("environmentName")
     *
     * @var string
     */
    private $environmentName;

    /**
     * Name of the provider.
     *
     * @JMS\Type("string")
     * @JMS\SerializedName("providerName")
     *
     * @var string
     */
    private $providerName;

    /**
     * Contents of the Docker-Compose file.
     *
     * @JMS\Type("string")
     * @JMS\SerializedName("dockerComposeContents")
     *
     * @var string
     */
    private $dockerComposeContents;

    /**
     * URL of a callback to have notification about the status of the deployment.
     *
     * @JMS\Type("string")
     * @JMS\SerializedName("notificationCallbackUrl")
     *
     * @var string
     */
    private $notificationCallbackUrl;

    /**
     * @JMS\Type("string")
     * @JMS\SerializedName("logId")
     *
     * @var string
     */
    private $logId;

    /**
     * @return string
     */
    public function getEnvironmentName()
    {
        return $this->environmentName;
    }

    /**
     * @return string
     */
    public function getProviderName()
    {
        return $this->providerName;
    }

    /**
     * @return string
     */
    public function getDockerComposeContents()
    {
        return $this->dockerComposeContents;
    }

    /**
     * @return string
     */
    public function getNotificationCallbackUrl()
    {
        return $this->notificationCallbackUrl;
    }

    /**
     * @return string
     */
    public function getLogId()
    {
        return $this->logId;
    }
}
