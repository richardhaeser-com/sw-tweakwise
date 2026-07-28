<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Integration\Feed;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Service\FeedService;
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
 * Integration tests verifying that the XML feed correctly emits product info
 * attributes: sw-id, sw-product-number, sw-ean, sw-manufacturer-productnumber,
 * sw-release-date, sw-description, sw-keywords, sw-delivery-time, sw-avg-rating.
 *
 * Tests cover:
 *  - Filled values
 *  - Empty/absent values
 *  - Variant inheriting fields from parent
 *
 * These tests are expected to PASS — the feed template already emits these.
 * The corresponding BackendApiProductInfoTest tests are expected to FAIL.
 */
class FeedProductInfoTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;
    private Context $context;

    protected function setUp(): void
    {
        $this->ids    = new IdsCollection();
        $this->context = Context::createDefaultContext();
    }

    // =========================================================================
    // sw-id
    // =========================================================================

    public function testSwIdContainsProductId(): void
    {
        $productId = $this->ids->create('PROD-ID');

        (new ProductBuilder($this->ids, 'PROD-ID'))
            ->name('Product ID Test')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-id');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-ID');

        $this->assertSame($productId, $this->getAttributeValue('sw-id', $item), 'sw-id must equal the product UUID.');
    }

    // =========================================================================
    // sw-product-number
    // =========================================================================

    public function testSwProductNumberContainsProductNumber(): void
    {
        $productId = $this->ids->create('PROD-NUM');

        (new ProductBuilder($this->ids, 'PROD-NUM'))
            ->name('Product Number Test')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-num');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NUM');

        $this->assertSame('PROD-NUM', $this->getAttributeValue('sw-product-number', $item));
    }

    // =========================================================================
    // sw-ean
    // =========================================================================

    public function testSwEanWithValue(): void
    {
        $productId = $this->ids->create('PROD-EAN');

        (new ProductBuilder($this->ids, 'PROD-EAN'))
            ->name('EAN Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'  => $productId,
            'ean' => '1234567890123',
        ]], $this->context);

        $this->createSeoUrl($productId, 'product/prod-ean');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-EAN');

        $this->assertSame('1234567890123', $this->getAttributeValue('sw-ean', $item));
    }

    public function testSwEanEmptyWhenNotSet(): void
    {
        $productId = $this->ids->create('PROD-NO-EAN');

        (new ProductBuilder($this->ids, 'PROD-NO-EAN'))
            ->name('No EAN Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-no-ean');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-EAN');

        $this->assertSame('', $this->getAttributeValue('sw-ean', $item));
    }

    public function testSwEanInheritedFromParentOnVariant(): void
    {
        $variantId = $this->ids->create('VARIANT-EAN');
        $parentId  = $this->ids->create('PARENT-EAN');

        (new ProductBuilder($this->ids, 'PARENT-EAN'))
            ->name('Parent EAN')
            ->price(10.00)
            ->stock(0)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'  => $parentId,
            'ean' => '9876543210987',
        ]], $this->context);

        (new ProductBuilder($this->ids, 'VARIANT-EAN'))
            ->name('Variant EAN')
            ->price(10.00)
            ->stock(5)
            ->parent('PARENT-EAN')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/variant-ean');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'VARIANT-EAN');

        $this->assertSame('9876543210987', $this->getAttributeValue('sw-ean', $item), 'sw-ean must be inherited from parent when variant has no own EAN.');
    }

    // =========================================================================
    // sw-manufacturer-productnumber
    // =========================================================================

    public function testSwManufacturerProductNumberWithValue(): void
    {
        $productId = $this->ids->create('PROD-MPN');

        (new ProductBuilder($this->ids, 'PROD-MPN'))
            ->name('MPN Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'                 => $productId,
            'manufacturerNumber' => 'MFR-001',
        ]], $this->context);

        $this->createSeoUrl($productId, 'product/prod-mpn');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-MPN');

        $this->assertSame('MFR-001', $this->getAttributeValue('sw-manufacturer-productnumber', $item));
    }

    public function testSwManufacturerProductNumberEmptyWhenNotSet(): void
    {
        $productId = $this->ids->create('PROD-NO-MPN');

        (new ProductBuilder($this->ids, 'PROD-NO-MPN'))
            ->name('No MPN Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-no-mpn');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-MPN');

        $this->assertSame('', $this->getAttributeValue('sw-manufacturer-productnumber', $item));
    }

    public function testSwManufacturerProductNumberInheritedFromParent(): void
    {
        $variantId = $this->ids->create('VARIANT-MPN');
        $parentId  = $this->ids->create('PARENT-MPN');

        (new ProductBuilder($this->ids, 'PARENT-MPN'))
            ->name('Parent MPN')
            ->price(10.00)
            ->stock(0)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'                 => $parentId,
            'manufacturerNumber' => 'MFR-PARENT',
        ]], $this->context);

        (new ProductBuilder($this->ids, 'VARIANT-MPN'))
            ->name('Variant MPN')
            ->price(10.00)
            ->stock(5)
            ->parent('PARENT-MPN')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/variant-mpn');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'VARIANT-MPN');

        $this->assertSame('MFR-PARENT', $this->getAttributeValue('sw-manufacturer-productnumber', $item), 'sw-manufacturer-productnumber must be inherited from parent.');
    }

    // =========================================================================
    // sw-release-date
    // =========================================================================

    public function testSwReleaseDateWithValue(): void
    {
        $productId = $this->ids->create('PROD-RD');

        (new ProductBuilder($this->ids, 'PROD-RD'))
            ->name('Release Date Product')
            ->price(10.00)
            ->stock(5)
            ->releaseDate('2024-06-15')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-rd');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-RD');

        $this->assertSame('2024-06-15', $this->getAttributeValue('sw-release-date', $item));
    }

    public function testSwReleaseDateEmptyWhenNotSet(): void
    {
        $productId = $this->ids->create('PROD-NO-RD');

        (new ProductBuilder($this->ids, 'PROD-NO-RD'))
            ->name('No Release Date Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-no-rd');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-RD');

        $this->assertSame('', $this->getAttributeValue('sw-release-date', $item));
    }

    // =========================================================================
    // sw-description
    // =========================================================================

    public function testSwDescriptionWithValue(): void
    {
        $productId = $this->ids->create('PROD-DESC');

        (new ProductBuilder($this->ids, 'PROD-DESC'))
            ->name('Description Product')
            ->description('This is a great product with many features.')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-desc');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-DESC');

        $this->assertStringContainsString('great product', $this->getAttributeValue('sw-description', $item) ?? '');
    }

    public function testSwDescriptionEmptyWhenNotSet(): void
    {
        $productId = $this->ids->create('PROD-NO-DESC');

        (new ProductBuilder($this->ids, 'PROD-NO-DESC'))
            ->name('No Description Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-no-desc');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-DESC');

        $this->assertSame('', $this->getAttributeValue('sw-description', $item));
    }

    public function testSwDescriptionInheritedFromParentOnVariant(): void
    {
        $variantId = $this->ids->create('VARIANT-DESC');

        (new ProductBuilder($this->ids, 'PARENT-DESC'))
            ->name('Parent Description')
            ->description('Inherited description text.')
            ->price(10.00)
            ->stock(0)
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'VARIANT-DESC'))
            ->name('Variant Description')
            ->price(10.00)
            ->stock(5)
            ->parent('PARENT-DESC')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/variant-desc');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'VARIANT-DESC');

        $this->assertStringContainsString('Inherited description', $this->getAttributeValue('sw-description', $item) ?? '', 'sw-description must be inherited from parent when variant has none.');
    }

    // =========================================================================
    // sw-keywords
    // =========================================================================

    public function testSwKeywordsWithValues(): void
    {
        $productId = $this->ids->create('PROD-KW');

        (new ProductBuilder($this->ids, 'PROD-KW'))
            ->name('Keywords Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'                  => $productId,
            'customSearchKeywords' => ['keyword1', 'keyword2', 'keyword3'],
        ]], $this->context);

        $this->createSeoUrl($productId, 'product/prod-kw');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-KW');

        $keywords = $this->getAttributeValue('sw-keywords', $item);
        $this->assertStringContainsString('keyword1', $keywords ?? '');
        $this->assertStringContainsString('keyword2', $keywords ?? '');
        $this->assertStringContainsString('keyword3', $keywords ?? '');
    }

    public function testSwKeywordsEmptyWhenNotSet(): void
    {
        $productId = $this->ids->create('PROD-NO-KW');

        (new ProductBuilder($this->ids, 'PROD-NO-KW'))
            ->name('No Keywords Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-no-kw');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-KW');

        $this->assertSame('', $this->getAttributeValue('sw-keywords', $item));
    }

    // =========================================================================
    // sw-delivery-time
    // =========================================================================

    public function testSwDeliveryTimeWithValue(): void
    {
        $productId      = $this->ids->create('PROD-DT');
        $deliveryTimeId = Uuid::randomHex();

        $this->getContainer()->get('delivery_time.repository')->create([[
            'id'        => $deliveryTimeId,
            'name'      => '2-3 days',
            'min'       => 2,
            'max'       => 3,
            'unit'      => 'day',
            'translations' => [['languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => '2-3 days']],
        ]], $this->context);

        (new ProductBuilder($this->ids, 'PROD-DT'))
            ->name('Delivery Time Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'             => $productId,
            'deliveryTimeId' => $deliveryTimeId,
        ]], $this->context);

        $this->createSeoUrl($productId, 'product/prod-dt');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-DT');

        $this->assertSame('2-3 days', $this->getAttributeValue('sw-delivery-time', $item));
    }

    public function testSwDeliveryTimeEmptyWhenNotSet(): void
    {
        $productId = $this->ids->create('PROD-NO-DT');

        (new ProductBuilder($this->ids, 'PROD-NO-DT'))
            ->name('No Delivery Time Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-no-dt');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-DT');

        $this->assertSame('', $this->getAttributeValue('sw-delivery-time', $item));
    }

    public function testSwDeliveryTimeInheritedFromParentOnVariant(): void
    {
        $variantId      = $this->ids->create('VARIANT-DT');
        $parentId       = $this->ids->create('PARENT-DT');
        $deliveryTimeId = Uuid::randomHex();

        $this->getContainer()->get('delivery_time.repository')->create([[
            'id'           => $deliveryTimeId,
            'name'         => '1-2 weeks',
            'min'          => 1,
            'max'          => 2,
            'unit'         => 'week',
            'translations' => [['languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => '1-2 weeks']],
        ]], $this->context);

        (new ProductBuilder($this->ids, 'PARENT-DT'))
            ->name('Parent DT')
            ->price(10.00)
            ->stock(0)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'             => $parentId,
            'deliveryTimeId' => $deliveryTimeId,
        ]], $this->context);

        (new ProductBuilder($this->ids, 'VARIANT-DT'))
            ->name('Variant DT')
            ->price(10.00)
            ->stock(5)
            ->parent('PARENT-DT')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/variant-dt');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'VARIANT-DT');

        $this->assertSame('1-2 weeks', $this->getAttributeValue('sw-delivery-time', $item), 'sw-delivery-time must be inherited from parent.');
    }

    // =========================================================================
    // sw-avg-rating
    // =========================================================================

    public function testSwAvgRatingWithValue(): void
    {
        $productId = $this->ids->create('PROD-RATING');

        (new ProductBuilder($this->ids, 'PROD-RATING'))
            ->name('Rated Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        // ratingAverage is write-protected — set via raw DB update
        $this->getContainer()->get('product.repository')->update([[
            'id'            => $productId,
            'ratingAverage' => 4.5,
        ]], $this->context);

        $this->createSeoUrl($productId, 'product/prod-rating');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-RATING');

        $this->assertSame('4.5', $this->getAttributeValue('sw-avg-rating', $item));
    }

    public function testSwAvgRatingEmptyWhenNotSet(): void
    {
        $productId = $this->ids->create('PROD-NO-RATING');

        (new ProductBuilder($this->ids, 'PROD-NO-RATING'))
            ->name('Unrated Product')
            ->price(10.00)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/prod-no-rating');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-RATING');

        $this->assertSame('', $this->getAttributeValue('sw-avg-rating', $item));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function getAttributeValue(string $attributeName, \SimpleXMLElement $item): ?string
    {
        foreach ($item->attributes->attribute ?? [] as $attr) {
            if ((string) $attr->name === $attributeName) {
                return (string) $attr->value;
            }
        }

        return null;
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

    private function generateFeed(): \SimpleXMLElement
    {
        $domainCriteria = new Criteria();
        $domainCriteria->addFilter(new EqualsFilter('salesChannelId', TestDefaults::SALES_CHANNEL));
        $domainCriteria->setLimit(1);

        $domain = $this->getContainer()
            ->get('sales_channel_domain.repository')
            ->search($domainCriteria, $this->context)
            ->first();

        $this->assertNotNull($domain);

        $feedId = Uuid::randomHex();
        $this->getContainer()->get('s_plugin_rhae_tweakwise_feed.repository')->create([[
            'id'                  => $feedId,
            'name'                => 'Product Info Test Feed',
            'status'              => FeedEntity::STATUS_QUEUED,
            'interval'            => '0 3 * * *',
            'type'                => 'full',
            'limit'               => '500',
            'groupedProducts'     => false,
            'excludeChildren'     => false,
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
        $this->assertNotEmpty($xml);

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
}
