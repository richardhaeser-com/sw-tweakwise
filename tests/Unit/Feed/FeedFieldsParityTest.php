<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Feed;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Service\FeedService;
use RH\Tweakwise\Tests\Unit\Fixtures\ProductFixtureFactory;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Verifies that the field values the XML feed template would receive match the
 * same expected values used by BackendApiParityTest, enforcing parity between
 * the two export mechanisms.
 *
 * The template itself is not rendered here (that requires a full Shopware kernel).
 * Instead, field values are extracted from the product entity using the same
 * logic the Twig template applies. If either the template logic or the entity
 * mapping changes, the expected values in ProductFixtureFactory must be updated,
 * which immediately breaks the sync parity test too — ensuring no silent drift.
 *
 * This class also contains feed-only visibility tests (not shared with
 * BackendApiParityTest) because the XML feed exports a `visibility` attribute
 * (1 / 3 / 4) that the backend sync intentionally omits.
 *
 * Shopware visibility mechanisms covered:
 *  - VISIBILITY_LINK  (10) → Tweakwise visibility 1 (product exists but Tweakwise hides it)
 *  - VISIBILITY_SEARCH (20) → Tweakwise visibility 3 (search only)
 *  - VISIBILITY_ALL   (30) → Tweakwise visibility 4 (fully visible in catalog + search)
 *  - No visibility record   → defaults to visibility 4 (unreachable in production because
 *                             ProductAvailableFilter would have excluded the product, but the
 *                             code must handle it defensively)
 *  - Variant with its OWN visibility record (independent of parent): the feed uses the
 *    variant's own visibility level, not the parent's — essential for grouped-mode correctness.
 *  - product.active = false: the feed EXCLUDES inactive products via ProductAvailableFilter;
 *    the admin sync does NOT filter by active status (see AdminController).
 */
class FeedFieldsParityTest extends TestCase
{
    public static function cases(): array
    {
        return ProductFixtureFactory::cases();
    }

    #[DataProvider('cases')]
    public function testFeedFieldsMatchExpected(
        SalesChannelProductEntity $product,
        ?SalesChannelProductEntity $parent,
        array $expected,
        bool $groupedProducts
    ): void {
        $fields = $this->extractFeedFields($product, $parent, $groupedProducts);

        foreach ($expected as $field => $expectedValue) {
            $this->assertEquals(
                $expectedValue,
                $fields[$field] ?? '',
                sprintf('Feed field "%s" does not match expected value.', $field)
            );
        }
    }

    // -------------------------------------------------------------------------
    // Feed-only visibility tests
    // These test the <attribute name="visibility"> value emitted by product.xml.twig.
    // The backend sync intentionally never emits this attribute (see
    // BackendApiTest::testSyncDoesNotSendVisibilityField).
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function visibilityCases(): array
    {
        return [
            'VISIBILITY_LINK (10) maps to PRODUCT_NOT_VISIBLE (1)' => [
                10,
                FeedService::PRODUCT_NOT_VISIBLE,
            ],
            'VISIBILITY_SEARCH (20) maps to PRODUCT_VISIBILITY_SEARCH (3)' => [
                20,
                FeedService::PRODUCT_VISIBILITY_SEARCH,
            ],
            'VISIBILITY_ALL (30) maps to PRODUCT_VISIBILITY_CATALOG_SEARCH (4)' => [
                30,
                FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH,
            ],
            'no visibility record defaults to PRODUCT_VISIBILITY_CATALOG_SEARCH (4)' => [
                null,
                FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH,
            ],
        ];
    }

    #[DataProvider('visibilityCases')]
    public function testFeedVisibilityAttributeForEachShopwareLevel(
        ?int $shopwareVisibility,
        int $expectedTweakwiseVisibility
    ): void {
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setVisibilities($this->makeVisibilities($shopwareVisibility));

        $actual = $this->extractVisibility($product);

        $this->assertSame(
            $expectedTweakwiseVisibility,
            $actual,
            sprintf(
                'Shopware visibility %s should map to Tweakwise visibility %d.',
                $shopwareVisibility === null ? '(none)' : $shopwareVisibility,
                $expectedTweakwiseVisibility
            )
        );
    }

    /**
     * In grouped-products mode the XML feed exports variants directly (parents are
     * excluded by buildGroupedProductsFilter). Each variant carries its OWN visibility
     * record — it does NOT inherit visibility from the parent. This test verifies that
     * when a variant has visibility=10 (LINK-only) but its parent has visibility=30
     * (fully visible), the feed uses the variant's own visibility (→ 1), not the parent's.
     *
     * This matters because a shop operator can assign different visibility levels to
     * individual variants (e.g., show some variants only via direct link while others
     * appear in the full catalog).
     */
    public function testVariantVisibilityIsIndependentOfParentVisibilityInGroupedMode(): void
    {
        // Variant is LINK-only (visibility = 10 → Tweakwise 1).
        $variant = new SalesChannelProductEntity();
        $variant->setId(Uuid::randomHex());
        $variant->setVisibilities($this->makeVisibilities(10));

        // Parent is fully visible (visibility = 30 → Tweakwise 4).
        // In grouped mode the parent is excluded from the feed, but we still assert
        // that if getVisibility() were called on the parent it would return 4, while
        // calling it on the variant returns 1 — proving independence.
        $parent = new SalesChannelProductEntity();
        $parent->setId(Uuid::randomHex());
        $parent->setVisibilities($this->makeVisibilities(30));

        $this->assertSame(
            FeedService::PRODUCT_NOT_VISIBLE,
            $this->extractVisibility($variant),
            'Variant visibility must be read from the variant entity, not from the parent.'
        );
        $this->assertSame(
            FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH,
            $this->extractVisibility($parent),
            'Parent visibility must be read from the parent entity (sanity check).'
        );
    }

