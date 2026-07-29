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
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Routing\RouterInterface;

/**
 * Tests that every sw-* attribute can be individually toggled via the
 * backendSyncProperties['swAttributes'] settings key.
 *
 * Each attribute must be:
 *  - present in the sync payload when its config value is true (or absent from config)
 *  - absent from the sync payload when its config value is false
 *
 * Backwards-compatibility: when the 'swAttributes' key is missing entirely (existing
 * installs that haven't saved the new settings yet) all attributes must still be sent.
 */
class BackendApiSwAttributesConfigTest extends TestCase
{
    private RouterInterface $router;

    /** All 15 configurable sw-* attribute keys */
    private const ALL_SW_ATTRIBUTES = [
        'sw-free-shipping',
        'sw-is-topseller',
        'sw-is-closeout',
        'sw-has-discount',
        'sw-new',
        'sw-label',
        'sw-id',
        'sw-product-number',
        'sw-ean',
        'sw-manufacturer-productnumber',
        'sw-release-date',
        'sw-description',
        'sw-keywords',
        'sw-delivery-time',
        'sw-avg-rating',
    ];

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')->willReturn('/product/test');
    }

    // =========================================================================
    // Backwards-compatibility: no 'swAttributes' key → all attributes present
    // =========================================================================

    public function testAllSwAttributesSentWhenSwAttributesKeyMissing(): void
    {
        $frontend = $this->createFrontend([]);  // 'swAttributes' key absent
        $posted   = $this->sync($this->createProduct(), $frontend);

        foreach (self::ALL_SW_ATTRIBUTES as $key) {
            $this->assertAttributePresent($key, $posted, "'{$key}' must be sent when swAttributes config is absent (backwards-compat).");
        }
    }

    public function testAllSwAttributesSentWhenSwAttributesIsEmptyArray(): void
    {
        $frontend = $this->createFrontend([]);  // empty swAttributes array
        $posted   = $this->sync($this->createProduct(), $frontend);

        foreach (self::ALL_SW_ATTRIBUTES as $key) {
            $this->assertAttributePresent($key, $posted, "'{$key}' must be sent when swAttributes config is an empty array (backwards-compat).");
        }
    }

    // =========================================================================
    // Individual attribute exclusion: set to false → must NOT appear in payload
    // =========================================================================

    /**
     * @dataProvider swAttributeKeyProvider
     */
    public function testAttributeAbsentWhenDisabledInConfig(string $attributeKey): void
    {
        $swAttributes = array_fill_keys(self::ALL_SW_ATTRIBUTES, true);
        $swAttributes[$attributeKey] = false;

        $frontend = $this->createFrontend($swAttributes);
        $posted   = $this->sync($this->createProduct(), $frontend);

        $this->assertAttributeAbsent($attributeKey, $posted, "'{$attributeKey}' must NOT be sent when its config value is false.");
    }

    /**
     * @dataProvider swAttributeKeyProvider
     */
    public function testOtherAttributesPresentWhenOneIsDisabled(string $disabledKey): void
    {
        $swAttributes = array_fill_keys(self::ALL_SW_ATTRIBUTES, true);
        $swAttributes[$disabledKey] = false;

        $frontend = $this->createFrontend($swAttributes);
        $posted   = $this->sync($this->createProduct(), $frontend);

        foreach (self::ALL_SW_ATTRIBUTES as $key) {
            if ($key === $disabledKey) {
                continue;
            }
            $this->assertAttributePresent($key, $posted, "'{$key}' must still be sent when only '{$disabledKey}' is disabled.");
        }
    }

    /**
     * @dataProvider swAttributeKeyProvider
     */
    public function testAttributePresentWhenEnabledInConfig(string $attributeKey): void
    {
        $swAttributes = array_fill_keys(self::ALL_SW_ATTRIBUTES, true);
        $swAttributes[$attributeKey] = true;

        $frontend = $this->createFrontend($swAttributes);
        $posted   = $this->sync($this->createProduct(), $frontend);

        $this->assertAttributePresent($attributeKey, $posted, "'{$attributeKey}' must be sent when its config value is true.");
    }

    // =========================================================================
    // All attributes disabled at once
    // =========================================================================

    public function testNoSwAttributesSentWhenAllDisabled(): void
    {
        $swAttributes = array_fill_keys(self::ALL_SW_ATTRIBUTES, false);

        $frontend = $this->createFrontend($swAttributes);
        $posted   = $this->sync($this->createProduct(), $frontend);

        foreach (self::ALL_SW_ATTRIBUTES as $key) {
            $this->assertAttributeAbsent($key, $posted, "'{$key}' must NOT be sent when all swAttributes are disabled.");
        }
    }

    // =========================================================================
    // Data provider
    // =========================================================================

    public static function swAttributeKeyProvider(): array
    {
        return array_combine(
            self::ALL_SW_ATTRIBUTES,
            array_map(static fn (string $k) => [$k], self::ALL_SW_ATTRIBUTES),
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function assertAttributePresent(string $key, array $posted, string $message = ''): void
    {
        $found = false;
        foreach ($posted['Attributes'] ?? [] as $attr) {
            if ($attr['Key'] === $key) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, $message ?: "Expected attribute '{$key}' to be present in the sync payload.");
    }

    private function assertAttributeAbsent(string $key, array $posted, string $message = ''): void
    {
        foreach ($posted['Attributes'] ?? [] as $attr) {
            if ($attr['Key'] === $key) {
                $this->fail($message ?: "Expected attribute '{$key}' to be absent from the sync payload, but it was found.");
            }
        }
        $this->addToAssertionCount(1);
    }

    private function getAttribute(string $key, array $posted): ?string
    {
        foreach ($posted['Attributes'] ?? [] as $attr) {
            if ($attr['Key'] === $key) {
                return $attr['Values'][0] ?? null;
            }
        }

        return null;
    }

    private function sync(SalesChannelProductEntity $product, FrontendEntity $frontend): array
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

        $api->syncProductData($product, $frontend, null, []);

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
        $product->setShippingFree(false);
        $product->setMarkAsTopseller(false);
        $product->setIsCloseout(false);
        $product->setIsNew(false);
        $product->assign([
            'calculatedPrices' => new CalculatedPriceCollection(),
            'categories'       => new CategoryCollection(),
            'streams'          => new ProductStreamCollection(),
        ]);

        return $product;
    }

    /**
     * @param array<string, bool> $swAttributes  Key → enabled mapping.
     *                                            Pass an empty array to omit the 'swAttributes' key entirely.
     */
    private function createFrontend(array $swAttributes): FrontendEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id');
        $domain->setUrl('https://example.com');

        $properties = [
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
        ];

        if ($swAttributes !== []) {
            $properties['swAttributes'] = $swAttributes;
        }

        $frontend = new FrontendEntity();
        $frontend->setSalesChannelDomains(new SalesChannelDomainCollection([$domain]));
        $frontend->setBackendSyncProperties($properties);

        return $frontend;
    }
}
