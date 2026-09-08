<?php

declare(strict_types=1);

namespace Keboola\K8sClient;

use Keboola\K8sClient\Exception\KubernetesResponseException;
use Keboola\K8sClient\Exception\ResourceAlreadyExistsException;
use Keboola\K8sClient\Exception\ResourceNotFoundException;
use Kubernetes\Model\Io\K8s\Apimachinery\Pkg\Apis\Meta\V1\Status;
use KubernetesRuntime\AbstractAPI;
use Retry\RetryProxy;

/**
 * @internal
 */
class KubernetesApiClient
{
    private const RAW_RESULT_PREVIEW_LENGTH = 512;

    private RetryProxy $retryProxy;
    protected string $k8sNamespace;

    public function __construct(RetryProxy $retryProxy, string $k8sNamespace)
    {
        $this->retryProxy = $retryProxy;
        $this->k8sNamespace = $k8sNamespace;
    }

    public function getK8sNamespace(): string
    {
        return $this->k8sNamespace;
    }

    /**
     * Run request on namespace-scoped API.
     *
     * Calls $api->{method}($this->k8sNamespace, ...$args)
     *
     * @param class-string<TResult> $expectedResult
     * @param mixed ...$args
     * @return TResult
     * @template TResult
     */
    public function request(AbstractAPI $api, string $method, string $expectedResult, ...$args)
    {
        return $this->clusterRequest($api, $method, $expectedResult, $this->k8sNamespace, ...$args);
    }

    /**
     * Run request on cluster-wide API.
     *
     * Calls $api->{method}(...$args)
     *
     * @param class-string<TResult> $expectedResult
     * @param mixed ...$args
     * @return TResult
     * @template TResult
     */
    public function clusterRequest(AbstractAPI $api, string $method, string $expectedResult, ...$args)
    {
        // network errors (connection failure, timeout etc.), server-side errors (5xx status) should be covered by retry
        // client errors (4xx status) should not be retried

        // inside the $api Guzzle is configured to not throw exception for 4xx/5xx status, it has to be handled manually
        // https://github.com/allansun/kubernetes-php-runtime/blob/f4d9466c1c8edc62c1f756f2812ceeb77f62b196/src/Client.php#L58

        $result = $this->retryProxy->call(function () use ($api, $method, $expectedResult, $args) {
            $result = $api->{$method}(...$args);

            if ($result instanceof Status && $result->code >= 500) {
                throw new KubernetesResponseException(
                    sprintf('K8S request has failed: %s', $result->message),
                    $result,
                );
            }

            // a non-object result means the runtime could not map the body onto any K8S model, so the API server
            // answered with something else than a resource - treat it like the transient server-side error it is
            if (!is_object($result)) {
                throw self::createUnexpectedResultException($api, $method, $expectedResult, $result);
            }

            return $result;
        });

        if ($result instanceof Status && $result->status === 'Failure') {
            if ($result->code === 404) {
                throw new ResourceNotFoundException(
                    sprintf('Resource not found: %s', $result->message),
                    $result,
                );
            }

            if ($method === 'create' && $result->reason === 'AlreadyExists') {
                throw new ResourceAlreadyExistsException(
                    sprintf('Resource already exists: %s', $result->message),
                    $result,
                );
            }

            throw new KubernetesResponseException(
                sprintf('K8S request has failed: %s', $result->message),
                $result,
            );
        }

        if ($result instanceof $expectedResult) {
            return $result;
        }

        throw self::createUnexpectedResultException($api, $method, $expectedResult, $result);
    }

    private static function createUnexpectedResultException(
        AbstractAPI $api,
        string $method,
        string $expectedResult,
        mixed $result,
    ): KubernetesResponseException {
        $bodyPreview = is_object($result) ? '' : self::previewRawResult($result);

        return new KubernetesResponseException(
            sprintf(
                'Expected response class %s for request %s::%s, found %s%s',
                $expectedResult,
                get_class($api),
                $method,
                get_debug_type($result),
                $bodyPreview === '' ? '' : ': ' . $bodyPreview,
            ),
            null,
        );
    }

    /**
     * The runtime drops the HTTP status code of a body it can not map onto a K8S model, so the body itself is
     * the only thing left to tell an overloaded API server apart from a misconfigured request.
     */
    private static function previewRawResult(mixed $result): string
    {
        $body = is_string($result) ? $result : json_encode($result);
        if (!is_string($body)) {
            return '';
        }

        return strlen($body) > self::RAW_RESULT_PREVIEW_LENGTH
            ? substr($body, 0, self::RAW_RESULT_PREVIEW_LENGTH) . '...'
            : $body;
    }
}
