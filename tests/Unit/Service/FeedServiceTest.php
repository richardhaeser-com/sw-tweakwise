<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FeedServiceTest extends TestCase
{
    private FeedService $feedService;

    protected function setUp(): void
    {
        // Disable the constructor — these tests only exercise protected helper methods
        // that have no dependency on injected services.
        $this->feedService = $this->getMockBuilder(FeedService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    private function invokeProtected(string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod(FeedService::class, $method);
        return $reflection->invokeArgs($this->feedService, $args);
    }

    // -------------------------------------------------------------------------
    // getVisibility()
    // -------------------------------------------------------------------------

    public function testVisibilityReturnsNotVisibleForValue10(): void
    {
        $product = $this->productWithVisibility(10);
        $result = $this->invokeProtected('getVisibility', [$product]);
        $this->assertEquals(FeedService::PRODUCT_NOT_VISIBLE, $result);
    }

    public function testVisibilityReturnsSearchOnlyForValue20(): void
    {
        $product = $this->productWithVisibility(20);
        $result = $this->invokeProtected('getVisibility', [$product]);
        $this->assertEquals(FeedService::PRODUCT_VISIBILITY_SEARCH, $result);
    }

    public function testVisibilityReturnsCatalogSearchForValue30(): void
    {
        $product = $this->productWithVisibility(30);
        $result = $this->invokeProtected('getVisibility', [$product]);
        $this->assertEquals(FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH, $result);
    }

    public function testVisibilityReturnsCatalogSearchWhenNoVisibilitiesSet(): void
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setVisibilities(new ProductVisibilityCollection());

        $result = $this->invokeProtected('getVisibility', [$product]);
        $this->assertEquals(FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH, $result);
    }

    // -------------------------------------------------------------------------
    // getLowestAndHighestPrice()
    // -------------------------------------------------------------------------

    public function testLowestAndHighestPriceReturnsZerosForSinglePrice(): void
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setPrices(new ProductPriceCollection([$this->createPriceEntity('rule-1', 10.0, 12.0)]));

        $context = $this->mockContext(['rule-1']);
        $result = $this->invokeProtected('getLowestAndHighestPrice', [$product, $context]);

        $this->assertEquals(0, $result['lowest']['price_net']);
        $this->assertEquals(0, $result['highest']['price_net']);
    }

    public function testLowestAndHighestPriceWithMultipleMatchingRules(): void
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setPrices(new ProductPriceCollection([
            $this->createPriceEntity('rule-1', 10.0, 12.0),
            $this->createPriceEntity('rule-2', 20.0, 24.0),
        ]));

        $context = $this->mockContext(['rule-1', 'rule-2']);
        $result = $this->invokeProtected('getLowestAndHighestPrice', [$product, $context]);

        $this->assertEquals(10.0, $result['lowest']['price_net']);
        $this->assertEquals(12.0, $result['lowest']['price_gross']);
        $this->assertEquals(20.0, $result['highest']['price_net']);
        $this->assertEquals(24.0, $result['highest']['price_gross']);
    }

    public function testLowestAndHighestPriceIgnoresPricesWithoutMatchingRule(): void
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setPrices(new ProductPriceCollection([
            $this->createPriceEntity('rule-1', 10.0, 12.0),
            $this->createPriceEntity('rule-unrelated', 5.0, 6.0),
        ]));

        // Only rule-1 is active in context; rule-unrelated is skipped.
        // Total price count is 2 so the early-return is not triggered, but only
        // the rule-1 price qualifies — it becomes both lowest and highest.
        $context = $this->mockContext(['rule-1']);
        $result = $this->invokeProtected('getLowestAndHighestPrice', [$product, $context]);

        $this->assertEquals(10.0, $result['lowest']['price_net']);
        $this->assertEquals(10.0, $result['highest']['price_net']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function productWithVisibility(int $visibility): ProductEntity
    {
        $visibilityEntity = new ProductVisibilityEntity();
        $visibilityEntity->setId(Uuid::randomHex());
        $visibilityEntity->setVisibility($visibility);

        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setVisibilities(new ProductVisibilityCollection([$visibilityEntity]));

        return $product;
    }

    private function createPriceEntity(string $ruleId, float $net, float $gross): ProductPriceEntity
    {
        $dalPrice = new Price(Defaults::CURRENCY, $net, $gross, false);
        $entity = new ProductPriceEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setRuleId($ruleId);
        $entity->setPrice(new PriceCollection([$dalPrice]));
        $entity->setQuantityStart(1);

        return $entity;
    }

    private function mockContext(array $ruleIds): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getRuleIds')->willReturn($ruleIds);
        return $context;
    }
}
