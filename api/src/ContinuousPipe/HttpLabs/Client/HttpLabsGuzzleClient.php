<?php

namespace ContinuousPipe\HttpLabs\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;

class HttpLabsGuzzleClient implements HttpLabsClient
{
    /**
     * @var HandlerStack
     */
    private $httpHandlerStack;

    /**
     * @param HandlerStack $httpHandlerStack
     */
    public function __construct(HandlerStack $httpHandlerStack)
    {
        $this->httpHandlerStack = $httpHandlerStack;
    }

    /**
     * {@inheritdoc}
     */
    public function createStack(string $apiKey, string $projectIdentifier, string $name, string $backendUrl): Stack
    {
        $stack = clone $this->httpHandlerStack;
        $stack->push(Middleware::mapRequest(function (Request $request) use ($apiKey) {
            return $request->withAddedHeader('Authorization', $apiKey);
        }));

        $httpClient = new Client([
            'handler' => $stack,
        ]);

        try {
            $response = $httpClient->request(
                'post',
                sprintf('https://api.httplabs.io/projects/%s/stacks', $projectIdentifier),
                [
                    'json' => [
                        'name' => substr($name, 0, 20),
                    ]
                ]
            );

            $stackUri = $response->getHeaderLine('Location');
            $httpClient->request('put', $stackUri, [
                'json' => [
                    'backend' => $backendUrl,
                ]
            ]);

            $stackResponseContents = $httpClient->request('get', $stackUri)->getBody()->getContents();
        } catch (RequestException $e) {
            throw new HttpLabsException('Unable to create the HttpLabs stack', $e->getCode(), $e);
        }

        try {
            $responseJson = \GuzzleHttp\json_decode($stackResponseContents, true);

            if (!isset($responseJson['id']) || !isset($responseJson['url'])) {
                throw new \InvalidArgumentException('The response needs to contain `id` and `url`');
            }
        } catch (\InvalidArgumentException $e) {
            throw new HttpLabsException('Unable to understand the response from HttpLabs', $e->getCode(), $e);
        }

        return new Stack(
            $responseJson['id'],
            $responseJson['url']
        );
    }
}
