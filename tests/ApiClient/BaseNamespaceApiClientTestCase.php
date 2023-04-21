<?php

declare(strict_types=1);

namespace Keboola\K8sClient\Tests\ApiClient;

use Keboola\K8sClient\ApiClient\BaseNamespaceApiClient;
use Keboola\K8sClient\Exception\ResourceAlreadyExistsException;
use Keboola\K8sClient\Exception\ResourceNotFoundException;
use Keboola\K8sClient\KubernetesApiClient;
use Kubernetes\Model\Io\K8s\Apimachinery\Pkg\Apis\Meta\V1\DeleteOptions;
use Kubernetes\Model\Io\K8s\Apimachinery\Pkg\Apis\Meta\V1\Status;
use KubernetesRuntime\AbstractAPI;
use KubernetesRuntime\AbstractModel;
use KubernetesRuntime\Client;
use Retry\RetryProxy;
use RuntimeException;

/**
 * @template TBaseApi of AbstractAPI
 * @template TApi of BaseNamespaceApiClient
 */
trait BaseNamespaceApiClientTestCase
{
    /** @var TBaseApi */
    private AbstractAPI $baseApiClient;

    /** @var TApi */
    private BaseNamespaceApiClient $apiClient;

    abstract protected function createResource(array $metadata): AbstractModel;

    /**
     * @param class-string<TBaseApi> $baseApiClientClass
     * @param class-string<TApi> $apiClientClass
     */
    public function setUpBaseNamespaceApiClientTest(string $baseApiClientClass, string $apiClientClass): void
    {
        Client::configure(
            (string) getenv('K8S_HOST'),
            [
                'caCert' => (string) getenv('K8S_CA_CERT_PATH'),
                'token' => (string) getenv('K8S_TOKEN'),
            ],
        );

        $this->baseApiClient = new $baseApiClientClass;
        $this->apiClient = new $apiClientClass(
            new KubernetesApiClient(
                new RetryProxy(),
                (string) getenv('K8S_NAMESPACE'),
            ),
            $this->baseApiClient,
        );

        $this->cleanupK8sResources();
    }

