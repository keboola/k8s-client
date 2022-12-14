<?php

declare(strict_types=1);

namespace Keboola\K8sClient\ClientFacadeFactory;

use Keboola\K8sClient\ApiClient\PodsApiClient;
use Keboola\K8sClient\ApiClient\SecretsApiClient;
use Keboola\K8sClient\Exception\ConfigurationException;
use Keboola\K8sClient\KubernetesApiClient;
use Keboola\K8sClient\KubernetesApiClientFacade;
use KubernetesRuntime\Client;
use Psr\Log\LoggerInterface;
use Retry\RetryProxy;

class GenericClientFacadeFactory
{
    private RetryProxy $retryProxy;
    private LoggerInterface $logger;

    public function __construct(RetryProxy $retryProxy, LoggerInterface $logger)
    {
        $this->retryProxy = $retryProxy;
        $this->logger = $logger;
    }

    public function createClusterClient(
        string $apiUrl,
        string $token,
        string $caCertFile,
        string $namespace
    ): KubernetesApiClientFacade {
        if (!is_file($caCertFile) || !is_readable($caCertFile)) {
            throw new ConfigurationException(sprintf(
                'Invalid K8S CA cert path "%s". File does not exist or can\'t be read.',
                $caCertFile
            ));
        }

        Client::configure(
            $apiUrl,
            [
                'caCert' => $caCertFile,
                'token' => $token,
            ],
            [
                'connect_timeout' => '30',
                'timeout' => '60',
            ]
        );

        $apiClient = new KubernetesApiClient($this->retryProxy, $namespace);

        // all K8S API clients created here will use the configuration above, even if the Client is reconfigured later
        return new KubernetesApiClientFacade(
            $this->logger,
            new PodsApiClient($apiClient),
            new SecretsApiClient($apiClient),
        );
    }
}