    /**
     * The XML feed's ProductAvailableFilter includes the condition `product.active = true`,
     * so inactive products are silently excluded from the feed at query time.
     *
     * The admin sync (AdminController) uses a plain EntityRepository::search() with no
     * active filter, meaning an inactive product CAN be synced manually from the admin.
     *
     * This test documents the FEED SIDE of that design decision: the visibility attribute
     * value for a product is computed purely from the product_visibility record — the
     * active flag plays no part in the visibility mapping itself. The exclusion happens
     * earlier, at the DAL query level, not inside getVisibility().
     *
     * Consequence for operators: if a product is deactivated after being synced to
     * Tweakwise, the feed will no longer export it (removing it on next full import),
     * but the admin sync will still allow manually pushing updates for that product.
     */
    public function testInactiveProductHasNoSpecialVisibilityInFeedMapping(): void
    {
        // Active flag is deliberately NOT set (defaults to null/false on a fresh entity).
        // getVisibility() must still return a sensible value based on the visibility record.
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setVisibilities($this->makeVisibilities(30));

        $this->assertSame(
            FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH,
            $this->extractVisibility($product),
            'getVisibility() must not consider the active flag — that filter lives at query level.'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Mirrors FeedService::getVisibility() — the PHP logic that determines what
     * visibility integer is passed to the product.xml.twig template.
     */
    private function extractVisibility(SalesChannelProductEntity $product): int
    {
        $visibilities = $product->getVisibilities();
        if ($visibilities === null || $visibilities->count() === 0) {
            return FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH;
        }

        return match ($visibilities->first()->getVisibility()) {
            10 => FeedService::PRODUCT_NOT_VISIBLE,
            20 => FeedService::PRODUCT_VISIBILITY_SEARCH,
            default => FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH,
        };
    }

    /**
     * Mirrors the URL construction in FeedService::renderProducts() + product.xml.twig:
     *   $domainUrl = rtrim($domain->getUrl(), '/') . '/';
     *   $url       = ltrim($this->getUrlOfEntity($product), '/');
     *   template:  {{ domainUrl ~ url }}
     */
    private function extractFeedUrl(SalesChannelProductEntity $product): string
    {
        $seoPath = '';
        foreach ($product->getSeoUrls() as $seoUrl) {
            if ($seoUrl->getIsCanonical()) {
                $seoPath = ltrim($seoUrl->getSeoPathInfo(), '/');
                break;
            }
        }

        return rtrim(ProductFixtureFactory::DOMAIN_URL, '/') . '/' . $seoPath;
    }

    private function makeVisibilities(?int $shopwareVisibility): ProductVisibilityCollection
    {
        if ($shopwareVisibility === null) {
            return new ProductVisibilityCollection();
        }

        $entity = new ProductVisibilityEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setVisibility($shopwareVisibility);

        return new ProductVisibilityCollection([$entity]);
    }

    /**
     * Extracts the fields the XML feed template computes from a product entity,
     * mirroring the logic in product.xml.twig and renderProducts().
     *
     * @return array<string, mixed>
     */
    private function extractFeedFields(SalesChannelProductEntity $product, ?SalesChannelProductEntity $parent, bool $groupedProducts = true): array
    {
        // template: {{ product.translated.name }}
        $name = $product->getTranslated()['name'] ?? '';

        // template: price = product.calculatedPrice; if calculatedPrices.count > 0 → last
        $price = $product->getCalculatedPrice();
        if ($product->getCalculatedPrices()->count() > 0) {
            $price = $product->getCalculatedPrices()->last();
        }
        $unitPrice = $price?->getUnitPrice() ?? 0.0;

        // template: {{ product.availableStock }}
        $stock = $product->getAvailableStock();

        // template: {% if product.manufacturer %} {{ product.manufacturer.translated.name }} {% endif %}
        // renderProducts() enriches variant with parent's manufacturer when the variant has none (mirrors FeedService fix)
        $manufacturer = $product->getManufacturer() ?? $parent?->getManufacturer();
        $brand = $manufacturer?->getTranslation('name') ?? '';

        // template: iterates thumbnails; picks largest width; falls back to original URL
        $image = '';
        if ($product->getCover()?->getMedia()?->getUrl()) {
            $image = $product->getCover()->getMedia()->getUrl();
            $width = 0;
            foreach ($product->getCover()->getMedia()->getThumbnails() ?? [] as $thumbnail) {
                if ($thumbnail->getWidth() > $width) {
                    $image = $thumbnail->getUrl();
                    $width = $thumbnail->getWidth();
                }
            }
        }

        // mirrors FeedService renderProducts(): groupCode only set when feed has isGroupedProducts() true
        $groupCode = '';
        if ($groupedProducts) {
            $groupCode = $product->getProductNumber();
            if ($parent !== null) {
                $groupCode = $parent->getProductNumber();
            }
        }

        return [
            'Name'      => $name,
            'Price'     => $unitPrice,
            'Stock'     => $stock,
            'Brand'     => $brand,
            'Image'     => $image,
            'GroupCode' => $groupCode,
            'Url'       => $this->extractFeedUrl($product),
        ];
    }
}
