<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Integration\Feed;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Service\FeedService;
use RH\Tweakwise\Tests\Unit\Fixtures\ProductFixtureFactory;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
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
 * rendered output against ProductFixtureFactory::rawCases().
 *
 * These tests boot the full Shopware kernel, write real product records to the
 * database, run FeedService::generateFeed(), and parse the resulting XML file.
 * Any divergence between the Twig template and the PHP re-implementation in the
 * unit parity tests (FeedFieldsParityTest / BackendApiParityTest) would be caught
 * here by the actual rendered XML.
 *
 * Fields verified for every case:
 *   <name>       from product.translated.name
 *   <price>      from product.calculatedPrice.unitPrice (Twig: round(2,'common'))
 *   <stock>      from product.availableStock
 *   <brand>      from product.manufacturer.translated.name (absent when no manufacturer)
 *   <url>        contains the canonical SEO path
 *   <groupcode>  present with parent number (grouped) or absent (non-grouped)
 *   visibility   attribute value — VISIBILITY_ALL (30) → PRODUCT_VISIBILITY_CATALOG_SEARCH (4)
 *
 * Manufacturer-from-parent inheritance (case 7) is asserted at the rendered level,
 * confirming FeedService's in-memory setManufacturer() call reaches the template.
 *
 * Image is not asserted: media URLs require a real storage server unavailable in
 * the test database.
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

    // -------------------------------------------------------------------------
    // Data provider
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{
     *     0: array<string, mixed>,
     *     1: array<string, mixed>|null,
     *     2: array<string, mixed>,
     *     3: bool
     * }>
     */
    public static function rawCasesProvider(): array
    {
        return ProductFixtureFactory::rawCases();
    }

    // -------------------------------------------------------------------------
    // Main test
    // -------------------------------------------------------------------------

    /**
     * @dataProvider rawCasesProvider
     *
     * @param array<string, mixed>      $productParams
     * @param array<string, mixed>|null $parentParams
     * @param array<string, mixed>      $expected
     */
    public function testFeedRendersProductFieldsCorrectly(
        array $productParams,
        ?array $parentParams,
        array $expected,
        bool $isGrouped
    ): void {
        // -----------------------------------------------------------------
        // 1. Create parent product (no visibility — parents are excluded from
        //    the feed by buildGroupedProductsFilter() / ProductAvailableFilter)
        // -----------------------------------------------------------------
        if ($parentParams !== null) {
            $parentBuilder = (new ProductBuilder($this->ids, $parentParams['number']))
                ->name($parentParams['name'])
                ->price($parentParams['price'])
                ->stock($parentParams['stock']);

            if (isset($parentParams['brand'])) {
                $parentBuilder->manufacturer($parentParams['brand']);
            }

            $parentBuilder->write($this->getContainer());
        }

        // -----------------------------------------------------------------
        // 2. Create the main (variant / standalone) product with visibility
        // -----------------------------------------------------------------
        $builder = (new ProductBuilder($this->ids, $productParams['number']))
            ->name($productParams['name'])
            ->price($productParams['price'])
            ->stock($productParams['stock'])
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL);

        if (isset($productParams['brand'])) {
            $builder->manufacturer($productParams['brand']);
        }

        if ($parentParams !== null) {
            $builder->parent($parentParams['number']);
        }

        $builder->write($this->getContainer());

        $productId = $this->ids->get($productParams['number']);

        // -----------------------------------------------------------------
        // 3. Create a canonical SEO URL
        //    (FeedService::getUrlOfEntity filters by isCanonical = true)
        // -----------------------------------------------------------------
        $this->getContainer()->get('seo_url.repository')->create([[
            'id'             => Uuid::randomHex(),
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'languageId'     => Defaults::LANGUAGE_SYSTEM,
            'foreignKey'     => $productId,
            'routeName'      => 'frontend.detail.page',
            'pathInfo'       => '/detail/' . $productId,
            'seoPathInfo'    => $productParams['seoPath'],
            'isCanonical'    => true,
            'isDeleted'      => false,
            'isModified'     => false,
        ]], $this->context);

        // -----------------------------------------------------------------
        // 4. Find the default sales channel domain
        // -----------------------------------------------------------------
        $domainCriteria = new Criteria();
        $domainCriteria->addFilter(new EqualsFilter('salesChannelId', TestDefaults::SALES_CHANNEL));
        $domainCriteria->setLimit(1);

        $domain = $this->getContainer()
            ->get('sales_channel_domain.repository')
            ->search($domainCriteria, $this->context)
            ->first();

        $this->assertNotNull($domain, 'Default sales channel must have at least one domain.');

        // -----------------------------------------------------------------
        // 5. Create a feed record linked to that domain
        // -----------------------------------------------------------------
        $feedId = Uuid::randomHex();
        $this->getContainer()->get('s_plugin_rhae_tweakwise_feed.repository')->create([[
            'id'                  => $feedId,
            'name'                => 'Test Feed',
            'status'              => FeedEntity::STATUS_QUEUED,
            'interval'            => '0 3 * * *',
            'type'                => 'full',
            'limit'               => '500',
            'groupedProducts'     => $isGrouped,
            'salesChannelDomains' => [['id' => $domain->getId()]],
        ]], $this->context);

        // -----------------------------------------------------------------
        // 6. Load the feed with all associations that FeedService requires
        // -----------------------------------------------------------------
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

        $this->assertNotNull($feed);

        // -----------------------------------------------------------------
        // 7. Generate the feed XML
        // -----------------------------------------------------------------
        /** @var FeedService $feedService */
        $feedService = $this->getContainer()->get(FeedService::class);
        $feedService->generateFeed($feed, $this->context);

        $xml = $feedService->readFeed($feed);
        $this->assertNotNull($xml, 'Generated feed XML must not be null.');
        $this->assertNotEmpty($xml, 'Generated feed XML must not be empty.');

        // -----------------------------------------------------------------
        // 8. Parse XML and locate the product by sw-product-number attribute
        //    (more robust than name lookup: works even when name = '')
        // -----------------------------------------------------------------
        $root = new \SimpleXMLElement($xml);

        $productNumber = $productParams['number'];
        $escapedNumber = htmlspecialchars($productNumber, ENT_XML1, 'UTF-8');

        $matchingItems = $root->xpath(
            sprintf(
                '//item[attributes/attribute[name="sw-product-number" and value="%s"]]',
                $escapedNumber
            )
        );

        $this->assertNotEmpty(
            $matchingItems,
            sprintf('Product "%s" must appear in the rendered feed XML.', $productNumber)
        );

        $item = $matchingItems[0];

        // -----------------------------------------------------------------
        // 9. Assert rendered field values
        // -----------------------------------------------------------------

        // <name> — CDATA-wrapped; SimpleXML returns the text content transparently
        $this->assertSame(
            $expected['Name'],
            (string) $item->name,
            '<name> must equal the product\'s translated name.'
        );

        // <price> — unitPrice rounded to 2dp; Twig round(2,'common') and PHP
        // (string)round($x,2) produce identical strings (both strip trailing zeros)
        $this->assertSame(
            (string) round($productParams['price'], 2),
            (string) $item->price,
            '<price> must equal the product\'s gross unit price rounded to 2dp.'
        );

        // <stock> — product.availableStock
        $this->assertSame(
            (string) $productParams['stock'],
            (string) $item->stock,
            '<stock> must equal the product\'s availableStock.'
        );

        // <brand> — only present when manufacturer is set
        if ($expected['Brand'] === '') {
            $this->assertEmpty(
                (string) $item->brand,
                '<brand> must be absent when the product has no manufacturer.'
            );
        } else {
            $this->assertSame(
                $expected['Brand'],
                (string) $item->brand,
                '<brand> must equal the manufacturer\'s translated name.'
            );
        }

        // <url> — contains the canonical SEO path (domain varies per test env)
        $this->assertStringContainsString(
            $expected['Url'],
            (string) $item->url,
            '<url> must contain the canonical SEO path.'
        );

        // <groupcode> — present with correct value when grouped, absent otherwise
        if ($expected['GroupCode'] === '') {
            $this->assertEmpty(
                (string) $item->groupcode,
                '<groupcode> must be absent in non-grouped mode.'
            );
        } else {
            $this->assertSame(
                $expected['GroupCode'],
                (string) $item->groupcode,
                '<groupcode> must equal the parent\'s product number in grouped mode.'
            );
        }

        // visibility attribute — VISIBILITY_ALL (30) → PRODUCT_VISIBILITY_CATALOG_SEARCH (4)
        $visibilityValue = null;
        foreach ($item->attributes->attribute ?? [] as $attr) {
            if ((string) $attr->name === 'visibility') {
                $visibilityValue = (int) (string) $attr->value;
                break;
            }
        }

        $this->assertSame(
            FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH,
            $visibilityValue,
            'Shopware VISIBILITY_ALL (30) must map to Tweakwise visibility 4 in the rendered XML.'
        );
    }
}