    private function cleanupK8sResources(float $timeout = 30.0): void
    {
        $startTime = microtime(true);

        $this->baseApiClient->deleteCollection(
            (string) getenv('K8S_NAMESPACE'),
            new DeleteOptions([
                'gracePeriodSeconds' => 0,
                'propagationPolicy' => 'Foreground',
            ]),
        );

        while ($startTime + $timeout > microtime(true)) {
            $result = $this->baseApiClient->list((string) getenv('K8S_NAMESPACE'));

            if ($result instanceof Status) {
                throw new RuntimeException('Failed to read resource state: ' . $result->message);
            }

            assert(is_object($result) && property_exists($result, 'items'));
            if (count($result->items) === 0) {
                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException('Timeout while waiting for resource delete');
    }

    protected function waitWhileResourceExists(string $name, float $timeout = 30.0): void
    {
        $startTime = microtime(true);

        while ($startTime + $timeout > microtime(true)) {
            $result = $this->baseApiClient->read((string) getenv('K8S_NAMESPACE'), $name);

            if ($result instanceof Status) {
                if ($result->code === 404) {
                    return;
                }

                throw new RuntimeException('Failed to read resource state: ' . $result->message);
            }

            usleep(100_000);
        }

        throw new RuntimeException('Timeout while waiting for resource delete');
    }

    public function testListResources(): void
    {
        $result = $this->apiClient->list();
        self::assertCount(0, $result->items);

        $this->baseApiClient->create((string) getenv('K8S_NAMESPACE'), $this->createResource([
            'name' => 'test-resource-1',
            'labels' => [
                'app' => 'test-1',
            ],
        ]));

        $this->baseApiClient->create((string) getenv('K8S_NAMESPACE'), $this->createResource([
            'name' => 'test-resource-2',
        ]));

        // list all
        $result = $this->apiClient->list();
        self::assertCount(2, $result->items);
        self::assertSame(
            ['test-resource-1', 'test-resource-2'],
            array_map(fn($resource) => $resource->metadata->name, $result->items)
        );

        // list using labelSelector
        $result = $this->apiClient->list([
            'labelSelector' => 'app=test-1',
        ]);
        self::assertCount(1, $result->items);
        self::assertSame(
            ['test-resource-1'],
            array_map(fn($resource) => $resource->metadata->name, $result->items)
        );
    }

    public function testGetResource(): void
    {
        $this->baseApiClient->create((string) getenv('K8S_NAMESPACE'), $this->createResource([
            'name' => 'test-resource-1',
            'labels' => [
                'app' => 'test-1',
            ],
        ]));

        $result = $this->apiClient->get('test-resource-1');
        self::assertSame('test-resource-1', $result->metadata->name);
    }

    public function testGetNonExistingResourceThrowsException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Resource not found:');

        $this->apiClient->get('test-resource-1');
    }

    public function testCreateResource(): void
    {
        $resourceToCreate = $this->createResource([
            'name' => 'test-resource-1',
            'labels' => [
                'app' => 'test-1',
            ],
        ]);

        $createdResource = $this->apiClient->create($resourceToCreate);

        self::assertNotSame($resourceToCreate, $createdResource);
        self::assertSame($resourceToCreate->metadata->name, $createdResource->metadata->name);

        $result = $this->baseApiClient->list((string) getenv('K8S_NAMESPACE'));
        assert(is_object($result) && property_exists($result, 'items'));
        self::assertCount(1, $result->items);
        self::assertSame($result->items[0]->metadata->name, $createdResource->metadata->name);
    }

    public function testCreateResourceWithDuplicateNameThrowsException(): void
    {
        $resourceToCreate = $this->createResource([
            'name' => 'test-resource-1',
            'labels' => [
                'app' => 'test-1',
            ],
        ]);

        $this->apiClient->create($resourceToCreate);

        $this->expectException(ResourceAlreadyExistsException::class);
        $this->expectExceptionMessage('Resource already exists:');

        $this->apiClient->create($resourceToCreate);
    }

    public function testDeleteResource(): void
    {
        $this->baseApiClient->create((string) getenv('K8S_NAMESPACE'), $this->createResource([
            'name' => 'test-resource-1',
            'labels' => [
                'app' => 'test-1',
            ],
        ]));
        $this->baseApiClient->create((string) getenv('K8S_NAMESPACE'), $this->createResource([
            'name' => 'test-resource-2',
            'labels' => [
                'app' => 'test-2',
            ],
        ]));

        // delete the resource
        $this->apiClient->delete('test-resource-1');
        $this->waitWhileResourceExists('test-resource-1');

        // check the other resource was not deleted
        $this->apiClient->get('test-resource-2');

        $this->expectNotToPerformAssertions();
    }

    public function testDeleteNotExistingResourceThrowsException(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('Resource not found:');

        $this->apiClient->delete('test-resource-1');
    }

    public function testDeleteCollection(): void
    {
        $this->baseApiClient->create((string) getenv('K8S_NAMESPACE'), $this->createResource([
            'name' => 'test-resource-11',
            'labels' => [
                'app' => 'test-1',
            ],
        ]));
        $this->baseApiClient->create((string) getenv('K8S_NAMESPACE'), $this->createResource([
            'name' => 'test-resource-12',
            'labels' => [
                'app' => 'test-1',
            ],
        ]));
        $this->baseApiClient->create((string) getenv('K8S_NAMESPACE'), $this->createResource([
            'name' => 'test-resource-21',
        ]));

        $listResult = $this->baseApiClient->list((string) getenv('K8S_NAMESPACE'));
        assert(is_object($listResult) && property_exists($listResult, 'items'));
        self::assertCount(3, $listResult->items);

        $this->apiClient->deleteCollection(new DeleteOptions(), [
            'labelSelector' => 'app=test-1',
        ]);

        $this->waitWhileResourceExists('test-resource-11');
        $this->waitWhileResourceExists('test-resource-12');

        $listResult = $this->baseApiClient->list((string) getenv('K8S_NAMESPACE'));
        assert(is_object($listResult) && property_exists($listResult, 'items'));
        self::assertCount(1, $listResult->items);
    }
}