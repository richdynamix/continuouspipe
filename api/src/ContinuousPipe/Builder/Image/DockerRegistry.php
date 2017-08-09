<?php

namespace ContinuousPipe\Builder\Image;

use ContinuousPipe\Builder\Docker\CredentialsRepository;
use ContinuousPipe\Builder\Image;
use ContinuousPipe\Security\Credentials\DockerRegistry as DockerRegistryCredentials;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class DockerRegistry implements Registry
{
    private $client;
    private $credentialsRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ClientInterface $client,
        CredentialsRepository $credentialsRepository,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->credentialsRepository = $credentialsRepository;
        $this->logger = $logger;
    }

    /**
     * @throws SearchingForExistingImageException
     */
    public function containsImage(Uuid $credentialsBucket, Image $image): bool
    {
        try {

            $credentials = $this->getCredentials($credentialsBucket, $image);

            return $this->requestManifest(
                    $image,
                    $credentials->getServerAddress(),
                    $this->fetchToken($this->fetchAuthDetails($image, $credentials), $credentials)
                )->getStatusCode() == 200;

        } catch (SearchingForExistingImageException $searchingForExistingImageException) {

            throw $searchingForExistingImageException;

        } catch (\Throwable $throwable) {

            throw new SearchingForExistingImageException('Error occurred while checking for an existing docker image for the build', 0, $throwable);

        }
    }

    private function manifestUrl(string $serverAddress, Image $image)
    {
        return sprintf(
            'https://%s/v2/%s/manifests/%s',
            $serverAddress,
            $image->getTwoPartName(),
            $image->getTag()
        );
    }

    private function requestManifest(Image $image, string $serverAddress, string $token = null): ResponseInterface
    {
        $this->logger->info(
        'Requesting manifest',
            [
                'request-url' => $this->manifestUrl($serverAddress, $image),
                'token' => $token
            ]
        );
        return $this->client->request(
            'head',
            $this->manifestUrl($serverAddress, $image),
            isset($token) ? ['headers' => ['Authorization', 'Bearer ' . $token]] : []
        );
    }

    private function getCredentials(Uuid $credentialsBucket, Image $image): DockerRegistryCredentials
    {
        return $this->credentialsRepository->findRegistryByImage(
            $image,
            $credentialsBucket
        );
    }

    private function fetchAuthDetails(Image $image, DockerRegistryCredentials $credentials) : array
    {
        $initialResponse = $this->requestManifest($image, $credentials->getServerAddress());

        if ($initialResponse->getStatusCode() != 401) {
            $this->logger->warning(
                'Retrieving auth details failed',
                [
                    'status-code' => $initialResponse->getStatusCode(),
                    'response-body'=> $initialResponse->getBody()->getContents()
                ]
            );
            throw new SearchingForExistingImageException(
                'Error requesting auth details.  Expected 401, got ' . $initialResponse->getStatusCode() . ' with message "' . $initialResponse->getBody()->getContents() . '", headers: ' . var_export($initialResponse->getHeaders(), true)
            );
        }

        if (null === $authHeader = $initialResponse->getHeader('WWW-Authenticate')) {
            throw new SearchingForExistingImageException('Error retrieving auth details from response header');
        }

        preg_match_all('/(\w+)="([^"]+)"/', $authHeader, $matches, PREG_SET_ORDER);

        //should check all present before returning
        return array_column($matches, 2, 1);
    }

    private function fetchToken(array $authDetails, DockerRegistryCredentials $credentials): string
    {
        $tokenResponse = $this->client->request(
            'get',
            sprintf('%s?service=%s&scope=%s', $authDetails['realm'], $authDetails['service'], $authDetails['scope']),
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(
                            $credentials->getUsername() . ':' . $credentials->getPassword()
                        )
                ],
            ]
        );

        if ($tokenResponse->getStatusCode() != 200) {
            throw new SearchingForExistingImageException('Token unavailable');
        }

        return \GuzzleHttp\json_decode($tokenResponse->getBody(), true)['token'];
    }
}
