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
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Routing\RouterInterface;

/**
 * Tests that the backend sync sends the same product info attributes as the
 * XML feed: sw-id, sw-product-number, sw-ean, sw-manufacturer-productnumber,
 * sw-release-date, sw-description, sw-keywords, sw-delivery-time, sw-avg-rating.
 *
 * Tests cover filled values, empty/absent values, and variant inheritance.
 *
 * These tests are expected to FAIL until BackendApi::syncProductData() is
 * updated to include these attributes.
 */
class BackendApiProductInfoTest extends TestCase
{
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')->willReturn('/product/test');
    }

    // =========================================================================
    // sw-id
    // =========================================================================

    public function testSyncSendsSwId(): void
    {
        $product = $this->createProduct();
        $posted  = $this->sync($product);

        $this->assertSame($product->getId(), $this->getAttribute('sw-id', $posted), 'sw-id must equal the product UUID.');
    }

    // =========================================================================
    // sw-product-number
    // =========================================================================

    public function testSyncSendsSwProductNumber(): void
    {
        $product = $this->createProduct();
        $posted  = $this->sync($product);

        $this->assertSame('TEST-001', $this->getAttribute('sw-product-number', $posted), 'sw-product-number must equal the product number.');
    }

    // =========================================================================
    // sw-ean
    // =========================================================================

    public function testSyncSendsSwEanWithValue(): void
    {
        $product = $this->createProduct();
        $product->setEan('1234567890123');

        $posted = $this->sync($product);

        $this->assertSame('1234567890123', $this->getAttribute('sw-ean', $posted));
    }

    public function testSyncSendsSwEanEmptyWhenNotSet(): void
    {
        $product = $this->createProduct();
        // ean not set

        $posted = $this->sync($product);

        $this->assertSame('', $this->getAttribute('sw-ean', $posted));
    }

    public function testSyncSendsSwEanFromParentWhenVariantHasNone(): void
    {
        $variant = $this->createProduct();
        // variant has no own EAN

        $parent = $this->createProduct();
        $parent->setEan('9876543210987');

        $posted = $this->sync($variant, $parent);

        $this->assertSame('9876543210987', $this->getAttribute('sw-ean', $posted), 'sw-ean must fall back to parent when variant has none.');
    }

    // =========================================================================
    // sw-manufacturer-productnumber
    // =========================================================================

    public function testSyncSendsSwManufacturerProductNumberWithValue(): void
    {
        $product = $this->createProduct();
        $product->setManufacturerNumber('MFR-001');

        $posted = $this->sync($product);

        $this->assertSame('MFR-001', $this->getAttribute('sw-manufacturer-productnumber', $posted));
    }

    public function testSyncSendsSwManufacturerProductNumberEmptyWhenNotSet(): void
    {
        $product = $this->createProduct();

        $posted = $this->sync($product);

        $this->assertSame('', $this->getAttribute('sw-manufacturer-productnumber', $posted));
    }

    public function testSyncSendsSwManufacturerProductNumberFromParentWhenVariantHasNone(): void
    {
        $variant = $this->createProduct();

        $parent = $this->createProduct();
        $parent->setManufacturerNumber('MFR-PARENT');

        $posted = $this->sync($variant, $parent);

        $this->assertSame('MFR-PARENT', $this->getAttribute('sw-manufacturer-productnumber', $posted), 'sw-manufacturer-productnumber must fall back to parent.');
    }

    // =========================================================================
    // sw-release-date
    // =========================================================================

    public function testSyncSendsSwReleaseDateWithValue(): void
    {
        $product = $this->createProduct();
        $product->setReleaseDate(new \DateTimeImmutable('2024-06-15'));

        $posted = $this->sync($product);

        $this->assertSame('2024-06-15', $this->getAttribute('sw-release-date', $posted));
    }

    public function testSyncSendsSwReleaseDateEmptyWhenNotSet(): void
    {
        $product = $this->createProduct();

        $posted = $this->sync($product);

        $this->assertSame('', $this->getAttribute('sw-release-date', $posted));
    }

    // =========================================================================
    // sw-description
    // =========================================================================

    public function testSyncSendsSwDescriptionWithValue(): void
    {
        $product = $this->createProduct();
        $product->setTranslated(['name' => 'Test', 'description' => '<p>Great product description.</p>']);

        $posted = $this->sync($product);

        $this->assertStringContainsString('Great product description', $this->getAttribute('sw-description', $posted) ?? '');
    }

    public function testSyncSendsSwDescriptionEmptyWhenNotSet(): void
    {
        $product = $this->createProduct();

        $posted = $this->sync($product);

        $this->assertSame('', $this->getAttribute('sw-description', $posted));
    }

    public function testSyncSendsSwDescriptionFromParentWhenVariantHasNone(): void
    {
        $variant = $this->createProduct();

        $parent = $this->createProduct();
        $parent->setTranslated(['name' => 'Parent', 'description' => 'Inherited description.']);

        $posted = $this->sync($variant, $parent);

        $this->assertStringContainsString('Inherited description', $this->getAttribute('sw-description', $posted) ?? '', 'sw-description must fall back to parent.');
    }

    // =========================================================================
    // sw-keywords
    // =========================================================================

    public function testSyncSendsSwKeywordsWithValues(): void
    {
        $product = $this->createProduct();
        $product->setCustomSearchKeywords(['keyword1', 'keyword2']);

        $posted = $this->sync($product);

        $keywords = $this->getAttribute('sw-keywords', $posted);
        $this->assertStringContainsString('keyword1', $keywords ?? '');
        $this->assertStringContainsString('keyword2', $keywords ?? '');
    }

    public function testSyncSendsSwKeywordsEmptyWhenNotSet(): void
    {
        $product = $this->createProduct();

        $posted = $this->sync($product);

        $this->assertSame('', $this->getAttribute('sw-keywords', $posted));
    }

    // =========================================================================
    // sw-delivery-time
    // =========================================================================

    public function testSyncSendsSwDeliveryTimeWithValue(): void
    {
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setId(Uuid::randomHex());
        $deliveryTime->setTranslated(['name' => '2-3 days']);

        $product = $this->createProduct();
        $product->setDeliveryTime($deliveryTime);

        $posted = $this->sync($product);

        $this->assertSame('2-3 days', $this->getAttribute('sw-delivery-time', $posted));
    }

    public function testSyncSendsSwDeliveryTimeEmptyWhenNotSet(): void
    {
        $product = $this->createProduct();

        $posted = $this->sync($product);

        $this->assertSame('', $this->getAttribute('sw-delivery-time', $posted));
    }

    public function testSyncSendsSwDeliveryTimeFromParentWhenVariantHasNone(): void
    {
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setId(Uuid::randomHex());
        $deliveryTime->setTranslated(['name' => '1-2 weeks']);

        $variant = $this->createProduct();

        $parent = $this->createProduct();
        $parent->setDeliveryTime($deliveryTime);

        $posted = $this->sync($variant, $parent);

        $this->assertSame('1-2 weeks', $this->getAttribute('sw-delivery-time', $posted), 'sw-delivery-time must fall back to parent.');
    }

    // =========================================================================
    // sw-avg-rating
    // =========================================================================

    public function testSyncSendsSwAvgRatingWithValue(): void
    {
        $product = $this->createProduct();
        $product->setRatingAverage(4.5);

        $posted = $this->sync($product);

        $this->assertSame('4.5', $this->getAttribute('sw-avg-rating', $posted));
    }

    public function testSyncSendsSwAvgRatingEmptyWhenNotSet(): void
    {
        $product = $this->createProduct();

        $posted = $this->sync($product);

        $this->assertSame('', $this->getAttribute('sw-avg-rating', $posted));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getAttribute(string $key, array $posted): ?string
    {
        foreach ($posted['Attributes'] ?? [] as $attr) {
            if ($attr['Key'] === $key) {
                return $attr['Values'][0] ?? null;
            }
        }

        return null;
    }

    private function sync(SalesChannelProductEntity $product, ?SalesChannelProductEntity $parent = null): array
    {
        $history = [];
        $mock    = new MockHandler([new Response(200, [], '{}')]);
        $stack   = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $api = $this->getMockBuilder(BackendApi::class)
            ->setConstructorArgs(['instance-key', 'access-token', $this->router, $client])
            ->onlyMethods(['getProductData', 'getCategoryData'])
            ->getMock();

        $api->method('getProductData')->willReturn(['error' => true, 'code' => 404, 'message' => 'Not Found']);
        $api->method('getCategoryData')->willReturn([]);

        $api->syncProductData($product, $this->createFrontend(), $parent, []);

        return json_decode((string) $history[0]['request']->getBody(), true);
    }

    private function createProduct(): SalesChannelProductEntity
    {
        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setIsCanonical(true);
        $seoUrl->setSeoPathInfo('product/test');

        $price = new CalculatedPrice(19.99, 19.99, new CalculatedTaxCollection(), new TaxRuleCollection());

        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber('TEST-001');
        $product->setTranslated(['name' => 'Test Product']);
        $product->setAvailableStock(10);
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));
        $product->setCalculatedPrice($price);
        $product->assign([
            'calculatedPrices' => new CalculatedPriceCollection(),
            'categories'       => new CategoryCollection(),
            'streams'          => new ProductStreamCollection(),
        ]);

        return $product;
    }

    private function createFrontend(): FrontendEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id');
        $domain->setUrl('https://example.com');

        $frontend = new FrontendEntity();
        $frontend->setSalesChannelDomains(new SalesChannelDomainCollection([$domain]));
        $frontend->setBackendSyncProperties([
            'main'         => [
                'name'           => true,
                'unitPrice'      => true,
                'availableStock' => true,
                'manufacturer'   => true,
                'url'            => true,
                'images'         => true,
                'categories'     => true,
                'groupcode'      => true,
            ],
            'swAttributes' => [
                'sw-free-shipping'             => true,
                'sw-is-topseller'              => true,
                'sw-is-closeout'               => true,
                'sw-has-discount'              => true,
                'sw-new'                       => true,
                'sw-label'                     => true,
                'sw-id'                        => true,
                'sw-product-number'            => true,
                'sw-ean'                       => true,
                'sw-manufacturer-productnumber' => true,
                'sw-release-date'              => true,
                'sw-description'               => true,
                'sw-keywords'                  => true,
                'sw-delivery-time'             => true,
                'sw-avg-rating'                => true,
            ],
            'properties'   => [],
            'customFields' => [],
        ]);

        return $frontend;
    }
}
