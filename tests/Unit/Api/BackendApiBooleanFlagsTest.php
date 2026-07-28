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
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Routing\RouterInterface;

/**
 * Tests that the backend sync sends the same boolean flag attributes as the
 * XML feed template: sw-new, sw-free-shipping, sw-has-discount, sw-is-topseller,
 * sw-is-closeout, sw-label.
 *
 * These tests are expected to FAIL until BackendApi::syncProductData() is
 * updated to include these attributes. Once the sync is implemented, all tests
 * in this class must pass — matching the feed's XML output exactly.
 */
class BackendApiBooleanFlagsTest extends TestCase
{
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')->willReturn('/product/test');
    }

    public function testSyncSendsSwFreeShippingTrue(): void
    {
        $product = $this->createProduct();
        $product->setShippingFree(true);

        $posted = $this->sync($product);

        $this->assertSame('true', $this->getAttribute('sw-free-shipping', $posted), 'sw-free-shipping must be "true" when product has free shipping.');
    }

    public function testSyncSendsSwFreeShippingFalse(): void
    {
        $product = $this->createProduct();
        $product->setShippingFree(false);

        $posted = $this->sync($product);

        $this->assertSame('false', $this->getAttribute('sw-free-shipping', $posted), 'sw-free-shipping must be "false" when product has no free shipping.');
    }

    public function testSyncSendsSwIsTopseller(): void
    {
        $product = $this->createProduct();
        $product->setMarkAsTopseller(true);

        $posted = $this->sync($product);

        $this->assertSame('true', $this->getAttribute('sw-is-topseller', $posted), 'sw-is-topseller must be "true" when product is marked as topseller.');
    }

    public function testSyncSendsSwIsTopselerFalse(): void
    {
        $product = $this->createProduct();
        $product->setMarkAsTopseller(false);

        $posted = $this->sync($product);

        $this->assertSame('false', $this->getAttribute('sw-is-topseller', $posted), 'sw-is-topseller must be "false" when product is not a topseller.');
    }

    public function testSyncSendsSwIsCloseoutTrue(): void
    {
        $product = $this->createProduct();
        $product->setIsCloseout(true);

        $posted = $this->sync($product);

        $this->assertSame('true', $this->getAttribute('sw-is-closeout', $posted), 'sw-is-closeout must be "true" when product is a closeout.');
    }

    public function testSyncSendsSwIsCloseoutFalse(): void
    {
        $product = $this->createProduct();
        $product->setIsCloseout(false);

        $posted = $this->sync($product);

        $this->assertSame('false', $this->getAttribute('sw-is-closeout', $posted), 'sw-is-closeout must be "false" when product is not a closeout.');
    }

    public function testSyncSendsSwHasDiscountTrueWhenListPriceHigherThanUnitPrice(): void
    {
        $product = $this->createProduct();

        $listPrice = ListPrice::createFromUnitPrice(15.00, 25.00);
        $price     = new CalculatedPrice(15.00, 15.00, new CalculatedTaxCollection(), new TaxRuleCollection(), 1, null, $listPrice);
        $product->setCalculatedPrice($price);
        $product->assign(['calculatedPrices' => new CalculatedPriceCollection()]);

        $posted = $this->sync($product);

        $this->assertSame('true', $this->getAttribute('sw-has-discount', $posted), 'sw-has-discount must be "true" when list price is higher than unit price.');
    }

    public function testSyncSendsSwHasDiscountFalseWhenNoListPrice(): void
    {
        $product = $this->createProduct();

        $posted = $this->sync($product);

        $this->assertSame('false', $this->getAttribute('sw-has-discount', $posted), 'sw-has-discount must be "false" when no list price is set.');
    }

    public function testSyncSendsSwLabelSoldoutWhenOutOfStock(): void
    {
        $product = $this->createProduct();
        $product->setAvailableStock(0);

        $posted = $this->sync($product);

        $label = $this->getAttribute('sw-label', $posted);
        $this->assertNotNull($label, 'sw-label attribute must be present in sync payload.');
        $this->assertNotEmpty($label, 'sw-label must not be empty for an out-of-stock product.');
    }

    public function testSyncSendsSwLabelEmptyWhenInStockWithNoSpecialFlags(): void
    {
        $product = $this->createProduct();
        $product->setAvailableStock(5);

        $posted = $this->sync($product);

        $label = $this->getAttribute('sw-label', $posted);
        $this->assertNotNull($label, 'sw-label attribute must be present in sync payload.');
        $this->assertSame('', $label, 'sw-label must be empty for a normal in-stock product.');
    }

    public function testSyncSendsSwNewTrueForNewProduct(): void
    {
        $product = $this->createProduct();
        // isNew is a runtime field on SalesChannelProductEntity
        $product->setIsNew(true);

        $posted = $this->sync($product);

        $this->assertSame('true', $this->getAttribute('sw-new', $posted), 'sw-new must be "true" when product isNew is true.');
    }

    public function testSyncSendsSwNewFalseForOldProduct(): void
    {
        $product = $this->createProduct();
        $product->setIsNew(false);

        $posted = $this->sync($product);

        $this->assertSame('false', $this->getAttribute('sw-new', $posted), 'sw-new must be "false" when product isNew is false.');
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

    private function sync(SalesChannelProductEntity $product): array
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

        $api->syncProductData($product, $this->createFrontend(), null, []);

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
            'properties'   => [],
            'customFields' => [],
        ]);

        return $frontend;
    }
}
