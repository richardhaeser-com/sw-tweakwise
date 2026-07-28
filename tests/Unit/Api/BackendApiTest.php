<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Api\BackendApi;
use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Routing\RouterInterface;

class BackendApiTest extends TestCase
{
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')->willReturn('/product/test');
    }

    private function createFrontend(): FrontendEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id');
        $domain->setUrl('https://example.com');

        $frontend = new FrontendEntity();
        $frontend->setSalesChannelDomains(new SalesChannelDomainCollection([$domain]));
        $frontend->setBackendSyncProperties([
            'main' => [
                'name' => true,
                'unitPrice' => true,
                'availableStock' => true,
                'manufacturer' => true,
                'url' => true,
                'images' => true,
                'categories' => true,
                'groupcode' => true,
            ],
            'properties' => [],
            'customFields' => [],
        ]);

        return $frontend;
    }

    private function createBaseProduct(): SalesChannelProductEntity
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId(Uuid::randomHex());
        $manufacturer->setTranslated(['name' => 'Test Brand']);

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setIsCanonical(true);
        $seoUrl->setSeoPathInfo('product/test-product');

        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('TEST-001');
        $product->setTranslated(['name' => 'Test Product']);
        $product->setAvailableStock(10);
        $product->setManufacturer($manufacturer);
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));
        $product->setCalculatedPrice(new CalculatedPrice(29.99, 29.99, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $product->assign([
            'calculatedPrices' => new CalculatedPriceCollection(),
            'categories' => new CategoryCollection(),
            'streams' => new ProductStreamCollection(),
        ]);

        return $product;
    }

    /**
     * Creates a Guzzle client with a MockHandler that returns a 200 for the POST call
     * and captures all requests into $history.
     *
     * getProductData() and getCategoryData() are mocked on BackendApi directly,
     * so only the final POST/PATCH hits the Guzzle client.
     */
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

        // Simulate product not yet in Tweakwise → triggers POST path
        $api->method('getProductData')->willReturn(['error' => true, 'code' => 404, 'message' => 'Not Found']);
        $api->method('getCategoryData')->willReturn([]);

        return $api;
    }

    private function getPostedData(array $history): array
    {
        return json_decode((string) $history[0]['request']->getBody(), true);
    }

    public function testNameUsesProductTranslation(): void
    {
        $history = [];
        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($this->createBaseProduct(), $this->createFrontend(), null, []);

        $this->assertEquals('Test Product', $this->getPostedData($history)['Name']);
    }

    /**
     * When a variant has no translated name the sync must fall back to the parent's
     * translated name. This mirrors the real production behaviour: Shopware's DAL
     * resolves translation inheritance so the feed template always receives the
     * parent name via product.translated.name. The sync must apply the same fallback
     * explicitly because the entity may arrive without DAL inheritance resolved.
     */
    public function testNameFallsBackToParentWhenVariantNameIsEmpty(): void
    {
        $history = [];
        $product = $this->createBaseProduct();
        $product->setTranslated([]);

        $parent = $this->createBaseProduct();
        $parent->setTranslated(['name' => 'Parent Product']);

        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, $this->createFrontend(), $parent, []);

        $this->assertSame(
            'Parent Product',
            $this->getPostedData($history)['Name'],
            'Sync must fall back to parent name when variant translated.name is empty.'
        );
    }

    public function testPriceUsesCalculatedUnitPrice(): void
    {
        $history = [];
        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($this->createBaseProduct(), $this->createFrontend(), null, []);

        $this->assertEquals(29.99, $this->getPostedData($history)['Price']);
    }

    public function testStockUsesAvailableStock(): void
    {
        $history = [];
        $product = $this->createBaseProduct();
        $product->setAvailableStock(42);

        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, $this->createFrontend(), null, []);

        $this->assertEquals(42, $this->getPostedData($history)['Stock']);
    }

    public function testBrandUsesManufacturerName(): void
    {
        $history = [];
        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($this->createBaseProduct(), $this->createFrontend(), null, []);

        $this->assertEquals('Test Brand', $this->getPostedData($history)['Brand']);
    }

    public function testUrlUsesCanonicalSeoUrl(): void
    {
        $history = [];
        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($this->createBaseProduct(), $this->createFrontend(), null, []);

        $this->assertEquals('https://example.com/product/test-product', $this->getPostedData($history)['Url']);
    }

    public function testImageUsesLargestThumbnail(): void
    {
        $history = [];

        $small = new MediaThumbnailEntity();
        $small->setId(Uuid::randomHex());
        $small->setWidth(200);
        $small->setUrl('https://example.com/small.jpg');

        $large = new MediaThumbnailEntity();
        $large->setId(Uuid::randomHex());
        $large->setWidth(800);
        $large->setUrl('https://example.com/large.jpg');

        $medium = new MediaThumbnailEntity();
        $medium->setId(Uuid::randomHex());
        $medium->setWidth(400);
        $medium->setUrl('https://example.com/medium.jpg');

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setUrl('https://example.com/original.jpg');
        $media->setThumbnails(new MediaThumbnailCollection([$small, $large, $medium]));

        $cover = new ProductMediaEntity();
        $cover->setId(Uuid::randomHex());
        $cover->setMedia($media);

        $product = $this->createBaseProduct();
        $product->setCover($cover);

        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, $this->createFrontend(), null, []);

        $this->assertEquals('https://example.com/large.jpg', $this->getPostedData($history)['Image']);
    }

    public function testImageFallsBackToOriginalUrlWhenNoThumbnails(): void
    {
        $history = [];

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setUrl('https://example.com/original.jpg');
        $media->setThumbnails(new MediaThumbnailCollection());

        $cover = new ProductMediaEntity();
        $cover->setId(Uuid::randomHex());
        $cover->setMedia($media);

        $product = $this->createBaseProduct();
        $product->setCover($cover);

        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, $this->createFrontend(), null, []);

        $this->assertEquals('https://example.com/original.jpg', $this->getPostedData($history)['Image']);
    }

    public function testGroupCodeUsesParentProductNumber(): void
    {
        $history = [];

        $product = $this->createBaseProduct();
        $product->setProductNumber('VARIANT-001');

        $parent = $this->createBaseProduct();
        $parent->setProductNumber('PARENT-001');

        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, $this->createFrontend(), $parent, []);

        $this->assertEquals('PARENT-001', $this->getPostedData($history)['GroupCode']);
    }

    public function testGroupCodeUsesOwnProductNumberWhenNoParent(): void
    {
        $history = [];

        $product = $this->createBaseProduct();
        $product->setProductNumber('STANDALONE-001');

        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, $this->createFrontend(), null, []);

        $this->assertEquals('STANDALONE-001', $this->getPostedData($history)['GroupCode']);
    }

    /**
     * The XML feed exports a 'visibility' attribute (1 / 3 / 4) so that Tweakwise can
     * differentiate link-only, search-only, and fully visible products.
     *
     * The backend sync has no corresponding 'visibility' field: it operates on individual
     * products triggered from the admin UI and does not replicate that attribute.
     *
     * This test documents and guards that design decision — if visibility is accidentally
     * added to the sync payload this test will fail and force a deliberate review.
     */
    public function testSyncDoesNotSendVisibilityField(): void
    {
        $history = [];
        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($this->createBaseProduct(), $this->createFrontend(), null, []);

        $posted = $this->getPostedData($history);
        $this->assertArrayNotHasKey(
            'visibility',
            $posted,
            'The backend sync must not send a "visibility" field. ' .
            'Visibility is intentionally only exported via the XML feed.'
        );
    }

    /**
     * A variant that is out of stock (availableStock = 0) must report Stock = 0, not the
     * parent\'s stock. This guards against the ?: vs ?? footgun where 0 is falsy in PHP.
     */
    public function testStockZeroVariantDoesNotFallBackToParentStock(): void
    {
        $history = [];

        $product = $this->createBaseProduct();
        $product->setAvailableStock(0);

        $parent = $this->createBaseProduct();
        $parent->setAvailableStock(99);

        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, $this->createFrontend(), $parent, []);

        $this->assertSame(0, $this->getPostedData($history)['Stock']);
    }
}
