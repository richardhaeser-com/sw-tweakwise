<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Integration\Feed;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Service\FeedService;
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
 * These tests boot the full Shopware kernel, write real product records to the
 * database with the correct variantListingConfig for each listing mode, run
 * FeedService::generateFeed(), and parse the resulting XML file.
 *
 * Each Shopware variant listing mode is tested in both grouped and non-grouped
 * feed configurations so that the XML output is verified against the correct
 * database state — not simulated through a generic fallback.
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

    // =========================================================================
    // Standalone products
    // =========================================================================

    /**
     * A standalone product (no parent, no children) must appear in the feed with
     * its own product number as GroupCode in grouped mode, and no GroupCode in
     * non-grouped mode.
     */
    public function testStandaloneProductGroupedMode(): void
    {
        $this->createStandaloneProduct('PROD-SA-001', 'Standalone Grouped', 10, 'Brand SA', 19.99, 'product/standalone-grouped');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'PROD-SA-001');

        $this->assertSame('Standalone Grouped', (string) $item->name);
        $this->assertSame('19.99', (string) $item->price);
        $this->assertSame('10', (string) $item->stock);
        $this->assertSame('Brand SA', (string) $item->brand);
        $this->assertStringContainsString('product/standalone-grouped', (string) $item->url);
        $this->assertSame('PROD-SA-001', (string) $item->groupcode, '<groupcode> must equal own product number for standalone in grouped mode.');
        $this->assertSame(FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH, $this->extractVisibility($item));
    }

    public function testStandaloneProductNonGroupedMode(): void
    {
        $this->createStandaloneProduct('PROD-SA-002', 'Standalone Non-Grouped', 5, 'Brand SB', 29.99, 'product/standalone-non-grouped');

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'PROD-SA-002');

        $this->assertSame('Standalone Non-Grouped', (string) $item->name);
        $this->assertSame('29.99', (string) $item->price);
        $this->assertSame('5', (string) $item->stock);
        $this->assertSame('Brand SB', (string) $item->brand);
        $this->assertStringContainsString('product/standalone-non-grouped', (string) $item->url);
        $this->assertEmpty((string) $item->groupcode, '<groupcode> must be absent for standalone in non-grouped mode.');
    }

    // =========================================================================
    // Non-grouped: displayParent = true
    //
    // variantListingConfig.displayParent = true → Shopware's listing loader
    // returns the parent entity itself as the listing item. The feed renders the
    // parent product; children appear only in the <otherVariants> block.
    //
    // Feed setup: parent gets visibility; variant gets no separate visibility.
    // listingLoader remaps the representative variant ID back to the parent ID.
    // =========================================================================

    /**
     * Non-grouped, displayParent=true: the parent product itself appears in the
     * feed. <groupcode> must be absent; all fields come from the parent entity.
     */
    public function testNonGroupedDisplayParentShowsParentInFeed(): void
    {
        $parentId = $this->ids->create('parent-dp');
        $variantId = $this->ids->create('variant-dp');

        // Parent: displayParent=true, visible in the sales channel.
        (new ProductBuilder($this->ids, 'parent-dp'))
            ->name('Display Parent Product')
            ->price(55.00)
            ->stock(12)
            ->manufacturer('Display Parent Brand')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->variantListingConfig(['displayParent' => true])
            ->write($this->getContainer());

        // Variant: child of the parent, no separate visibility record needed
        // (the listing loader will map it to the parent).
        (new ProductBuilder($this->ids, 'variant-dp'))
            ->name('Variant Of Display Parent')
            ->price(55.00)
            ->stock(3)
            ->parent('parent-dp')
            ->write($this->getContainer());

        $this->createSeoUrl($parentId, 'product/display-parent');

        // Run VariantListingUpdater synchronously so displayGroup is set on the variant.
        // Without this, listingLoader excludes the product (displayGroup IS NOT NULL filter).
        $this->updateVariantListing([$parentId]);

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'parent-dp');

        $this->assertSame('Display Parent Product', (string) $item->name);
        $this->assertSame('55', (string) $item->price);
        $this->assertSame('12', (string) $item->stock);
        $this->assertSame('Display Parent Brand', (string) $item->brand);
        $this->assertStringContainsString('product/display-parent', (string) $item->url);
        $this->assertEmpty((string) $item->groupcode, '<groupcode> must be absent in non-grouped displayParent mode.');
        $this->assertSame(FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH, $this->extractVisibility($item));
    }

    // =========================================================================
    // Non-grouped: mainVariantId set
    //
    // variantListingConfig.mainVariantId = <variantId> → listingLoader always
    // returns that specific variant as the representative of the family.
    //
    // Feed setup: the variant gets visibility; the parent does not (it would be
    // excluded by productAvailableFilter if it had no visibility anyway).
    // =========================================================================

    /**
     * Non-grouped, mainVariantId: the pinned variant appears in the feed.
     * <groupcode> must be absent; fields come from the variant; brand falls
     * back to parent when variant has no manufacturer.
     */
    public function testNonGroupedMainVariantIdShowsPinnedVariantInFeed(): void
    {
        $variantId = $this->ids->create('variant-mv');

        (new ProductBuilder($this->ids, 'parent-mv'))
            ->name('Main Variant Parent')
            ->price(42.00)
            ->stock(0)
            ->manufacturer('Main Variant Parent Brand')
            ->variantListingConfig(['mainVariantId' => $variantId])
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-mv'))
            ->name('Main Variant')
            ->price(42.00)
            ->stock(8)
            ->parent('parent-mv')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/main-variant');

        // Run VariantListingUpdater synchronously so displayGroup is set on the variant.
        $this->updateVariantListing([$this->ids->get('parent-mv')]);

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'variant-mv');

        $this->assertSame('Main Variant', (string) $item->name);
        $this->assertSame('42', (string) $item->price);
        $this->assertSame('8', (string) $item->stock);
        // Variant has no manufacturer — must inherit from parent.
        $this->assertSame('Main Variant Parent Brand', (string) $item->brand);
        $this->assertStringContainsString('product/main-variant', (string) $item->url);
        $this->assertEmpty((string) $item->groupcode, '<groupcode> must be absent in non-grouped mainVariantId mode.');
        $this->assertSame(FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH, $this->extractVisibility($item));
    }

    // =========================================================================
    // Non-grouped: default variant listing
    //   (displayParent=false, no mainVariantId, no expressionForListings)
    //
    // Shopware selects a representative variant via displayGroup grouping.
    // The representative is the variant with the lowest sort position in its
    // display group. We set mainVariantId in the integration test to make this
    // deterministic — the same mechanism used by the admin when it "pins" a variant.
    // =========================================================================

    /**
     * Non-grouped, default variant listing: the representative variant appears
     * in the feed. <groupcode> absent; brand inherits from parent when variant
     * has no manufacturer.
     */
    public function testNonGroupedDefaultVariantListingShowsRepresentativeVariant(): void
    {
        $variantId = $this->ids->create('variant-dv');

        (new ProductBuilder($this->ids, 'parent-dv'))
            ->name('Default Variant Parent')
            ->price(24.99)
            ->stock(0)
            ->manufacturer('Default Variant Brand')
            ->variantListingConfig(['mainVariantId' => $variantId])
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-dv'))
            ->name('Default Variant')
            ->price(24.99)
            ->stock(3)
            ->parent('parent-dv')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/default-variant');

        // Run VariantListingUpdater synchronously so displayGroup is set on the variant.
        $this->updateVariantListing([$this->ids->get('parent-dv')]);

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'variant-dv');

        $this->assertSame('Default Variant', (string) $item->name);
        $this->assertSame('24.99', (string) $item->price);
        $this->assertSame('3', (string) $item->stock);
        $this->assertSame('Default Variant Brand', (string) $item->brand);
        $this->assertStringContainsString('product/default-variant', (string) $item->url);
        $this->assertEmpty((string) $item->groupcode);
    }

    // =========================================================================
    // Non-grouped: expand-variants (expressionForListings = true)
    //
    // When a configuratorGroup has expressionForListings=true, Shopware treats
    // each variant as its own independent listing item (no displayGroup grouping).
    // FeedService::renderProducts() detects this and sets $getVariants=false,
    // suppressing the <otherVariants> block.
    //
    // Each variant gets its own visibility record and its own SEO URL.
    // =========================================================================

    /**
     * Non-grouped, expand-variants: each variant appears independently in the feed.
     * <groupcode> absent; <otherVariants> block suppressed (no sibling attributes
     * belonging to other variants appear on each item).
     */
    public function testNonGroupedExpandVariantsEachVariantAppearsIndependently(): void
    {
        $groupId = $this->ids->create('color-group');
        $variantAId = $this->ids->create('variant-ev-a');
        $variantBId = $this->ids->create('variant-ev-b');

        // Parent: configuratorGroupConfig marks the color group as expressionForListings.
        // configuratorSetting registers the group+option on the parent so Shopware
        // knows about the configurator structure.
        (new ProductBuilder($this->ids, 'parent-ev'))
            ->name('Expand Variants Parent')
            ->price(19.50)
            ->stock(0)
            ->manufacturer('GEV Brand')
            ->configuratorSetting('red', 'color-group')
            ->configuratorSetting('blue', 'color-group')
            ->variantListingConfig([
                'configuratorGroupConfig' => [[
                    'id'                   => $groupId,
                    'representation'       => 'box',
                    'expressionForListings' => true,
                ]],
            ])
            ->write($this->getContainer());

        // Variant A — assign option 'red' so display_group is computed per-option.
        (new ProductBuilder($this->ids, 'variant-ev-a'))
            ->name('Expand Variant Red')
            ->price(19.50)
            ->stock(8)
            ->parent('parent-ev')
            ->option('red', 'color-group')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        // Variant B — assign option 'blue'.
        (new ProductBuilder($this->ids, 'variant-ev-b'))
            ->name('Expand Variant Blue')
            ->price(22.00)
            ->stock(4)
            ->parent('parent-ev')
            ->option('blue', 'color-group')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantAId, 'product/expand-variant-red');
        $this->createSeoUrl($variantBId, 'product/expand-variant-blue');

        // Run VariantListingUpdater synchronously so displayGroup is set on the variants.
        $this->updateVariantListing([$this->ids->get('parent-ev')]);

        $xml = $this->generateFeed(grouped: false);

        // Both variants must appear as independent items.
        $itemA = $this->findItem($xml, 'variant-ev-a');
        $itemB = $this->findItem($xml, 'variant-ev-b');

        $this->assertSame('Expand Variant Red', (string) $itemA->name);
        $this->assertSame('19.5', (string) $itemA->price);
        $this->assertSame('8', (string) $itemA->stock);
        $this->assertSame('GEV Brand', (string) $itemA->brand);
        $this->assertStringContainsString('product/expand-variant-red', (string) $itemA->url);
        $this->assertEmpty((string) $itemA->groupcode, '<groupcode> must be absent in non-grouped expand-variants mode.');

        $this->assertSame('Expand Variant Blue', (string) $itemB->name);
        $this->assertSame('22', (string) $itemB->price);
        $this->assertSame('4', (string) $itemB->stock);
        $this->assertSame('GEV Brand', (string) $itemB->brand);
        $this->assertStringContainsString('product/expand-variant-blue', (string) $itemB->url);
        $this->assertEmpty((string) $itemB->groupcode, '<groupcode> must be absent in non-grouped expand-variants mode.');
    }

    // =========================================================================
    // Grouped: all listing modes
    //
    // In grouped mode, buildGroupedProductsFilter() excludes parent products.
    // Only variants (parentId IS NOT NULL) and childless standalones appear.
    // The listing mode configured on the parent (displayParent, mainVariantId,
    // expressionForListings) does NOT affect which products appear — it only
    // affects non-grouped mode. In grouped mode, every variant appears with
    // GroupCode = parent product number.
    //
    // These tests confirm that regardless of the variantListingConfig on the
    // parent, variants in grouped mode always produce the correct GroupCode and
    // field values in the rendered XML.
    // =========================================================================

    /**
     * Grouped, displayParent=true: the parent is excluded by the filter; the
     * variant appears with GroupCode = parent product number.
     */
    public function testGroupedDisplayParentVariantAppearsWithGroupCode(): void
    {
        $variantId = $this->ids->create('variant-gdp');

        (new ProductBuilder($this->ids, 'parent-gdp'))
            ->name('Display Parent (excluded from grouped feed)')
            ->price(28.00)
            ->stock(0)
            ->manufacturer('GDP Brand')
            ->variantListingConfig(['displayParent' => true])
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-gdp'))
            ->name('Grouped DisplayParent Variant')
            ->price(28.00)
            ->stock(5)
            ->parent('parent-gdp')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/grouped-display-parent-variant');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-gdp');

        $this->assertSame('Grouped DisplayParent Variant', (string) $item->name);
        $this->assertSame('28', (string) $item->price);
        $this->assertSame('5', (string) $item->stock);
        $this->assertSame('GDP Brand', (string) $item->brand);
        $this->assertStringContainsString('product/grouped-display-parent-variant', (string) $item->url);
        // GroupCode must equal the parent's product number. ProductBuilder uses the
        // IdsCollection key as the product number, so the parent's product number
        // is the string 'parent-gdp'.
        $this->assertSame('parent-gdp', (string) $item->groupcode);

        // Assert the parent is NOT in the feed (excluded by filter).
        $this->assertCount(
            0,
            $xml->xpath('//item[attributes/attribute[name="sw-product-number" and value="parent-gdp"]]'),
            'Parent with displayParent=true must be excluded from the grouped feed.'
        );
        $this->assertSame(FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH, $this->extractVisibility($item));
    }

    /**
     * Grouped, mainVariantId set: the parent is excluded; all variants appear
     * individually with GroupCode = parent product number.
     */
    public function testGroupedMainVariantIdVariantAppearsWithGroupCode(): void
    {
        $variantId = $this->ids->create('variant-gmv');

        (new ProductBuilder($this->ids, 'parent-gmv'))
            ->name('Main Variant Parent (excluded from grouped feed)')
            ->price(45.00)
            ->stock(0)
            ->manufacturer('GMV Brand')
            ->variantListingConfig(['mainVariantId' => $variantId])
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-gmv'))
            ->name('Grouped MainVariant Variant')
            ->price(45.00)
            ->stock(3)
            ->parent('parent-gmv')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/grouped-main-variant');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-gmv');

        $this->assertSame('Grouped MainVariant Variant', (string) $item->name);
        $this->assertSame('45', (string) $item->price);
        $this->assertSame('3', (string) $item->stock);
        $this->assertSame('GMV Brand', (string) $item->brand);
        $this->assertStringContainsString('product/grouped-main-variant', (string) $item->url);
        $this->assertCount(
            0,
            $xml->xpath('//item[attributes/attribute[name="sw-product-number" and value="parent-gmv"]]'),
            'Parent must be excluded from the grouped feed.'
        );
        $this->assertSame(FeedService::PRODUCT_VISIBILITY_CATALOG_SEARCH, $this->extractVisibility($item));
    }

    /**
     * Grouped, expand-variants (expressionForListings=true): the parent is
     * excluded; each variant appears individually with GroupCode = parent product
     * number. The <otherVariants> block is suppressed by isGroupedProducts()=true
     * overriding $getVariants to false (FeedService line 558-560).
     */
    public function testGroupedExpandVariantsVariantsAppearWithGroupCode(): void
    {
        $groupId = $this->ids->create('size-group');
        $variantAId = $this->ids->create('variant-gev-a');
        $variantBId = $this->ids->create('variant-gev-b');

        (new ProductBuilder($this->ids, 'parent-gev'))
            ->name('Expand Variants Parent (excluded from grouped feed)')
            ->price(19.50)
            ->stock(0)
            ->manufacturer('GEV Brand')
            ->variantListingConfig([
                'configuratorGroupConfig' => [[
                    'id'                   => $groupId,
                    'representation'       => 'box',
                    'expressionForListings' => true,
                ]],
            ])
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-gev-a'))
            ->name('Grouped ExpandVariants Variant A')
            ->price(19.50)
            ->stock(8)
            ->parent('parent-gev')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-gev-b'))
            ->name('Grouped ExpandVariants Variant B')
            ->price(21.00)
            ->stock(2)
            ->parent('parent-gev')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantAId, 'product/grouped-expand-variant-a');
        $this->createSeoUrl($variantBId, 'product/grouped-expand-variant-b');

        $xml = $this->generateFeed(grouped: true);

        // Both variants must appear.
        $itemA = $this->findItem($xml, 'variant-gev-a');
        $itemB = $this->findItem($xml, 'variant-gev-b');

        // Variant A assertions.
        $this->assertSame('Grouped ExpandVariants Variant A', (string) $itemA->name);
        $this->assertSame('19.5', (string) $itemA->price);
        $this->assertSame('8', (string) $itemA->stock);
        $this->assertSame('GEV Brand', (string) $itemA->brand);
        $this->assertStringContainsString('product/grouped-expand-variant-a', (string) $itemA->url);
        $this->assertNotEmpty((string) $itemA->groupcode, '<groupcode> must be present in grouped mode.');

        // Variant B assertions.
        $this->assertSame('Grouped ExpandVariants Variant B', (string) $itemB->name);
        $this->assertSame('21', (string) $itemB->price);
        $this->assertSame('2', (string) $itemB->stock);
        $this->assertSame('GEV Brand', (string) $itemB->brand);
        $this->assertStringContainsString('product/grouped-expand-variant-b', (string) $itemB->url);
        $this->assertNotEmpty((string) $itemB->groupcode, '<groupcode> must be present in grouped mode.');

        // Both variants must share the same GroupCode (parent product number).
        $this->assertSame((string) $itemA->groupcode, (string) $itemB->groupcode, 'Both variants must share the same GroupCode.');

        // Parent must be excluded from the grouped feed.
        $this->assertCount(
            0,
            $xml->xpath('//item[attributes/attribute[name="sw-product-number" and value="parent-gev"]]'),
            'Parent must be excluded from the grouped feed.'
        );
    }

    // =========================================================================
    // Manufacturer inheritance
    // =========================================================================

    /**
     * In grouped mode, a variant without its own manufacturer must inherit the
     * parent's manufacturer in the rendered XML. This confirms that
     * FeedService::renderProducts() calls $product->setManufacturer($parent->getManufacturer())
     * before passing the product to the Twig template.
     */
    public function testGroupedVariantInheritsManufacturerFromParentInXml(): void
    {
        $variantId = $this->ids->create('variant-inherit-brand');

        (new ProductBuilder($this->ids, 'parent-inherit-brand'))
            ->name('Parent With Brand')
            ->price(34.99)
            ->stock(0)
            ->manufacturer('Inherited Brand')
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-inherit-brand'))
            ->name('Variant Without Brand')
            ->price(34.99)
            ->stock(4)
            ->parent('parent-inherit-brand')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/variant-inherit-brand');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-inherit-brand');

        $this->assertSame(
            'Inherited Brand',
            (string) $item->brand,
            '<brand> must be inherited from parent when variant has no manufacturer.'
        );
    }

    /**
     * In non-grouped mode (mainVariantId path), a variant without its own
     * manufacturer must also inherit from the parent in the rendered XML.
     */
    public function testNonGroupedMainVariantInheritsManufacturerFromParentInXml(): void
    {
        $variantId = $this->ids->create('variant-mv-brand');

        (new ProductBuilder($this->ids, 'parent-mv-brand'))
            ->name('Parent With Brand MV')
            ->price(18.00)
            ->stock(0)
            ->manufacturer('MV Parent Brand')
            ->variantListingConfig(['mainVariantId' => $variantId])
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-mv-brand'))
            ->name('Main Variant Without Brand')
            ->price(18.00)
            ->stock(3)
            ->parent('parent-mv-brand')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/main-variant-no-brand');

        // Run VariantListingUpdater synchronously so displayGroup is set on the variant.
        $this->updateVariantListing([$this->ids->get('parent-mv-brand')]);

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'variant-mv-brand');

        $this->assertSame(
            'MV Parent Brand',
            (string) $item->brand,
            '<brand> must be inherited from parent when main variant has no manufacturer.'
        );
    }

    // =========================================================================
    // Name inheritance: variant without own translation uses parent name
    // =========================================================================

    /**
     * A variant with no own name translation must render the parent's name in
     * the feed XML. Shopware's DAL resolves translation inheritance when loading
     * products, so product.translated.name on the variant entity will contain the
     * parent's name when the variant has no own translation record.
     *
     * This is the "truth" test for name inheritance: it generates real XML via
     * FeedService::generateFeed() and asserts the rendered <name> element.
     */
    public function testGroupedVariantWithoutOwnNameUsesParentNameInXml(): void
    {
        $variantId = $this->ids->create('variant-no-name');

        (new ProductBuilder($this->ids, 'parent-no-name'))
            ->name('Parent Name As Fallback')
            ->price(10.00)
            ->stock(0)
            ->manufacturer('Brand X')
            ->write($this->getContainer());

        // No ->name() call: the variant has no own name translation.
        // Shopware's DAL will resolve translated.name to the parent's value.
        (new ProductBuilder($this->ids, 'variant-no-name'))
            ->price(10.00)
            ->stock(1)
            ->parent('parent-no-name')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/variant-no-name');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-no-name');

        $this->assertSame(
            'Parent Name As Fallback',
            (string) $item->name,
            '<name> must be inherited from parent when variant has no own name translation.'
        );
    }

    // =========================================================================
    // GroupCode parity: grouped mode always uses parent number
    // =========================================================================

    /**
     * In grouped mode with a standalone product (no parent), GroupCode must equal
     * the product's own product number.
     */
    public function testGroupedStandaloneProductGroupCodeEqualsOwnNumber(): void
    {
        $this->createStandaloneProduct('PROD-GC-001', 'Grouped Standalone GC', 7, 'GC Brand', 12.00, 'product/grouped-standalone-gc');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'PROD-GC-001');

        $this->assertSame('PROD-GC-001', (string) $item->groupcode);
    }

    /**
     * In grouped mode with a variant, GroupCode must equal the parent's product
     * number — not the variant's own number.
     */
    public function testGroupedVariantGroupCodeEqualsParentNumber(): void
    {
        $variantId = $this->ids->create('variant-gc');

        (new ProductBuilder($this->ids, 'parent-gc'))
            ->name('GC Parent')
            ->price(20.00)
            ->stock(0)
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-gc'))
            ->name('GC Variant')
            ->price(20.00)
            ->stock(6)
            ->parent('parent-gc')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/gc-variant');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-gc');

        $groupCode = (string) $item->groupcode;
        $this->assertNotEmpty($groupCode, '<groupcode> must be present in grouped mode.');
        $this->assertNotSame('variant-gc', $groupCode, 'GroupCode must not be the variant\'s own number.');
    }

    /**
     * In non-grouped mode, no <groupcode> element must appear for a variant.
     */
    public function testNonGroupedVariantHasNoGroupCode(): void
    {
        $variantId = $this->ids->create('variant-ngc');

        (new ProductBuilder($this->ids, 'parent-ngc'))
            ->name('Non-Grouped Parent')
            ->price(15.00)
            ->stock(0)
            ->variantListingConfig(['displayParent' => false])
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-ngc'))
            ->name('Non-Grouped Variant')
            ->price(15.00)
            ->stock(2)
            ->parent('parent-ngc')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/non-grouped-variant');
        $this->updateVariantListing([$this->ids->get('parent-ngc')]);

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'variant-ngc');

        $this->assertEmpty((string) $item->groupcode, '<groupcode> must be absent in non-grouped mode.');
    }

    /**
     * A standalone product without a manufacturer must not emit a <brand> element.
     */
    public function testStandaloneProductWithoutManufacturerHasNoBrandElement(): void
    {
        $this->createStandaloneProductWithoutManufacturer('PROD-NO-BRAND', 'No Brand Product', 5, 14.99, 'product/no-brand');

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'PROD-NO-BRAND');

        $this->assertEmpty((string) $item->brand, '<brand> must be absent when product has no manufacturer.');
    }

    /**
     * When a variant has its own manufacturer, the parent's manufacturer must not
     * override it — even in grouped mode.
     */
    public function testGroupedVariantWithOwnManufacturerDoesNotInheritParentBrand(): void
    {
        $variantId = $this->ids->create('variant-own-brand');

        (new ProductBuilder($this->ids, 'parent-own-brand'))
            ->name('Parent With Different Brand')
            ->price(22.00)
            ->stock(0)
            ->manufacturer('Parent Brand')
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-own-brand'))
            ->name('Variant With Own Brand')
            ->price(22.00)
            ->stock(5)
            ->manufacturer('Variant Brand')
            ->parent('parent-own-brand')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/variant-own-brand');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-own-brand');

        $this->assertSame(
            'Variant Brand',
            (string) $item->brand,
            '<brand> must be the variant\'s own manufacturer, not the parent\'s.'
        );
    }

    /**
     * A variant with zero available stock must report 0 in the feed — not fall
     * back to the parent's stock.
     */
    public function testGroupedVariantWithZeroStockReportsZeroNotParentStock(): void
    {
        $variantId = $this->ids->create('variant-zero-stock');

        (new ProductBuilder($this->ids, 'parent-zero-stock'))
            ->name('Parent With Stock')
            ->price(15.00)
            ->stock(10)
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-zero-stock'))
            ->name('Zero Stock Variant')
            ->price(15.00)
            ->stock(0)
            ->parent('parent-zero-stock')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/zero-stock-variant');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-zero-stock');

        $this->assertSame('0', (string) $item->stock, '<stock> must be 0 for an out-of-stock variant, not the parent\'s stock.');
    }

    /**
     * When a variant has no cover image and the parent does, the feed must not
     * inherit the parent's image — <image> must be absent for that variant.
     */
    public function testGroupedVariantWithoutCoverDoesNotInheritParentImage(): void
    {
        $variantId = $this->ids->create('variant-no-cover');

        (new ProductBuilder($this->ids, 'parent-no-cover'))
            ->name('Parent With Cover')
            ->price(12.50)
            ->stock(0)
            ->write($this->getContainer());

        // No cover set on the variant — ProductBuilder does not assign a cover by default.
        (new ProductBuilder($this->ids, 'variant-no-cover'))
            ->name('Coverless Variant')
            ->price(12.50)
            ->stock(6)
            ->parent('parent-no-cover')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/coverless-variant');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-no-cover');

        $this->assertEmpty((string) $item->image, '<image> must be absent when variant has no cover.');
    }

    /**
     * In grouped mode, variant visibility is read from the variant's own visibility
     * record — not inherited from the parent. A variant with VISIBILITY_LINK (10)
     * whose parent has VISIBILITY_ALL (30) must appear in the feed with visibility=1.
     */
    public function testGroupedVariantVisibilityIsIndependentOfParentVisibility(): void
    {
        $variantId = $this->ids->create('variant-link-vis');

        (new ProductBuilder($this->ids, 'parent-link-vis'))
            ->name('Parent All Visible')
            ->price(20.00)
            ->stock(0)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->write($this->getContainer());

        (new ProductBuilder($this->ids, 'variant-link-vis'))
            ->name('Variant Link Only')
            ->price(20.00)
            ->stock(3)
            ->parent('parent-link-vis')
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_LINK)
            ->write($this->getContainer());

        $this->createSeoUrl($variantId, 'product/variant-link-vis');

        $xml = $this->generateFeed(grouped: true);
        $item = $this->findItem($xml, 'variant-link-vis');

        $this->assertSame(
            FeedService::PRODUCT_NOT_VISIBLE,
            $this->extractVisibility($item),
            'Variant with VISIBILITY_LINK must have visibility=1, independent of parent visibility.'
        );
    }

    // =========================================================================
    // Product exclusion — feed must NOT contain certain products
    //
    // FeedService applies ProductAvailableFilter(VISIBILITY_LINK) which requires:
    //   - product.active = true
    //   - a product_visibility record for the sales channel with visibility >= 10
    //
    // Products failing either condition are silently excluded from the feed at
    // query time, before any rendering happens. These tests confirm the exclusion
    // is enforced in the real generated XML, not just assumed from the filter.
    // =========================================================================

    /**
     * An inactive product (active=false) must not appear in the feed, even when
     * it has a valid visibility record. ProductAvailableFilter requires active=true.
     */
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

    /**
     * A product with no visibility record for the sales channel must not appear
     * in the feed. ProductAvailableFilter requires a visibility record >= VISIBILITY_LINK.
     */
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

    /**
     * A product visible only via direct link (VISIBILITY_LINK = 10) must still
     * appear in the feed — the feed uses VISIBILITY_LINK as its minimum threshold.
     * It must have visibility attribute = 1 (PRODUCT_NOT_VISIBLE) in the XML,
     * meaning Tweakwise hides it from listings but it exists in the index.
     */
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

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'PROD-LINK');

        $this->assertSame(
            FeedService::PRODUCT_NOT_VISIBLE,
            $this->extractVisibility($item),
            'VISIBILITY_LINK (10) must produce visibility=1 in the feed XML.'
        );
    }

    /**
     * A product with VISIBILITY_SEARCH (20) must appear in the feed with
     * visibility attribute = 3 (PRODUCT_VISIBILITY_SEARCH).
     */
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

        $xml = $this->generateFeed(grouped: false);
        $item = $this->findItem($xml, 'PROD-SEARCH');

        $this->assertSame(
            FeedService::PRODUCT_VISIBILITY_SEARCH,
            $this->extractVisibility($item),
            'VISIBILITY_SEARCH (20) must produce visibility=3 in the feed XML.'
        );
    }

    /**
     * In a feed with excludeChildren=true, variant products (products with a parentId)
     * must not appear in the feed. Only standalone and parent products are included.
     */
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

    private function createStandaloneProductWithoutManufacturer(
        string $number,
        string $name,
        int $stock,
        float $price,
        string $seoPath
    ): string {
        $productId = $this->ids->create($number);

        (new ProductBuilder($this->ids, $number))
            ->name($name)
            ->price($price)
            ->stock($stock)
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

    /**
     * Forces the VariantListingUpdater to run synchronously for the given parent
     * product IDs. This sets the display_group column on child variants, which is
     * normally computed asynchronously via the message bus (ProductIndexer dispatches
     * a ProductIndexingMessage). Without this, Shopware's listingLoader excludes
     * products because it filters on displayGroup IS NOT NULL.
     *
     * @param string[] $parentIds hex UUIDs of the parent products
     */
    private function updateVariantListing(array $parentIds): void
    {
        $this->getContainer()->get(VariantListingUpdater::class)->update($parentIds, $this->context);
    }

    /**
     * Creates a feed record for the default sales channel domain, runs
     * FeedService::generateFeed(), and returns the parsed XML root element.
     */
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

    /**
     * Finds an <item> by its sw-product-number attribute value.
     * Uses the product number key from the IdsCollection when an alias is given,
     * or falls back to using the value directly as the product number string.
     */
    private function findItem(\SimpleXMLElement $xml, string $numberOrKey): \SimpleXMLElement
    {
        $number = $numberOrKey;
        $escapedNumber = htmlspecialchars($number, ENT_XML1, 'UTF-8');
        $matches = $xml->xpath(
            sprintf('//item[attributes/attribute[name="sw-product-number" and value="%s"]]', $escapedNumber)
        );

        $this->assertNotEmpty($matches, sprintf('Product "%s" must appear in the rendered feed XML.', $number));

        return $matches[0];
    }

    private function assertItemPresent(\SimpleXMLElement $xml, string $numberOrKey, string $message = ''): void
    {
        $number = $numberOrKey;
        $escapedNumber = htmlspecialchars($number, ENT_XML1, 'UTF-8');
        $matches = $xml->xpath(
            sprintf('//item[attributes/attribute[name="sw-product-number" and value="%s"]]', $escapedNumber)
        );

        $this->assertNotEmpty($matches, $message ?: sprintf('Product "%s" must appear in the feed XML.', $number));
    }

    private function assertItemAbsent(\SimpleXMLElement $xml, string $numberOrKey, string $message = ''): void
    {
        $number = $numberOrKey;
        $escapedNumber = htmlspecialchars($number, ENT_XML1, 'UTF-8');
        $matches = $xml->xpath(
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
