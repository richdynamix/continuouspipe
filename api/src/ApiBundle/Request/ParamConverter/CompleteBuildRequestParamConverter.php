<?php

namespace ApiBundle\Request\ParamConverter;

use ContinuousPipe\Builder\Request\BuildRequest;
use ContinuousPipe\Builder\Request\CompleteBuildRequest;
use JMS\Serializer\Serializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

class CompleteBuildRequestParamConverter implements ParamConverterInterface
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @param Serializer $serializer
     */
    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $body = $request->getContent(false);
        $buildRequest = $this->serializer->deserialize($body, CompleteBuildRequest::class, 'json');

        $request->attributes->set($configuration->getName(), $buildRequest);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ParamConverter $configuration)
    {
        return $configuration->getConverter() == 'complete_build_request';
    }
}
