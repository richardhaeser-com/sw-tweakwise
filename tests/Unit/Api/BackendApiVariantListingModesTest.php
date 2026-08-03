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

/**
 * Tests BackendApi::syncProductData() for each Shopware variant listing mode.
 *
 * Shopware determines which product(s) appear in a listing based on
 * variantListingConfig (displayParent, mainVariantId, configuratorGroupConfig).
 * AdminController interprets that config and calls BackendApi with different
 * combinations of ($product, $parent, $groupedProducts). This test file
 * documents and guards every combination.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Shopware listing mode → BackendApi call parameters
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * GROUPED FEED (feed->isGroupedProducts() = true)
 *   Parents are excluded from the feed at query level. Only variants and
 *   standalone products appear. Each variant is synced as:
 *     syncProductData($variant, $frontend, $parentEntity, ..., true)
 *   → GroupCode = parent product number
 *   → Brand falls back to parent if variant has no manufacturer
 *
 * NON-GROUPED, displayParent = true
 *   The parent product itself is the listing item. Children are appended as
 *   otherVariants in the XML feed. AdminController detects displayParent=true
 *   and syncs the parent directly:
 *     syncProductData($parentProduct, $frontend, null, ..., false)
 *   → GroupCode absent (non-grouped)
 *   → All fields from the parent entity itself
 *
 * NON-GROUPED, mainVariantId set
 *   One specific variant is pinned as the listing representative. AdminController
 *   loads that variant and calls:
 *     syncProductData($mainVariant, $frontend, $originalParent, ..., false)
 *   → GroupCode absent (non-grouped), even though parent IS passed
 *   → Brand falls back to parent if mainVariant has no manufacturer
 *
 * NON-GROUPED, default variant listing (no displayParent, no mainVariantId)
 *   Shopware picks a representative variant; siblings are shown in the feed as
 *   otherVariants. AdminController syncs all children via syncVariants=true:
 *     syncProductData($eachVariant, $frontend, $originalParent, ..., false)
 *   → GroupCode absent (non-grouped), even though parent IS passed
 *   → Brand falls back to parent if variant has no manufacturer
 *
 * NON-GROUPED, expand variants (expressionForListings = true on a group)
 *   Every variant is its own independent listing item; no otherVariants block.
 *   The user syncs each variant directly from the admin UI:
 *     syncProductData($variant, $frontend, $parentEntity, ..., false)
 *   → GroupCode absent (non-grouped)
 *   → Brand falls back to parent if variant has no manufacturer
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */
class BackendApiVariantListingModesTest extends TestCase
{
    private RouterInterface $router;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')->willReturn('/product/fallback');
    }

    // =========================================================================
    // Grouped feed mode
    // =========================================================================

    /**
     * In grouped mode every variant is its own Tweakwise product, linked to its
     * siblings via GroupCode = parent product number.
     *
     * BackendApi call: syncProductData($variant, $frontend, $parent, ..., true)
     * Feed equivalent: FeedService renders the variant with isGroupedProducts()=true
     */
    public function testGroupedModeVariantUsesParentNumberAsGroupCode(): void
    {
        $parent = $this->makeProduct('PARENT-G-001', 'Parent Grouped', 0, 'Grouped Brand');
        $variant = $this->makeProduct('VARIANT-G-001', 'Blue Variant', 5, 'Grouped Brand');

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('PARENT-G-001', $posted['GroupCode']);
    }

    /**
     * In grouped mode a variant with no manufacturer must fall back to the parent's
     * manufacturer — both the XML feed and the sync use parent as fallback.
     *
     * Feed: FeedService::renderProducts() pre-assigns $parent->getManufacturer()
     *       to the variant entity when the variant has none (line ~523).
     * Sync: BackendApi uses `?: $parent?->getManufacturer()?->getTranslation('name')`.
     */
    public function testGroupedModeVariantWithoutManufacturerInheritsParentBrand(): void
    {
        $parent = $this->makeProduct('PARENT-G-002', 'Parent Brand Holder', 0, 'Inherited Grouped Brand');
        $variant = $this->makeProduct('VARIANT-G-002', 'No-Brand Variant', 3, null);

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Inherited Grouped Brand', $posted['Brand']);
    }

    /**
     * A variant that HAS its own manufacturer must NOT fall back to the parent's
     * manufacturer, even in grouped mode. Own value always wins.
     */
    public function testGroupedModeVariantWithOwnManufacturerDoesNotUseParentBrand(): void
    {
        $parent = $this->makeProduct('PARENT-G-003', 'Parent', 0, 'Parent Brand');
        $variant = $this->makeProduct('VARIANT-G-003', 'Own Brand Variant', 4, 'Variant Own Brand');

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Variant Own Brand', $posted['Brand']);
        $this->assertNotSame('Parent Brand', $posted['Brand']);
    }

    /**
     * In grouped mode a standalone product (no parent) uses its own product number
     * as GroupCode.
     */
    public function testGroupedModeStandaloneProductUsesOwnNumberAsGroupCode(): void
    {
        $product = $this->makeProduct('STANDALONE-G-001', 'Standalone', 10, 'Brand');

        $posted = $this->sync($product, null, groupedProducts: true);

        $this->assertSame('STANDALONE-G-001', $posted['GroupCode']);
    }

    /**
     * A variant that is out of stock (stock = 0) must send Stock = 0, not the
     * parent's stock, in grouped mode. Guards the ?? vs ?: footgun.
     */
    public function testGroupedModeZeroStockVariantDoesNotFallBackToParentStock(): void
    {
        $parent = $this->makeProduct('PARENT-G-STOCK', 'Parent', 99, 'Brand');
        $variant = $this->makeProduct('VARIANT-G-STOCK', 'Out-of-stock Variant', 0, 'Brand');

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame(0, $posted['Stock']);
    }

    /**
     * All scalar fields for a grouped-mode variant must match the feed output:
     * name (own), price (own), stock (own), brand (own or parent fallback),
     * groupcode (parent number), url (own SEO path).
     */
    public function testGroupedModeVariantAllFieldsMatchFeedOutput(): void
    {
        $parent = $this->makeProduct('PARENT-G-FULL', 'Parent Full', 0, 'Full Parent Brand');
        $variant = $this->makeProductWithCover(
            'VARIANT-G-FULL',
            'Full Grouped Variant',
            7,
            'Full Variant Brand',
            'product/full-grouped-variant',
            29.99,
            'https://cdn.example.com/full-thumb.jpg',
            600
        );

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Full Grouped Variant', $posted['Name']);
        $this->assertSame(29.99, $posted['Price']);
        $this->assertSame(7, $posted['Stock']);
        $this->assertSame('Full Variant Brand', $posted['Brand']);
        $this->assertSame('PARENT-G-FULL', $posted['GroupCode']);
        $this->assertSame('https://example.com/product/full-grouped-variant', $posted['Url']);
        $this->assertSame('https://cdn.example.com/full-thumb.jpg', $posted['Image']);
    }

    // =========================================================================
    // Non-grouped, displayParent = true
    // =========================================================================

    /**
     * When displayParent=true the parent product itself is the listing item.
     * AdminController calls syncProductData($parent, $frontend, null, ..., false).
     *
     * GroupCode must be absent from the payload (non-grouped feed).
     */
    public function testDisplayParentModeGroupCodeAbsentFromPayload(): void
    {
        $parent = $this->makeProduct('PARENT-DP-001', 'Display Parent', 8, 'Display Brand');

        $posted = $this->sync($parent, null, groupedProducts: false);

        $this->assertArrayNotHasKey(
            'GroupCode',
            $posted,
            'Non-grouped feed must not include GroupCode in the sync payload.'
        );
    }

    /**
     * In displayParent mode, all product fields come from the parent entity itself.
     * There is no $parent parameter — the parent IS the product being synced.
     */
    public function testDisplayParentModeAllFieldsFromParentEntity(): void
    {
        $parent = $this->makeProductWithCover(
            'PARENT-DP-FULL',
            'Display Parent Full',
            14,
            'Display Parent Brand',
            'product/display-parent-full',
            79.99,
            'https://cdn.example.com/dp-thumb.jpg',
            800
        );

        $posted = $this->sync($parent, null, groupedProducts: false);

        $this->assertSame('Display Parent Full', $posted['Name']);
        $this->assertSame(79.99, $posted['Price']);
        $this->assertSame(14, $posted['Stock']);
        $this->assertSame('Display Parent Brand', $posted['Brand']);
        $this->assertSame('https://example.com/product/display-parent-full', $posted['Url']);
        $this->assertSame('https://cdn.example.com/dp-thumb.jpg', $posted['Image']);
        $this->assertArrayNotHasKey('GroupCode', $posted);
    }

    /**
     * In displayParent mode the parent product has no $parent parameter passed.
     * Stock must come from the parent entity; there is nothing to fall back to.
     */
    public function testDisplayParentModeStockFromParentEntity(): void
    {
        $parent = $this->makeProduct('PARENT-DP-STOCK', 'Display Parent', 25, 'Brand');

        $posted = $this->sync($parent, null, groupedProducts: false);

        $this->assertSame(25, $posted['Stock']);
    }

    // =========================================================================
    // Non-grouped, mainVariantId set
    // =========================================================================

    /**
     * When mainVariantId is set, AdminController calls:
     *   syncProductData($mainVariant, $frontend, $originalParent, ..., false)
     *
     * The parent IS passed (for manufacturer/stock fallback), but groupedProducts=false
     * means GroupCode must NOT appear in the payload.
     */
    public function testMainVariantModeGroupCodeAbsentEvenWhenParentIsPassed(): void
    {
        $parent = $this->makeProduct('PARENT-MV-001', 'Main Variant Parent', 0, 'MV Parent Brand');
        $mainVariant = $this->makeProduct('VARIANT-MV-001', 'Main Variant', 6, 'MV Parent Brand');

        // groupedProducts = false mirrors the controller line 318:
        //   syncProductData($mainVariant, $frontend, $product, $customFieldNames, false)
        $posted = $this->sync($mainVariant, $parent, groupedProducts: false);

        $this->assertArrayNotHasKey(
            'GroupCode',
            $posted,
            'mainVariant sync path passes groupedProducts=false; GroupCode must be absent.'
        );
    }

    /**
     * When the main variant has no manufacturer, the parent's brand must be used.
     * This applies even in the non-grouped mainVariant path because the parent
     * entity is still passed as the $parent parameter.
     */
    public function testMainVariantModeVariantWithoutManufacturerInheritsParentBrand(): void
    {
        $parent = $this->makeProduct('PARENT-MV-002', 'Main Variant Parent', 0, 'MV Inherited Brand');
        $mainVariant = $this->makeProduct('VARIANT-MV-002', 'Main Variant No Brand', 4, null);

        $posted = $this->sync($mainVariant, $parent, groupedProducts: false);

        $this->assertSame('MV Inherited Brand', $posted['Brand']);
    }

    /**
     * The main variant's own name, price, and stock must be used — not the parent's.
     * Name: no parent fallback (same rule as grouped mode).
     * Price: no parent fallback (same rule as grouped mode).
     * Stock: ?? fallback only for null, not 0 (same rule as grouped mode).
     */
    public function testMainVariantModeUsesVariantOwnNamePriceStock(): void
    {
        $parent = $this->makeProduct('PARENT-MV-003', 'Parent Name Must Not Appear', 99, 'Brand');
        $mainVariant = $this->makeProduct('VARIANT-MV-003', 'Main Variant Own Name', 2, 'Brand', 'product/main-variant-own', 35.00);

        $posted = $this->sync($mainVariant, $parent, groupedProducts: false);

        $this->assertSame('Main Variant Own Name', $posted['Name']);
        $this->assertEquals(35.00, $posted['Price']);
        $this->assertSame(2, $posted['Stock']);
    }

    /**
     * A zero-stock main variant must send Stock=0, not the parent's stock.
     */
    public function testMainVariantModeZeroStockVariantDoesNotFallBackToParentStock(): void
    {
        $parent = $this->makeProduct('PARENT-MV-STOCK', 'Parent', 50, 'Brand');
        $mainVariant = $this->makeProduct('VARIANT-MV-STOCK', 'Zero Stock Main Variant', 0, 'Brand');

        $posted = $this->sync($mainVariant, $parent, groupedProducts: false);

        $this->assertSame(0, $posted['Stock']);
    }

    // =========================================================================
    // Non-grouped, default variant listing (syncVariants path)
    // =========================================================================

    /**
     * When no displayParent and no mainVariantId, AdminController uses syncVariants=true
     * to sync every child. Each call is:
     *   syncProductData($variant, $frontend, $originalParent, ..., false)
     *
     * GroupCode must be absent even though $parent is passed.
     */
    public function testSyncVariantsPathGroupCodeAbsentForEachVariant(): void
    {
        $parent = $this->makeProduct('PARENT-SV-001', 'Sync Variants Parent', 0, 'SV Brand');
        $variantA = $this->makeProduct('VARIANT-SV-001A', 'Variant A', 3, 'SV Brand');
        $variantB = $this->makeProduct('VARIANT-SV-001B', 'Variant B', 5, 'SV Brand');

        $postedA = $this->sync($variantA, $parent, groupedProducts: false);
        $postedB = $this->sync($variantB, $parent, groupedProducts: false);

        $this->assertArrayNotHasKey('GroupCode', $postedA, 'syncVariants path: GroupCode must be absent for variant A.');
        $this->assertArrayNotHasKey('GroupCode', $postedB, 'syncVariants path: GroupCode must be absent for variant B.');
    }

    /**
     * In the syncVariants path each variant must use its own name, price, and stock.
     * Two variants from the same parent must produce different payloads when they
     * have different names/prices/stock values.
     */
    public function testSyncVariantsPathEachVariantProducesIndependentPayload(): void
    {
        $parent = $this->makeProduct('PARENT-SV-002', 'Parent', 0, 'Brand');
        $variantA = $this->makeProduct('VARIANT-SV-002A', 'Red Variant', 10, 'Brand', 'product/red', 49.99);
        $variantB = $this->makeProduct('VARIANT-SV-002B', 'Blue Variant', 2, 'Brand', 'product/blue', 54.99);

        $postedA = $this->sync($variantA, $parent, groupedProducts: false);
        $postedB = $this->sync($variantB, $parent, groupedProducts: false);

        // Names are independent
        $this->assertSame('Red Variant', $postedA['Name']);
        $this->assertSame('Blue Variant', $postedB['Name']);

        // Prices are independent
        $this->assertSame(49.99, $postedA['Price']);
        $this->assertSame(54.99, $postedB['Price']);

        // Stock values are independent
        $this->assertSame(10, $postedA['Stock']);
        $this->assertSame(2, $postedB['Stock']);
    }

    /**
     * In the syncVariants path a variant without a manufacturer must still fall back
     * to the parent's brand. The parent entity is available because the controller
     * passes $originalParent when iterating variants.
     */
    public function testSyncVariantsPathVariantWithoutManufacturerInheritsParentBrand(): void
    {
        $parent = $this->makeProduct('PARENT-SV-003', 'Parent', 0, 'SV Inherited Brand');
        $variant = $this->makeProduct('VARIANT-SV-003', 'No-Brand Variant', 4, null);

        $posted = $this->sync($variant, $parent, groupedProducts: false);

        $this->assertSame('SV Inherited Brand', $posted['Brand']);
    }

    /**
     * A zero-stock variant in the syncVariants path must report Stock=0, not the
     * parent's stock.
     */
    public function testSyncVariantsPathZeroStockVariantDoesNotFallBackToParentStock(): void
    {
        $parent = $this->makeProduct('PARENT-SV-STOCK', 'Parent', 15, 'Brand');
        $variant = $this->makeProduct('VARIANT-SV-STOCK', 'Out-of-stock Variant', 0, 'Brand');

        $posted = $this->sync($variant, $parent, groupedProducts: false);

        $this->assertSame(0, $posted['Stock']);
    }

    // =========================================================================
    // Non-grouped, expand variants (expressionForListings = true)
    // =========================================================================

    /**
     * When a configurator group has expressionForListings=true, each variant
     * appears as an independent listing item in Shopware. The user syncs each
     * variant directly. AdminController loads the parent (for manufacturer
     * fallback) but calls syncProductData with groupedProducts=false.
     *
     * This is functionally identical to the syncVariants path from BackendApi's
     * perspective: ($variant, $frontend, $parent, ..., false).
     */
    public function testExpandVariantsModeGroupCodeAbsentFromPayload(): void
    {
        $parent = $this->makeProduct('PARENT-EV-001', 'Expand Parent', 0, 'EV Brand');
        $variant = $this->makeProduct('VARIANT-EV-001', 'Expanded Variant', 6, 'EV Brand');

        $posted = $this->sync($variant, $parent, groupedProducts: false);

        $this->assertArrayNotHasKey('GroupCode', $posted);
    }

    /**
     * In expand-variants mode each variant is a fully independent Tweakwise product.
     * Its name, price, stock, and image must all come from the variant itself —
     * this is the key feature of expand-variants: each variant is its own item.
     */
    public function testExpandVariantsModeAllFieldsFromVariantEntity(): void
    {
        $parent = $this->makeProduct('PARENT-EV-FULL', 'Parent Name Must Not Appear', 0, 'EV Brand');
        $variant = $this->makeProductWithCover(
            'VARIANT-EV-FULL',
            'Expanded Variant Full',
            9,
            'EV Brand',
            'product/expanded-variant-full',
            65.00,
            'https://cdn.example.com/ev-thumb.jpg',
            500
        );

        $posted = $this->sync($variant, $parent, groupedProducts: false);

        $this->assertSame('Expanded Variant Full', $posted['Name']);
        $this->assertEquals(65.00, $posted['Price']);
        $this->assertSame(9, $posted['Stock']);
        $this->assertSame('https://example.com/product/expanded-variant-full', $posted['Url']);
        $this->assertSame('https://cdn.example.com/ev-thumb.jpg', $posted['Image']);
        $this->assertArrayNotHasKey('GroupCode', $posted);
    }

    /**
     * In expand-variants mode a variant without its own manufacturer still inherits
     * the parent's brand. The parent entity is passed (loaded from DB by AdminController)
     * so the fallback chain works the same as in all other non-grouped paths.
     */
    public function testExpandVariantsModeVariantWithoutManufacturerInheritsParentBrand(): void
    {
        $parent = $this->makeProduct('PARENT-EV-002', 'Parent', 0, 'EV Inherited Brand');
        $variant = $this->makeProduct('VARIANT-EV-002', 'Expanded Variant No Brand', 2, null);

        $posted = $this->sync($variant, $parent, groupedProducts: false);

        $this->assertSame('EV Inherited Brand', $posted['Brand']);
    }

    // =========================================================================
    // Grouped feed × Shopware variant listing mode combinations
    //
    // In grouped mode buildGroupedProductsFilter() excludes parent products.
    // What BackendApi receives is always a variant entity with groupedProducts=true,
    // regardless of the listing mode configured on the parent. These tests guard
    // parity between the feed XML and the sync payload for each combination.
    // =========================================================================

    /**
     * Grouped + displayParent=true:
     * In non-grouped mode the parent itself is the listing item. In grouped mode the
     * parent is excluded by buildGroupedProductsFilter(); variants appear individually.
     * AdminController calls syncProductData($variant, $frontend, $parent, ..., true).
     *
     * Expected: GroupCode = parent product number (same as any grouped variant).
     * Feed parity: product.xml.twig receives groupCode=parent.productNumber.
     */
    public function testGroupedWithDisplayParentVariantUsesParentNumberAsGroupCode(): void
    {
        $parent = $this->makeProduct('PARENT-GDP-001', 'Display Parent (excluded from grouped feed)', 0, 'GDP Brand');
        $variant = $this->makeProduct('VARIANT-GDP-001', 'Grouped DisplayParent Variant', 5, 'GDP Brand');

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('PARENT-GDP-001', $posted['GroupCode']);
    }

    /**
     * Grouped + displayParent=true: all scalar fields come from the variant, not the parent.
     * The parent product number provides GroupCode but nothing else.
     */
    public function testGroupedWithDisplayParentVariantAllFieldsFromVariant(): void
    {
        $parent = $this->makeProduct('PARENT-GDP-FULL', 'Display Parent', 0, 'GDP Parent Brand');
        $variant = $this->makeProductWithCover(
            'VARIANT-GDP-FULL',
            'Grouped DisplayParent Variant Full',
            5,
            'GDP Brand',
            'product/grouped-display-parent-variant',
            28.00,
            'https://cdn.example.com/gdp-thumb.jpg',
            500
        );

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Grouped DisplayParent Variant Full', $posted['Name']);
        $this->assertEquals(28.00, $posted['Price']);
        $this->assertSame(5, $posted['Stock']);
        $this->assertSame('GDP Brand', $posted['Brand']);
        $this->assertSame('PARENT-GDP-FULL', $posted['GroupCode']);
        $this->assertSame('https://cdn.example.com/gdp-thumb.jpg', $posted['Image']);
    }

    /**
     * Grouped + displayParent=true: variant without its own manufacturer inherits
     * the parent brand (same fallback rule as all other grouped paths).
     */
    public function testGroupedWithDisplayParentVariantWithoutManufacturerInheritsParentBrand(): void
    {
        $parent = $this->makeProduct('PARENT-GDP-002', 'Display Parent Brand Holder', 0, 'Inherited GDP Brand');
        $variant = $this->makeProduct('VARIANT-GDP-002', 'No-Brand DisplayParent Variant', 4, null);

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Inherited GDP Brand', $posted['Brand']);
    }

    /**
     * Grouped + mainVariantId set:
     * In non-grouped mode only the pinned mainVariant appears in the listing.
     * In grouped mode all variants appear individually (the parent is excluded).
     * AdminController calls syncProductData($variant, $frontend, $parent, ..., true).
     *
     * Expected: GroupCode = parent product number (same as any grouped variant).
     * Feed parity: product.xml.twig receives groupCode=parent.productNumber.
     */
    public function testGroupedWithMainVariantIdVariantUsesParentNumberAsGroupCode(): void
    {
        $parent = $this->makeProduct('PARENT-GMV-001', 'Main Variant Parent (excluded from grouped feed)', 0, 'GMV Brand');
        $variant = $this->makeProduct('VARIANT-GMV-001', 'Grouped MainVariant Variant', 3, 'GMV Brand');

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('PARENT-GMV-001', $posted['GroupCode']);
    }

    /**
     * Grouped + mainVariantId set: all scalar fields come from the variant.
     */
    public function testGroupedWithMainVariantIdVariantAllFieldsFromVariant(): void
    {
        $parent = $this->makeProduct('PARENT-GMV-FULL', 'Main Variant Parent', 0, 'GMV Parent Brand');
        $variant = $this->makeProductWithCover(
            'VARIANT-GMV-FULL',
            'Grouped MainVariant Variant Full',
            3,
            'GMV Brand',
            'product/grouped-main-variant',
            45.00,
            'https://cdn.example.com/gmv-thumb.jpg',
            600
        );

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Grouped MainVariant Variant Full', $posted['Name']);
        $this->assertEquals(45.00, $posted['Price']);
        $this->assertSame(3, $posted['Stock']);
        $this->assertSame('GMV Brand', $posted['Brand']);
        $this->assertSame('PARENT-GMV-FULL', $posted['GroupCode']);
        $this->assertSame('https://cdn.example.com/gmv-thumb.jpg', $posted['Image']);
    }

    /**
     * Grouped + mainVariantId set: variant without its own manufacturer inherits
     * the parent brand (same fallback rule as all other grouped paths).
     */
    public function testGroupedWithMainVariantIdVariantWithoutManufacturerInheritsParentBrand(): void
    {
        $parent = $this->makeProduct('PARENT-GMV-002', 'Main Variant Parent Brand Holder', 0, 'Inherited GMV Brand');
        $variant = $this->makeProduct('VARIANT-GMV-002', 'No-Brand MainVariant Variant', 2, null);

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Inherited GMV Brand', $posted['Brand']);
    }

    /**
     * Grouped + expand-variants (expressionForListings=true):
     * In non-grouped mode renderProducts() sets $getVariants=false because
     * expressionForListings suppresses the otherVariants block — each variant is its
     * own independent listing item. In grouped mode $feed->isGroupedProducts() also
     * forces $getVariants=false (FeedService line 558-560). Both paths produce the
     * same result: otherVariantsXml='', GroupCode=parent product number.
     *
     * AdminController calls syncProductData($variant, $frontend, $parent, ..., true).
     * Expected: GroupCode = parent product number; all fields from the variant.
     * Feed parity: template receives groupCode=parent.productNumber, otherVariantsXml=''.
     */
    public function testGroupedWithExpandVariantsVariantUsesParentNumberAsGroupCode(): void
    {
        $parent = $this->makeProduct('PARENT-GEV-001', 'Expand Variants Parent (excluded from grouped feed)', 0, 'GEV Brand');
        $variant = $this->makeProduct('VARIANT-GEV-001', 'Grouped ExpandVariants Variant', 8, 'GEV Brand');

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('PARENT-GEV-001', $posted['GroupCode']);
    }

    /**
     * Grouped + expand-variants: all scalar fields come from the variant.
     */
    public function testGroupedWithExpandVariantsVariantAllFieldsFromVariant(): void
    {
        $parent = $this->makeProduct('PARENT-GEV-FULL', 'Expand Variants Parent', 0, 'GEV Parent Brand');
        $variant = $this->makeProductWithCover(
            'VARIANT-GEV-FULL',
            'Grouped ExpandVariants Variant Full',
            8,
            'GEV Brand',
            'product/grouped-expand-variants',
            19.50,
            'https://cdn.example.com/gev-thumb.jpg',
            400
        );

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Grouped ExpandVariants Variant Full', $posted['Name']);
        $this->assertSame(19.50, $posted['Price']);
        $this->assertSame(8, $posted['Stock']);
        $this->assertSame('GEV Brand', $posted['Brand']);
        $this->assertSame('PARENT-GEV-FULL', $posted['GroupCode']);
        $this->assertSame('https://cdn.example.com/gev-thumb.jpg', $posted['Image']);
    }

    /**
     * Grouped + expand-variants: variant without its own manufacturer inherits
     * the parent brand (same fallback rule as all other grouped paths).
     */
    public function testGroupedWithExpandVariantsVariantWithoutManufacturerInheritsParentBrand(): void
    {
        $parent = $this->makeProduct('PARENT-GEV-002', 'Expand Variants Parent Brand Holder', 0, 'Inherited GEV Brand');
        $variant = $this->makeProduct('VARIANT-GEV-002', 'No-Brand ExpandVariants Variant', 6, null);

        $posted = $this->sync($variant, $parent, groupedProducts: true);

        $this->assertSame('Inherited GEV Brand', $posted['Brand']);
    }

    // =========================================================================
    // Cross-mode invariants
    // =========================================================================

    /**
     * Across ALL listing modes, when a variant has no own name the sync must fall
     * back to the parent's name — matching what the feed produces via DAL translation
     * inheritance (COALESCE(variant.name, parent.name) at query time).
     */
    public function testNameFallsBackToParentInAllListingModes(): void
    {
        $parent = $this->makeProduct('PARENT-NAME', 'Parent Name As Fallback', 0, 'Brand');
        $variantWithEmptyName = $this->makeProduct('VARIANT-NAME', '', 1, 'Brand');

        // Grouped mode
        $postedGrouped = $this->sync($variantWithEmptyName, $parent, groupedProducts: true);
        $this->assertSame('Parent Name As Fallback', $postedGrouped['Name'], 'Grouped: name must fall back to parent.');

        // Non-grouped
        $postedNonGrouped = $this->sync($variantWithEmptyName, $parent, groupedProducts: false);
        $this->assertSame('Parent Name As Fallback', $postedNonGrouped['Name'], 'Non-grouped: name must fall back to parent.');
    }

    /**
     * Across ALL listing modes, a variant's price must come from its own
     * calculatedPrice — never from the parent.
     */
    public function testPriceNeverFallsBackToParentInAnyListingMode(): void
    {
        $parent = $this->makeProduct('PARENT-PRICE', 'Parent', 0, 'Brand', 'product/parent-price', 99.99);
        $variant = $this->makeProduct('VARIANT-PRICE', 'Variant', 5, 'Brand', 'product/variant-price', 19.99);

        $postedGrouped = $this->sync($variant, $parent, groupedProducts: true);
        $this->assertSame(19.99, $postedGrouped['Price'], 'Grouped: price must not fall back to parent.');

        $postedNonGrouped = $this->sync($variant, $parent, groupedProducts: false);
        $this->assertSame(19.99, $postedNonGrouped['Price'], 'Non-grouped: price must not fall back to parent.');
    }

    /**
     * Across ALL listing modes, a variant's cover image must come from its own
     * cover — never from the parent. This is documented explicitly by fixture case
     * "grouped products, variant without cover (parent cover not used)".
     */
    public function testImageNeverFallsBackToParentCoverInAnyListingMode(): void
    {
        $parent = $this->makeProductWithCover(
            'PARENT-IMG',
            'Parent',
            0,
            'Brand',
            'product/parent-img',
            10.00,
            'https://cdn.example.com/parent-cover.jpg',
            600
        );
        // Variant has no cover
        $variant = $this->makeProduct('VARIANT-IMG', 'Variant No Cover', 1, 'Brand');

        $postedGrouped = $this->sync($variant, $parent, groupedProducts: true);
        $this->assertArrayNotHasKey('Image', $postedGrouped, 'Grouped: Image key must be absent when variant has no cover.');

        $postedNonGrouped = $this->sync($variant, $parent, groupedProducts: false);
        $this->assertArrayNotHasKey('Image', $postedNonGrouped, 'Non-grouped: Image key must be absent when variant has no cover.');
    }

    /**
     * The visibility attribute is intentionally absent from sync payloads in ALL
     * listing modes. Only the XML feed exports visibility.
     */
    public function testVisibilityAbsentFromPayloadInAllListingModes(): void
    {
        $parent = $this->makeProduct('PARENT-VIS', 'Parent', 0, 'Brand');
        $variant = $this->makeProduct('VARIANT-VIS', 'Variant', 3, 'Brand');
        $standalone = $this->makeProduct('STANDALONE-VIS', 'Standalone', 5, 'Brand');

        foreach ([
            'grouped variant'     => [$variant, $parent, true],
            'non-grouped variant' => [$variant, $parent, false],
            'standalone grouped'  => [$standalone, null, true],
            'displayParent'       => [$parent, null, false],
        ] as $caseName => [$product, $productParent, $grouped]) {
            $posted = $this->sync($product, $productParent, groupedProducts: $grouped);
            $this->assertArrayNotHasKey(
                'visibility',
                $posted,
                "visibility must be absent from sync payload in $caseName mode."
            );
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function sync(
        SalesChannelProductEntity $product,
        ?SalesChannelProductEntity $parent,
        bool $groupedProducts
    ): array {
        $history = [];
        $this->createApi($this->createCapturingClient($history))
            ->syncProductData($product, $this->createFrontend(), $parent, [], $groupedProducts);

        return json_decode((string) $history[0]['request']->getBody(), true);
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

        $api->method('getProductData')->willReturn(['error' => true, 'code' => 404, 'message' => 'Not Found']);
        $api->method('getCategoryData')->willReturn([]);

        return $api;
    }

    /**
     * Creates a minimal product entity. Pass null for $brand to omit the manufacturer
     * (simulates a variant that inherits brand from its parent).
     */
    private function makeProduct(
        string $number,
        string $name,
        int $stock,
        ?string $brand,
        string $seoPath = '',
        float $price = 10.00
    ): SalesChannelProductEntity {
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber($number);
        $product->setTranslated(['name' => $name]);
        $product->setAvailableStock($stock);

        if ($brand !== null) {
            $manufacturer = new ProductManufacturerEntity();
            $manufacturer->setId(Uuid::randomHex());
            $manufacturer->setTranslated(['name' => $brand]);
            $product->setManufacturer($manufacturer);
        }

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setIsCanonical(true);
        $seoUrl->setSeoPathInfo($seoPath ?: ('product/' . strtolower($number)));
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));

        $product->setCalculatedPrice(
            new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $product->assign([
            'calculatedPrices' => new CalculatedPriceCollection(),
            'categories'       => new CategoryCollection(),
            'streams'          => new ProductStreamCollection(),
        ]);

        return $product;
    }

    /**
     * Creates a product with a cover image and one thumbnail.
     */
    private function makeProductWithCover(
        string $number,
        string $name,
        int $stock,
        ?string $brand,
        string $seoPath,
        float $price,
        string $thumbnailUrl,
        int $thumbnailWidth
    ): SalesChannelProductEntity {
        $product = $this->makeProduct($number, $name, $stock, $brand, $seoPath, $price);

        $thumb = new MediaThumbnailEntity();
        $thumb->setId(Uuid::randomHex());
        $thumb->setWidth($thumbnailWidth);
        $thumb->setUrl($thumbnailUrl);

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setUrl('https://cdn.example.com/original.jpg');
        $media->setThumbnails(new MediaThumbnailCollection([$thumb]));

        $cover = new ProductMediaEntity();
        $cover->setId(Uuid::randomHex());
        $cover->setMedia($media);

        $product->setCover($cover);

        return $product;
    }
}
