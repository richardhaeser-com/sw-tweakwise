<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Integration\Feed;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Service\FeedService;
use RH\Tweakwise\Tests\Unit\Fixtures\ProductFixtureFactory;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * Integration tests that generate a real Tweakwise XML feed and assert the
 * rendered output against expected values.
 *
 * The data-provider-driven test testFeedXmlMatchesExpected() is the primary
 * parity test: it uses the same ProductFixtureFactory::rawCases() fixture
 * definitions as BackendApiParityTest, ensuring that any case added to the
 * shared fixture is automatically verified in both the rendered XML feed and
 * the backend sync POST body.
 *
 * Feed-only behaviour (product exclusion, visibility mapping, excludeChildren)
 * is covered by the standalone tests below the parity section.
 */
class FeedXmlOutputTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;
    private Context $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
    }

    // =========================================================================
    // Parity test — driven by the same fixture definitions as BackendApiParityTest
    // =========================================================================

    public static function rawCases(): array
    {
        return ProductFixtureFactory::rawCases();
    }

    /**
     * For every case in ProductFixtureFactory::rawCases(), insert the products
     * into the real database, generate the feed, and assert that the rendered
     * XML <item> matches the expected field values.
     *
     * This test shares its fixture definitions with BackendApiParityTest so
     * that both the feed and the sync are always verified against identical
     * expected values. Adding a new case to rawCases() automatically exercises
     * it in both places.
     *
     * @param array<string, mixed>      $productParams
     * @param array<string, mixed>|null $parentParams
     * @param array<string, mixed>      $expected
     */
    #[DataProvider('rawCases')]
    public function testFeedXmlMatchesExpected(
        ?array $productParams,
        ?array $parentParams,
        array $expected,
        bool $isGrouped
    ): void {
        $productNumber = $this->insertProducts($productParams, $parentParams);

        $xml  = $this->generateFeed(grouped: $isGrouped);
        $item = $this->findItem($xml, $productNumber);

        foreach ($expected as $field => $expectedValue) {
            match ($field) {
                'Name'      => $this->assertSame((string) $expectedValue, (string) $item->name, '<name> mismatch for field Name'),
                'Price'     => $this->assertSame((string) (float) $expectedValue, (string) (float) $item->price, '<price> mismatch'),
                'Stock'     => $this->assertSame((string) $expectedValue, (string) $item->stock, '<stock> mismatch'),
                'Brand'     => $expectedValue === ''
                    ? $this->assertEmpty((string) $item->brand, '<brand> must be absent')
                    : $this->assertSame((string) $expectedValue, (string) $item->brand, '<brand> mismatch'),
                'GroupCode' => $expectedValue === ''
                    ? $this->assertEmpty((string) $item->groupcode, '<groupcode> must be absent')
                    : $this->assertSame((string) $expectedValue, (string) $item->groupcode, '<groupcode> mismatch'),
                'Url'       => $this->assertStringContainsString((string) $expectedValue, (string) $item->url, '<url> must contain seo path'),
                default     => null,
            };
        }
    }

    // =========================================================================
    // Feed-only: product exclusion
    // =========================================================================

    public function testInactiveProductIsExcludedFromFeed(): void
    {
        $this->createStandaloneProduct('PROD-ACTIVE', 'Active Product', 5, 'Brand', 10.00, 'product/active');

        (new ProductBuilder($this->ids, 'PROD-INACTIVE'))
            ->name('Inactive Product')
            ->price(10.00)
            ->stock(5)
            ->active(false)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $xml = $this->generateFeed(grouped: false);

        $this->assertItemPresent($xml, 'PROD-ACTIVE', 'Active product must appear in the feed.');
        $this->assertItemAbsent($xml, 'PROD-INACTIVE', 'Inactive product must not appear in the feed.');
    }

    public function testProductWithNoVisibilityIsExcludedFromFeed(): void
    {
        $this->createStandaloneProduct('PROD-VISIBLE', 'Visible Product', 3, 'Brand', 15.00, 'product/visible');

        (new ProductBuilder($this->ids, 'PROD-NO-VIS'))
            ->name('No Visibility Product')
            ->price(15.00)
            ->stock(3)
            ->write($this->getContainer());

        $xml = $this->generateFeed(grouped: false);

        $this->assertItemPresent($xml, 'PROD-VISIBLE', 'Product with visibility must appear in the feed.');
        $this->assertItemAbsent($xml, 'PROD-NO-VIS', 'Product with no visibility record must not appear in the feed.');
    }

    public function testProductWithVisibilityLinkAppearsInFeedWithVisibility1(): void
    {
        $productId = $this->ids->create('PROD-LINK');

        (new ProductBuilder($this->ids, 'PROD-LINK'))
            ->name('Link-Only Product')
            ->price(20.00)
            ->stock(2)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_LINK)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/link-only');

        $xml  = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'PROD-LINK');

        $this->assertSame(FeedService::PRODUCT_NOT_VISIBLE, $this->extractVisibility($item));
    }

    public function testProductWithVisibilitySearchAppearsInFeedWithVisibility3(): void
    {
        $productId = $this->ids->create('PROD-SEARCH');

        (new ProductBuilder($this->ids, 'PROD-SEARCH'))
            ->name('Search-Only Product')
            ->price(25.00)
            ->stock(4)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_SEARCH)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/search-only');

        $xml  = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'PROD-SEARCH');

        $this->assertSame(FeedService::PRODUCT_VISIBILITY_SEARCH, $this->extractVisibility($item));
    }

    public function testExcludeChildrenFeedOmitsVariants(): void
    {
        $this->createStandaloneProduct('PROD-STANDALONE-EC', 'Standalone EC', 5, 'Brand', 10.00, 'product/standalone-ec');

        $variantId = $this->ids->create('variant-ec');

        (new ProductBuilder($this->ids, 'parent-ec'))
            ->name('Parent EC')
            ->price(20.00)
            ->stock(0)
            ->manufacturer('Brand')
            ->variantListingConfig(['displayParent' => true])
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-ec'))
            ->name('Variant EC')
            ->price(20.00)
            ->stock(3)
            ->parent('parent-ec')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($this->ids->get('parent-ec'), 'product/parent-ec');
        $this->createSeoUrl($variantId, 'product/variant-ec');
        $this->updateVariantListing([$this->ids->get('parent-ec')]);

        $xml = $this->generateFeed(grouped: false, excludeChildren: true);

        $this->assertItemPresent($xml, 'PROD-STANDALONE-EC', 'Standalone must appear in excludeChildren feed.');
        $this->assertItemAbsent($xml, 'variant-ec', 'Variant must not appear in excludeChildren feed.');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Inserts one or two products into the database from raw param arrays and
     * returns the product number of the item that will appear in the feed
     * (always the variant/product, never the parent).
     *
     * @param array<string, mixed>      $productParams
     * @param array<string, mixed>|null $parentParams
     */
    private function insertProducts(?array $productParams, ?array $parentParams): string
    {
        $parentId = null;

        if ($parentParams !== null) {
            $parentId      = $this->ids->create($parentParams['number']);
            $listingMode   = $parentParams['listingMode'] ?? null;
            $parentBuilder = (new ProductBuilder($this->ids, $parentParams['number']))
                ->name($parentParams['name'])
                ->price($parentParams['price'])
                ->stock($parentParams['stock']);

            if (isset($parentParams['brand'])) {
                $parentBuilder->manufacturer($parentParams['brand']);
            }

            $variantListingConfig = match ($listingMode) {
                'displayParent' => ['displayParent' => true],
                'expandVariants' => ['configuratorGroupConfig' => []],
                default         => [],
            };

            // mainVariantId requires the variant ID, set after writing — skip config for now
            if ($listingMode !== 'mainVariantId' && $variantListingConfig !== []) {
                $parentBuilder->variantListingConfig($variantListingConfig);
            }

            $parentBuilder->write($this->getContainer());
            $this->createSeoUrl($parentId, $parentParams['seoPath']);
        }

        $productId = $this->ids->create($productParams['number']);

        if (!empty($productParams['emptyName'])) {
            // Write the variant directly via the repository without any translation record.
            // This mirrors the real Shopware production scenario: variants created without
            // their own name have no product_translation row, so COALESCE returns NULL for
            // the variant's name and the DAL falls back to the parent's translation.
            // Using ->translation(LANGUAGE_SYSTEM, 'name', '') would store an explicit empty
            // string, which COALESCE does NOT treat as a fallback candidate.
            $taxCriteria = new Criteria();
            $taxCriteria->setLimit(1);
            $taxId = $this->getContainer()->get('tax.repository')
                ->searchIds($taxCriteria, $this->context)
                ->firstId();

            $this->getContainer()->get('product.repository')->create([[
                'id'            => $productId,
                'productNumber' => $productParams['number'],
                'stock'         => $productParams['stock'],
                'price'         => [['currencyId' => Defaults::CURRENCY, 'gross' => $productParams['price'], 'net' => $productParams['price'], 'linked' => false]],
                'taxId'         => $taxId,
                'parentId'      => $parentId,
                'visibilities'  => [[
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility'     => ProductVisibilityDefinition::VISIBILITY_ALL,
                ]],
            ]], $this->context);
        } else {
            $productBuilder = (new ProductBuilder($this->ids, $productParams['number']))
                ->name($productParams['name'])
                ->price($productParams['price'])
                ->stock($productParams['stock'])
                ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL);

            if (isset($productParams['brand'])) {
                $productBuilder->manufacturer($productParams['brand']);
            }

            if ($parentId !== null) {
                $productBuilder->parent($parentParams['number']);
            }

            $productBuilder->write($this->getContainer());
        }
        $this->createSeoUrl($productId, $productParams['seoPath']);

        // For mainVariantId listing mode, update the parent config now that we have the variant ID
        if (isset($parentParams['listingMode']) && $parentParams['listingMode'] === 'mainVariantId') {
            $this->getContainer()->get('product.repository')->update([[
                'id'                    => $parentId,
                'variantListingConfig'  => ['mainVariantId' => $productId],
            ]], $this->context);
        }

        if ($parentId !== null) {
            $this->updateVariantListing([$parentId]);
        }

        return $productParams['number'];
    }

    private function createStandaloneProduct(
        string $number,
        string $name,
        int $stock,
        string $brand,
        float $price,
        string $seoPath
    ): string {
        $productId = $this->ids->create($number);

        (new ProductBuilder($this->ids, $number))
            ->name($name)
            ->price($price)
            ->stock($stock)
            ->manufacturer($brand)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, $seoPath);

        return $productId;
    }

    private function createSeoUrl(string $productId, string $seoPath): void
    {
        $this->getContainer()->get('seo_url.repository')->create([[
            'id'             => Uuid::randomHex(),
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'languageId'     => Defaults::LANGUAGE_SYSTEM,
            'foreignKey'     => $productId,
            'routeName'      => 'frontend.detail.page',
            'pathInfo'       => '/detail/' . $productId,
            'seoPathInfo'    => $seoPath,
            'isCanonical'    => true,
            'isDeleted'      => false,
            'isModified'     => false,
        ]], $this->context);
    }

    /** @param string[] $parentIds */
    private function updateVariantListing(array $parentIds): void
    {
        $this->getContainer()->get(VariantListingUpdater::class)->update($parentIds, $this->context);
    }

    private function generateFeed(bool $grouped, bool $excludeChildren = false): \SimpleXMLElement
    {
        $domainCriteria = new Criteria();
        $domainCriteria->addFilter(new EqualsFilter('salesChannelId', TestDefaults::SALES_CHANNEL));
        $domainCriteria->setLimit(1);

        $domain = $this->getContainer()
            ->get('sales_channel_domain.repository')
            ->search($domainCriteria, $this->context)
            ->first();

        $this->assertNotNull($domain, 'Default sales channel must have at least one domain.');

        $feedId = Uuid::randomHex();
        $this->getContainer()->get('s_plugin_rhae_tweakwise_feed.repository')->create([[
            'id'                  => $feedId,
            'name'                => 'Test Feed',
            'status'              => FeedEntity::STATUS_QUEUED,
            'interval'            => '0 3 * * *',
            'type'                => 'full',
            'limit'               => '500',
            'groupedProducts'     => $grouped,
            'excludeChildren'     => $excludeChildren,
            'salesChannelDomains' => [['id' => $domain->getId()]],
        ]], $this->context);

        $feedCriteria = new Criteria([$feedId]);
        $feedCriteria->addAssociation('salesChannelDomains');
        $feedCriteria->addAssociation('salesChannelDomains.salesChannel');
        $feedCriteria->addAssociation('salesChannelDomains.language');
        $feedCriteria->addAssociation('salesChannelDomains.language.translationCode');

        /** @var FeedEntity $feed */
        $feed = $this->getContainer()
            ->get('s_plugin_rhae_tweakwise_feed.repository')
            ->search($feedCriteria, $this->context)
            ->first();

        /** @var FeedService $feedService */
        $feedService = $this->getContainer()->get(FeedService::class);
        $feedService->generateFeed($feed, $this->context);

        $xml = $feedService->readFeed($feed);
        $this->assertNotEmpty($xml, 'Generated feed XML must not be empty.');

        return new \SimpleXMLElement($xml);
    }

    private function findItem(\SimpleXMLElement $xml, string $number): \SimpleXMLElement
    {
        $escapedNumber = htmlspecialchars($number, ENT_XML1, 'UTF-8');
        $matches       = $xml->xpath(
            sprintf('//item[attributes/attribute[name="sw-product-number" and value="%s"]]', $escapedNumber)
        );

        $this->assertNotEmpty($matches, sprintf('Product "%s" must appear in the rendered feed XML.', $number));

        return $matches[0];
    }

    private function assertItemPresent(\SimpleXMLElement $xml, string $number, string $message = ''): void
    {
        $escapedNumber = htmlspecialchars($number, ENT_XML1, 'UTF-8');
        $matches       = $xml->xpath(
            sprintf('//item[attributes/attribute[name="sw-product-number" and value="%s"]]', $escapedNumber)
        );

        $this->assertNotEmpty($matches, $message ?: sprintf('Product "%s" must appear in the feed XML.', $number));
    }

    private function assertItemAbsent(\SimpleXMLElement $xml, string $number, string $message = ''): void
    {
        $escapedNumber = htmlspecialchars($number, ENT_XML1, 'UTF-8');
        $matches       = $xml->xpath(
            sprintf('//item[attributes/attribute[name="sw-product-number" and value="%s"]]', $escapedNumber)
        );

        $this->assertEmpty($matches, $message ?: sprintf('Product "%s" must NOT appear in the feed XML.', $number));
    }

    private function extractVisibility(\SimpleXMLElement $item): ?int
    {
        foreach ($item->attributes->attribute ?? [] as $attr) {
            if ((string) $attr->name === 'visibility') {
                return (int) (string) $attr->value;
            }
        }

        return null;
    }
}
