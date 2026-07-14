<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Api\BackendApi;
use RH\Tweakwise\Tests\Unit\Fixtures\ProductFixtureFactory;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Symfony\Component\Routing\RouterInterface;

class BackendApiParityTest extends TestCase
{
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')->willReturn('/product/fallback');
    }

    public static function cases(): array
    {
        return ProductFixtureFactory::cases();
    }

    #[DataProvider('cases')]
    public function testSyncFieldsMatchExpected(
        SalesChannelProductEntity $product,
        ?SalesChannelProductEntity $parent,
        array $expected,
        bool $groupedProducts
    ): void {
        $history = [];
        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, ProductFixtureFactory::createFrontend(), $parent, [], $groupedProducts);

        $posted = json_decode((string) $history[0]['request']->getBody(), true);

        foreach ($expected as $field => $expectedValue) {
            $actualValue = $posted[$field] ?? '';
            $this->assertEquals(
                $expectedValue,
                $actualValue,
                sprintf('Sync field "%s" does not match expected value.', $field)
            );
        }
    }

    private function createCapturingClient(array &$history): Client
    {
        $mock = new MockHandler([new Response(200, [], '{}')]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        return new Client(['handler' => $stack]);
    }

    private function createApi(Client $client): BackendApi
    {
        $api = $this->getMockBuilder(BackendApi::class)
            ->setConstructorArgs(['instance-key', 'access-token', $this->router, $client])
            ->onlyMethods(['getProductData', 'getCategoryData'])
            ->getMock();

        $api->method('getProductData')->willReturn(['error' => true, 'code' => 404, 'message' => 'Not Found']);
        $api->method('getCategoryData')->willReturn([]);

        return $api;
    }
}
