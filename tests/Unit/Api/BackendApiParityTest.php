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

    /**
     * When a variant's own translated name is empty, the backend sync must fall
     * back to the parent's translated name.
     *
     * This differs from the feed: the XML feed template has no explicit fallback
     * because Shopware's DAL resolves translation inheritance before the template
     * renders (the integration test FeedXmlOutputTest::testGroupedVariantWithoutOwnNameUsesParentNameInXml
     * is the authoritative truth for the feed). The backend sync receives the
     * entity directly from the admin controller where DAL inheritance may not
     * have been resolved, so it must apply the fallback explicitly.
     */
    public function testSyncUsesParentNameWhenVariantNameIsEmpty(): void
    {
        $variant = ProductFixtureFactory::createProduct([
            'number'  => 'VARIANT-NO-NAME',
            'name'    => '',
            'stock'   => 1,
            'brand'   => 'Brand X',
            'price'   => 10.00,
            'seoPath' => 'product/no-name-variant',
        ]);
        $parent = ProductFixtureFactory::createProduct([
            'number'  => 'PARENT-NO-NAME',
            'name'    => 'Parent Name As Fallback',
            'stock'   => 0,
            'brand'   => 'Brand X',
            'price'   => 10.00,
            'seoPath' => 'product/parent-no-name',
        ]);

        $history = [];
        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($variant, ProductFixtureFactory::createFrontend(), $parent, [], true);

        $posted = json_decode((string) $history[0]['request']->getBody(), true);

        $this->assertSame(
            'Parent Name As Fallback',
            $posted['Name'],
            'Sync must use parent name as fallback when variant translated.name is empty.'
        );
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
