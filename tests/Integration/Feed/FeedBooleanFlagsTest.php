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
 * Integration tests verifying that the XML feed correctly emits boolean flag
 * attributes (sw-new, sw-free-shipping, sw-has-discount, sw-is-topseller,
 * sw-is-closeout, sw-label) for products with the corresponding flags set.
 *
 * These tests generate real XML via FeedService::generateFeed() and assert
 * the rendered attribute values. They are expected to pass since the feed
 * template already emits these attributes.
 *
 * The corresponding backend sync tests (BackendApiBooleanFlagsTest) are
 * expected to FAIL until the sync implements these attributes.
 */
class FeedBooleanFlagsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;
    private Context $context;

    protected function setUp(): void
    {
        $this->ids    = new IdsCollection();
        $this->context = Context::createDefaultContext();
    }

    public function testSwFreeShippingTrueWhenProductHasFreeShipping(): void
    {
        $productId = $this->ids->create('PROD-FREE-SHIP');

        (new ProductBuilder($this->ids, 'PROD-FREE-SHIP'))
            ->name('Free Shipping Product')
            ->price(19.99)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'           => $productId,
            'shippingFree' => true,
        ]], $this->context);

        $this->createSeoUrl($productId, 'product/free-shipping');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-FREE-SHIP');

        $this->assertAttributeValue('true', 'sw-free-shipping', $item);
    }

    public function testSwFreeShippingFalseWhenProductHasNoFreeShipping(): void
    {
        $productId = $this->ids->create('PROD-NO-FREE-SHIP');

        (new ProductBuilder($this->ids, 'PROD-NO-FREE-SHIP'))
            ->name('No Free Shipping Product')
            ->price(19.99)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/no-free-shipping');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-FREE-SHIP');

        $this->assertAttributeValue('false', 'sw-free-shipping', $item);
    }

    public function testSwIsTopseller(): void
    {
        $productId = $this->ids->create('PROD-TOPSELLER');

        (new ProductBuilder($this->ids, 'PROD-TOPSELLER'))
            ->name('Topseller Product')
            ->price(29.99)
            ->stock(10)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'              => $productId,
            'markAsTopseller' => true,
        ]], $this->context);

        $this->createSeoUrl($productId, 'product/topseller');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-TOPSELLER');

        $this->assertAttributeValue('true', 'sw-is-topseller', $item);
    }

    public function testSwIsTopselerFalseWhenNotTopseller(): void
    {
        $productId = $this->ids->create('PROD-NOT-TOPSELLER');

        (new ProductBuilder($this->ids, 'PROD-NOT-TOPSELLER'))
            ->name('Not Topseller Product')
            ->price(29.99)
            ->stock(10)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/not-topseller');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NOT-TOPSELLER');

        $this->assertAttributeValue('false', 'sw-is-topseller', $item);
    }

    public function testSwIsCloseoutTrueWhenProductIsCloseout(): void
    {
        $productId = $this->ids->create('PROD-CLOSEOUT');

        (new ProductBuilder($this->ids, 'PROD-CLOSEOUT'))
            ->name('Closeout Product')
            ->price(9.99)
            ->stock(2)
            ->closeout(true)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/closeout');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-CLOSEOUT');

        $this->assertAttributeValue('true', 'sw-is-closeout', $item);
    }

    public function testSwIsCloseoutFalseWhenProductIsNotCloseout(): void
    {
        $productId = $this->ids->create('PROD-NOT-CLOSEOUT');

        (new ProductBuilder($this->ids, 'PROD-NOT-CLOSEOUT'))
            ->name('Not Closeout Product')
            ->price(9.99)
            ->stock(2)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/not-closeout');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NOT-CLOSEOUT');

        $this->assertAttributeValue('false', 'sw-is-closeout', $item);
    }

    public function testSwHasDiscountTrueWhenProductHasListPrice(): void
    {
        $productId = $this->ids->create('PROD-DISCOUNT');

        // price(gross, net, currency, listPriceGross, listPriceNet)
        (new ProductBuilder($this->ids, 'PROD-DISCOUNT'))
            ->name('Discounted Product')
            ->price(15.00, null, 'default', 25.00)
            ->stock(3)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/discount');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-DISCOUNT');

        $this->assertAttributeValue('true', 'sw-has-discount', $item);
    }

    public function testSwHasDiscountFalseWhenProductHasNoListPrice(): void
    {
        $productId = $this->ids->create('PROD-NO-DISCOUNT');

        (new ProductBuilder($this->ids, 'PROD-NO-DISCOUNT'))
            ->name('Full Price Product')
            ->price(25.00)
            ->stock(3)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/no-discount');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NO-DISCOUNT');

        $this->assertAttributeValue('false', 'sw-has-discount', $item);
    }

    public function testSwLabelIsSoldoutWhenOutOfStock(): void
    {
        $productId = $this->ids->create('PROD-SOLDOUT');

        (new ProductBuilder($this->ids, 'PROD-SOLDOUT'))
            ->name('Sold Out Product')
            ->price(19.99)
            ->stock(0)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/soldout');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-SOLDOUT');

        // sw-label is a translated string — assert it is not empty when out of stock
        $label = $this->getAttributeValue('sw-label', $item);
        $this->assertNotEmpty($label, 'sw-label must not be empty for an out-of-stock product.');
    }

    public function testSwLabelIsEmptyWhenProductIsInStockWithNoSpecialFlags(): void
    {
        $productId = $this->ids->create('PROD-NORMAL');

        (new ProductBuilder($this->ids, 'PROD-NORMAL'))
            ->name('Normal Product')
            ->price(19.99)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($productId, 'product/normal');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-NORMAL');

        $this->assertAttributeValue('', 'sw-label', $item);
    }

    public function testSwLabelIsTopseller(): void
    {
        $productId = $this->ids->create('PROD-LABEL-TOPSELLER');

        (new ProductBuilder($this->ids, 'PROD-LABEL-TOPSELLER'))
            ->name('Topseller Label Product')
            ->price(19.99)
            ->stock(5)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->getContainer()->get('product.repository')->update([[
            'id'              => $productId,
            'markAsTopseller' => true,
        ]], $this->context);

        $this->createSeoUrl($productId, 'product/label-topseller');

        $xml  = $this->generateFeed();
        $item = $this->findItem($xml, 'PROD-LABEL-TOPSELLER');

        $label = $this->getAttributeValue('sw-label', $item);
        $this->assertNotEmpty($label, 'sw-label must not be empty for a topseller product.');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function assertAttributeValue(string $expected, string $attributeName, \SimpleXMLElement $item): void
    {
        $actual = $this->getAttributeValue($attributeName, $item);
        $this->assertSame(
            $expected,
            $actual,
            sprintf('Feed attribute "%s" must be "%s".', $attributeName, $expected)
        );
    }

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
        // Some Shopware core versions auto-generate a canonical SEO URL when
        // the product is written; reuse its ID (if any) instead of always
        // inserting, to avoid a uniq.seo_url.foreign_key collision.
        $existing = $this->getContainer()->get('seo_url.repository')->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('foreignKey', $productId))
                ->addFilter(new EqualsFilter('salesChannelId', TestDefaults::SALES_CHANNEL))
                ->addFilter(new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM))
                ->addFilter(new EqualsFilter('isCanonical', true)),
            $this->context
        )->first();

        $this->getContainer()->get('seo_url.repository')->upsert([[
            'id'             => $existing?->getId() ?? Uuid::randomHex(),
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

        $this->assertNotNull($domain, 'Default sales channel must have at least one domain.');

        $feedId = Uuid::randomHex();
        $this->getContainer()->get('s_plugin_rhae_tweakwise_feed.repository')->create([[
            'id'                  => $feedId,
            'name'                => 'Boolean Flags Test Feed',
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
}
