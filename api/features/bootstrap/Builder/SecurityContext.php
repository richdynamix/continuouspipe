<?php

namespace Builder;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use ContinuousPipe\Security\Credentials\Bucket;
use ContinuousPipe\Security\Credentials\BucketRepository;
use ContinuousPipe\Security\Credentials\DockerRegistry;
use ContinuousPipe\Security\Credentials\GitHubToken;
use ContinuousPipe\Security\Tests\Authenticator\InMemoryAuthenticatorClient;
use ContinuousPipe\Security\User\SecurityUser;
use ContinuousPipe\Security\User\User;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SecurityContext implements Context
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var KernelInterface
     */
    private $kernel;
    /**
     * @var BucketRepository
     */
    private $bucketRepository;

    public function __construct(BucketRepository $bucketRepository, TokenStorageInterface $tokenStorage, KernelInterface $kernel)
    {
        $this->tokenStorage = $tokenStorage;
        $this->kernel = $kernel;
        $this->bucketRepository = $bucketRepository;
    }

    /**
     * @Transform table:username,password,email,serverAddress
     * @Transform table:username,password,serverAddress,email
     */
    public function transformDockerRegistryCredentials(TableNode $node)
    {
        return array_map(function(array $row) {
            return new DockerRegistry($row['username'], $row['password'], $row['email'], $row['serverAddress']);
        }, $node->getHash());
    }

    /**
     * @Given I am authenticated
     */
    public function iAmAuthenticated()
    {
        $token = new JWTUserToken(['ROLE_USER']);
        $token->setUser(new SecurityUser(new User('samuel', Uuid::uuid1())));

        $this->tokenStorage->setToken($token);
    }

    /**
     * @Given there is the bucket :uuid
     */
    public function thereIsTheBucket($uuid)
    {
        $this->bucketRepository->save(new Bucket(Uuid::fromString($uuid)));
    }

    /**
     * @Given the bucket :uuid contains the following docker registry credentials:
     */
    public function theBucketContainsTheFollowingDockerRegistryCredentials($uuid, array $credentials)
    {
        $bucket = $this->bucketRepository->find(Uuid::fromString($uuid));

        foreach ($credentials as $credential) {
            $bucket->getDockerRegistries()->add($credential);
        }
    }

    /**
     * @Given the bucket :uuid contains the Docker Registry credentials
     */
    public function theBucketContainsTheDockerRegistryCredentials($uuid)
    {
        $bucket = $this->bucketRepository->find(Uuid::fromString($uuid));
        $testCredentials = $this->kernel->getContainer()->getParameter('test_docker_credentials');

        foreach ($testCredentials as $row) {
            $bucket->getDockerRegistries()->add(new DockerRegistry($row['username'], $row['password'], $row['email'], $row['serverAddress']));
        }
    }

    /**
     * @Given the bucket :uuid contains the following github tokens:
     */
    public function theBucketContainsTheFollowingGithubTokens($uuid, TableNode $table)
    {
        $bucket = $this->bucketRepository->find(Uuid::fromString($uuid));

        foreach ($table->getHash() as $row) {
            $bucket->getGitHubTokens()->add(new GitHubToken($row['identifier'], $row['token']));
        }
    }
}
