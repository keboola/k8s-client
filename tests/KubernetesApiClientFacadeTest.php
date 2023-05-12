<?php

declare(strict_types=1);

namespace Keboola\K8sClient\Tests;

use Keboola\K8sClient\ApiClient\ConfigMapsApiClient;
use Keboola\K8sClient\ApiClient\EventsApiClient;
use Keboola\K8sClient\ApiClient\IngressesApiClient;
use Keboola\K8sClient\ApiClient\PersistentVolumeClaimApiClient;
use Keboola\K8sClient\ApiClient\PodsApiClient;
use Keboola\K8sClient\ApiClient\SecretsApiClient;
use Keboola\K8sClient\ApiClient\ServicesApiClient;
use Keboola\K8sClient\Exception\ResourceNotFoundException;
use Keboola\K8sClient\Exception\TimeoutException;
use Keboola\K8sClient\KubernetesApiClientFacade;
use Kubernetes\Model\Io\K8s\Api\Apps\V1\ReplicaSet;
use Kubernetes\Model\Io\K8s\Api\Core\V1\ConfigMap;
use Kubernetes\Model\Io\K8s\Api\Core\V1\Event;
use Kubernetes\Model\Io\K8s\Api\Core\V1\PersistentVolumeClaim;
use Kubernetes\Model\Io\K8s\Api\Core\V1\Pod;
use Kubernetes\Model\Io\K8s\Api\Core\V1\PodList;
use Kubernetes\Model\Io\K8s\Api\Core\V1\Secret;
use Kubernetes\Model\Io\K8s\Api\Core\V1\Service;
use Kubernetes\Model\Io\K8s\Api\Networking\V1\Ingress;
use Kubernetes\Model\Io\K8s\Apimachinery\Pkg\Apis\Meta\V1\DeleteOptions;
use Kubernetes\Model\Io\K8s\Apimachinery\Pkg\Apis\Meta\V1\Status;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class KubernetesApiClientFacadeTest extends TestCase
{
    private readonly LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new Logger('test');
    }

    public function testApisAccessors(): void
    {
        $configMapsApiClient = $this->createMock(ConfigMapsApiClient::class);
        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $persistentVolumeClaimClient = $this->createMock(PersistentVolumeClaimApiClient::class);
        $podsApiClient = $this->createMock(PodsApiClient::class);
        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $ingressesApiClient = $this->createMock(IngressesApiClient::class);

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $configMapsApiClient,
            $eventsApiClient,
            $persistentVolumeClaimClient,
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        self::assertSame($configMapsApiClient, $facade->configMaps());
        self::assertSame($eventsApiClient, $facade->events());
        self::assertSame($persistentVolumeClaimClient, $facade->persistentVolumeClaims());
        self::assertSame($podsApiClient, $facade->pods());
        self::assertSame($secretsApiClient, $facade->secrets());
        self::assertSame($ingressesApiClient, $facade->ingresses());
    }

    public function testGetPod(): void
    {
        $returnedPod = new Pod([
            'metadata' => [
                'name' => 'pod-name',
                'labels' => [
                    'app' => 'pod-name',
                ],
            ],
        ]);

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::once())
            ->method('get')
            ->with('pod-name', ['labelSelector' => 'app=pod-name'])
            ->willReturn($returnedPod)
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::never())->method(self::anything());

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $result = $facade->get(Pod::class, 'pod-name', ['labelSelector' => 'app=pod-name']);
        self::assertSame($returnedPod, $result);
    }

    public function testGetSecret(): void
    {
        $returnedSecret = new Secret([
            'metadata' => [
                'name' => 'secret-name',
                'labels' => [
                    'app' => 'secret-name',
                ],
            ],
        ]);

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::never())->method(self::anything());

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::once())
            ->method('get')
            ->with('secret-name', ['labelSelector' => 'app=secret-name'])
            ->willReturn($returnedSecret)
        ;

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $result = $facade->get(Secret::class, 'secret-name', ['labelSelector' => 'app=secret-name']);
        self::assertSame($returnedSecret, $result);
    }

    public function testGetEvent(): void
    {
        $returnedEvent = new Event([
            'metadata' => [
                'name' => 'event-name',
                'labels' => [
                    'app' => 'event-name',
                ],
            ],
        ]);

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::never())->method(self::anything());

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::never())->method(self::anything());

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::once())
            ->method('get')
            ->with('event-name', ['labelSelector' => 'app=event-name'])
            ->willReturn($returnedEvent)
        ;

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $result = $facade->get(Event::class, 'event-name', ['labelSelector' => 'app=event-name']);
        self::assertSame($returnedEvent, $result);
    }

    public function testCreateModels(): void
    {
        // request & result represent the same resource but are different class instances
        $podRequest1 = new Pod(['metadata' => ['name' => 'pod1']]);
        $podRequest2 = new Pod(['metadata' => ['name' => 'pod2']]);
        $secretRequest3 = new Secret(['metadata' => ['name' => 'secret3']]);
        $eventRequest4 = new Event(['metadata' => ['name' => 'event4']]);
        $serviceRequest5 = new Service(['metadata' => ['name' => 'service5']]);
        $ingressRequest6 = new Ingress(['metadata' => ['name' => 'ingress6']]);

        $podResult1 = new Pod(['metadata' => ['name' => 'pod1']]);
        $podResult2 = new Pod(['metadata' => ['name' => 'pod2']]);
        $secretResult3 = new Secret(['metadata' => ['name' => 'secret3']]);
        $eventResult4 = new Event(['metadata' => ['name' => 'event4']]);
        $serviceResult5 = new Service(['metadata' => ['name' => 'service5']]);
        $ingressResult6 = new Ingress(['metadata' => ['name' => 'ingress6']]);

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::exactly(2))
            ->method('create')
            ->withConsecutive(
                [$podRequest1, []],
                [$podRequest2, []],
            )
            ->willReturnOnConsecutiveCalls(
                $podResult1,
                $podResult2,
                $secretResult3,
            )
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::once())
            ->method('create')
            ->with($secretRequest3, [])
            ->willReturn($secretResult3)
        ;

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::once())
            ->method('create')
            ->with($eventRequest4, [])
            ->willReturn($eventResult4)
        ;

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::once())
            ->method('create')
            ->with($serviceRequest5, [])
            ->willReturn($serviceResult5)
        ;

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::once())
            ->method('create')
            ->with($ingressRequest6, [])
            ->willReturn($ingressResult6)
        ;

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $result = $facade->createModels([
            $podRequest1,
            $podRequest2,
            $secretRequest3,
            $eventRequest4,
            $serviceRequest5,
            $ingressRequest6,
        ]);

        self::assertSame([
            $podResult1,
            $podResult2,
            $secretResult3,
            $eventResult4,
            $serviceResult5,
            $ingressResult6,
        ], $result);
    }

    public function testCreateModelsErrorHandling(): void
    {
        $pod1 = new Pod(['metadata' => ['name' => 'pod1']]);
        $pod2 = new Pod(['metadata' => ['name' => 'pod2']]);
        $pod3 = new Pod(['metadata' => ['name' => 'pod3']]);

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::exactly(2))
            ->method('create')
            ->withConsecutive(
                [$pod1, []],
                [$pod2, []],
            )
            ->will(self::onConsecutiveCalls(
                self::returnArgument(0),
                self::throwException(new RuntimeException('Can\'t create Pod')),
            ))
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::never())->method(self::anything());

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Can\'t create Pod');

        $facade->createModels([$pod1, $pod2, $pod3]);
    }

    public function testDeleteModels(): void
    {
        // request & result represent the same resource but are different class instances
        $podRequest1 = new Pod(['metadata' => ['name' => 'pod1']]);
        $podRequest2 = new Pod(['metadata' => ['name' => 'pod2']]);
        $secretRequest3 = new Secret(['metadata' => ['name' => 'secret3']]);
        $eventRequest4 = new Event(['metadata' => ['name' => 'event4']]);
        $serviceRequest5 = new Service(['metadata' => ['name' => 'service5']]);
        $ingressRequest6 = new Ingress(['metadata' => ['name' => 'ingress6']]);

        $podResult1 = new Status(['metadata' => ['name' => 'pod1']]);
        $podResult2 = new Status(['metadata' => ['name' => 'pod2']]);
        $secretResult3 = new Status(['metadata' => ['name' => 'secret3']]);
        $eventResult4 = new Status(['metadata' => ['name' => 'event4']]);
        $serviceResult5 = new Status(['metadata' => ['name' => 'service5']]);
        $ingressResult6 = new Status(['metadata' => ['name' => 'ingress6']]);

        $deleteOptions = new DeleteOptions();

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                ['pod1', $deleteOptions, []],
                ['pod2', $deleteOptions, []],
            )
            ->willReturnOnConsecutiveCalls(
                $podResult1,
                $podResult2,
            )
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::once())
            ->method('delete')
            ->with('secret3', $deleteOptions, [])
            ->willReturn($secretResult3)
        ;

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::once())
            ->method('delete')
            ->with('event4', $deleteOptions, [])
            ->willReturn($eventResult4)
        ;

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::once())
            ->method('delete')
            ->with('service5', $deleteOptions, [])
            ->willReturn($serviceResult5)
        ;

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::once())
            ->method('delete')
            ->with('ingress6', $deleteOptions, [])
            ->willReturn($ingressResult6)
        ;

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $result = $facade->deleteModels([
            $podRequest1,
            $podRequest2,
            $secretRequest3,
            $eventRequest4,
            $serviceRequest5,
            $ingressRequest6,
        ], $deleteOptions);

        self::assertSame([
            $podResult1,
            $podResult2,
            $secretResult3,
            $eventResult4,
            $serviceResult5,
            $ingressResult6,
        ], $result);
    }

    public function testDeleteModelsErrorHandling(): void
    {
        $pod1 = new Pod(['metadata' => ['name' => 'pod1']]);
        $pod2 = new Pod(['metadata' => ['name' => 'pod2']]);
        $pod3 = new Pod(['metadata' => ['name' => 'pod3']]);

        $deleteOptions = new DeleteOptions();

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                ['pod1', $deleteOptions, []],
                ['pod2', $deleteOptions, []],
            )
            ->will(self::onConsecutiveCalls(
                new Status(['metadata' => ['name' => 'pod1']]),
                self::throwException(new RuntimeException('Can\'t delete Pod')),
            ))
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::never())->method(self::anything());

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Can\'t delete Pod');

        $facade->deleteModels([$pod1, $pod2, $pod3], $deleteOptions);
    }

    public function testWaitWhileExists(): void
    {
        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::exactly(4))
            ->method('get')
            ->withConsecutive(
                // first round check both pods, pod1 still exists, pod2 does not exist
                ['pod1'],
                ['pod2'],
                // second round checks remaining pod1
                ['pod1'],
                // third round checks remaining pod1
                ['pod1'],
            )
            ->will(self::onConsecutiveCalls(
                new Pod(['metadata' => ['name' => 'pod1']]),
                self::throwException(new ResourceNotFoundException('Pod doesn\'t exist', null)),
                new Pod(['metadata' => ['name' => 'pod1']]),
                self::throwException(new ResourceNotFoundException('Pod doesn\'t exist', null)),
            ))
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::never())->method(self::anything());

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $facade->waitWhileExists([
            new Pod(['metadata' => ['name' => 'pod1']]),
            new Pod(['metadata' => ['name' => 'pod2']]),
        ]);
    }

    public function testWaitWhileExistsTimeout(): void
    {
        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient
            ->method('get')
            ->willReturnCallback(fn($podName) => new Pod(['metadata' => ['name' => $podName]]))
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::never())->method(self::anything());

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $startTime = microtime(true);
        try {
            $facade->waitWhileExists([
                new Pod(['metadata' => ['name' => 'pod1']]),
                new Pod(['metadata' => ['name' => 'pod2']]),
            ], 3);
            self::fail('Expected TimeoutException was not thrown');
        } catch (TimeoutException) {
        }
        $endTime = microtime(true);

        self::assertEqualsWithDelta(3, $endTime - $startTime, 1);
    }

    public function testListMatching(): void
    {
        $pod1 = new Pod(['metadata' => ['name' => 'pod-1']]);
        $pod2 = new Pod(['metadata' => ['name' => 'pod-2']]);
        $pod3 = new Pod(['metadata' => ['name' => 'pod-3']]);

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::exactly(2))
            ->method('list')
            ->withConsecutive(
                [['labelSelector' => 'app=my', 'limit' => 100]],
                [['labelSelector' => 'app=my', 'limit' => 100, 'continue' => 'foo']],
            )
            ->willReturnOnConsecutiveCalls(
                new PodList([
                    'metadata' => [
                        'continue' => 'foo',
                    ],
                    'items' => [$pod1, $pod2],
                ]),
                new PodList([
                    'items' => [$pod3],
                ]),
            )
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::never())->method(self::anything());

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $result = $facade->listMatching(Pod::class, ['labelSelector' => 'app=my']);
        self::assertEquals([$pod1, $pod2, $pod3], [...$result]);
    }

    public function testListMatchingWithCustomPageSize(): void
    {
        $pod1 = new Pod(['metadata' => ['name' => 'pod-1']]);
        $pod2 = new Pod(['metadata' => ['name' => 'pod-2']]);
        $pod3 = new Pod(['metadata' => ['name' => 'pod-3']]);

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::exactly(2))
            ->method('list')
            ->withConsecutive(
                [['labelSelector' => 'app=my', 'limit' => 5]],
                [['labelSelector' => 'app=my', 'limit' => 5, 'continue' => 'foo']],
            )
            ->willReturnOnConsecutiveCalls(
                new PodList([
                    'metadata' => [
                        'continue' => 'foo',
                    ],
                    'items' => [$pod1, $pod2],
                ]),
                new PodList([
                    'items' => [$pod3],
                ]),
            )
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::never())->method(self::anything());

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $result = $facade->listMatching(Pod::class, ['labelSelector' => 'app=my', 'limit' => 5]);
        self::assertEquals([$pod1, $pod2, $pod3], [...$result]);
    }

    public function testDeleteAllMatching(): void
    {
        $deleteOptions = new DeleteOptions();
        $deleteQuery = ['labelSelector' => 'app=my-app'];

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::once())
            ->method('deleteCollection')
            ->with($deleteOptions, $deleteQuery)
        ;

        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::once())
            ->method('deleteCollection')
            ->with($deleteOptions, $deleteQuery)
        ;

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $facade->deleteAllMatching($deleteOptions, $deleteQuery);
    }

    public function testDeleteAllMatchingErrorHandling(): void
    {
        $deleteOptions = new DeleteOptions();
        $deleteQuery = ['labelSelector' => 'app=my-app'];

        $podsApiClient = $this->createMock(PodsApiClient::class);
        $podsApiClient->expects(self::once())
            ->method('deleteCollection')
            ->with($deleteOptions, $deleteQuery)
            ->willThrowException(new RuntimeException('Pod delete failed'))
        ;

        // secrets API is called even if pods has failed
        $secretsApiClient = $this->createMock(SecretsApiClient::class);
        $secretsApiClient->expects(self::once())
            ->method('deleteCollection')
            ->with($deleteOptions, $deleteQuery)
        ;

        $eventsApiClient = $this->createMock(EventsApiClient::class);
        $eventsApiClient->expects(self::never())->method(self::anything());

        $servicesApiClient = $this->createMock(ServicesApiClient::class);
        $servicesApiClient->expects(self::never())->method(self::anything());

        $ingressesApiClient = $this->createMock(IngressesApiClient::class);
        $ingressesApiClient->expects(self::never())->method(self::anything());

        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $eventsApiClient,
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $podsApiClient,
            $secretsApiClient,
            $servicesApiClient,
            $ingressesApiClient
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pod delete failed');

        $facade->deleteAllMatching($deleteOptions, $deleteQuery);
    }

    public function testGetApiForResource(): void
    {
        $facade = new KubernetesApiClientFacade(
            $this->logger,
            $this->createMock(ConfigMapsApiClient::class),
            $this->createMock(EventsApiClient::class),
            $this->createMock(PersistentVolumeClaimApiClient::class),
            $this->createMock(PodsApiClient::class),
            $this->createMock(SecretsApiClient::class),
            $this->createMock(ServicesApiClient::class),
            $this->createMock(IngressesApiClient::class),
        );

        self::assertSame($facade->configMaps(), $facade->getApiForResource(ConfigMap::class));
        self::assertSame($facade->events(), $facade->getApiForResource(Event::class));
        self::assertSame(
            $facade->persistentVolumeClaims(),
            $facade->getApiForResource(PersistentVolumeClaim::class)
        );
        self::assertSame($facade->pods(), $facade->getApiForResource(Pod::class));
        self::assertSame($facade->secrets(), $facade->getApiForResource(Secret::class));
        self::assertSame($facade->services(), $facade->getApiForResource(Service::class));
        self::assertSame($facade->ingresses(), $facade->getApiForResource(Ingress::class));

        // get api for unsuported resource
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown K8S resource type "Kubernetes\Model\Io\K8s\Api\Apps\V1\ReplicaSet"');

        $facade->getApiForResource(ReplicaSet::class);
    }
}
