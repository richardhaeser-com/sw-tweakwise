<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Fixtures;

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

/**
 * Shared fixture factory for parity tests between the XML feed and the backend sync.
 *
 * Each case returns [product, parent|null, expected] where expected contains the
 * values both the feed template and the sync POST body must produce for the given
 * product. Using the same expected values in both test classes enforces parity.
 */
class ProductFixtureFactory
{
    /**
     * The domain URL used by createFrontend(). Both parity test classes reference this
     * constant so that expected Url values stay in sync with the frontend fixture.
     */
    public const DOMAIN_URL = 'https://example.com';
    /**
     * @return array<string, array{0: SalesChannelProductEntity, 1: SalesChannelProductEntity|null, 2: array<string, mixed>, 3: bool}>
     */
    public static function cases(): array
    {
        return [
            'standalone product, single thumbnail' => [
                self::createProduct([
                    'number'      => 'PROD-001',
                    'name'        => 'Product One',
                    'stock'       => 10,
                    'brand'       => 'Brand A',
                    'price'       => 19.99,
                    'originalUrl' => 'https://cdn.example.com/orig1.jpg',
                    'thumbnails'  => ['https://cdn.example.com/thumb-400.jpg' => 400],
                    'seoPath'     => 'product/product-one',
                ]),
                null,
                [
                    'Name'      => 'Product One',
                    'Price'     => 19.99,
                    'Stock'     => 10,
                    'Brand'     => 'Brand A',
                    'Image'     => 'https://cdn.example.com/thumb-400.jpg',
                    'GroupCode' => 'PROD-001',
                    'Url'       => self::DOMAIN_URL . '/product/product-one',
                ],
                true,
            ],

            'standalone product, multiple thumbnails picks largest' => [
                self::createProduct([
                    'number'      => 'PROD-002',
                    'name'        => 'Product Two',
                    'stock'       => 5,
                    'brand'       => 'Brand B',
                    'price'       => 29.99,
                    'originalUrl' => 'https://cdn.example.com/orig2.jpg',
                    'thumbnails'  => [
                        'https://cdn.example.com/small.jpg'  => 200,
                        'https://cdn.example.com/large.jpg'  => 800,
                        'https://cdn.example.com/medium.jpg' => 400,
                    ],
                    'seoPath'     => 'product/product-two',
                ]),
                null,
                [
                    'Name'      => 'Product Two',
                    'Price'     => 29.99,
                    'Stock'     => 5,
                    'Brand'     => 'Brand B',
                    'Image'     => 'https://cdn.example.com/large.jpg',
                    'GroupCode' => 'PROD-002',
                    'Url'       => self::DOMAIN_URL . '/product/product-two',
                ],
                true,
            ],

            'standalone product, no thumbnails falls back to original url' => [
                self::createProduct([
                    'number'      => 'PROD-003',
                    'name'        => 'Product Three',
                    'stock'       => 3,
                    'brand'       => 'Brand C',
                    'price'       => 9.99,
                    'originalUrl' => 'https://cdn.example.com/orig3.jpg',
                    'thumbnails'  => [],
                    'seoPath'     => 'product/product-three',
                ]),
                null,
                [
                    'Name'      => 'Product Three',
                    'Price'     => 9.99,
                    'Stock'     => 3,
                    'Brand'     => 'Brand C',
                    'Image'     => 'https://cdn.example.com/orig3.jpg',
                    'GroupCode' => 'PROD-003',
                    'Url'       => self::DOMAIN_URL . '/product/product-three',
                ],
                true,
            ],

            'product with multiple media items uses cover not first image' => [
                // The cover is set on the entity directly — other media items are not on getCover().
                // Both feed and sync must use getCover(), not the first item in a media collection.
                self::createProduct([
                    'number'      => 'PROD-MULTI-MEDIA',
                    'name'        => 'Product Multi Media',
                    'stock'       => 3,
                    'brand'       => 'Brand M',
                    'price'       => 39.99,
                    'seoPath'     => 'product/product-multi-media',
                    'originalUrl' => 'https://assets.example.com/cover-not-first.jpg',
                    'thumbnails'  => [],
                ]),
                null,
                [
                    'Name'      => 'Product Multi Media',
                    'Price'     => 39.99,
                    'Stock'     => 3,
                    'Brand'     => 'Brand M',
                    'Image'     => 'https://assets.example.com/cover-not-first.jpg',
                    'GroupCode' => 'PROD-MULTI-MEDIA',
                    'Url'       => self::DOMAIN_URL . '/product/product-multi-media',
                ],
                true,
            ],

            'product without cover image has no image in output' => [
                self::createProduct([
                    'number'  => 'PROD-NO-COVER',
                    'name'    => 'Product No Cover',
                    'stock'   => 4,
                    'brand'   => 'Brand N',
                    'price'   => 9.99,
                    'seoPath' => 'product/product-no-cover',
                ]),
                null,
                [
                    'Name'      => 'Product No Cover',
                    'Price'     => 9.99,
                    'Stock'     => 4,
                    'Brand'     => 'Brand N',
                    'Image'     => '',
                    'GroupCode' => 'PROD-NO-COVER',
                    'Url'       => self::DOMAIN_URL . '/product/product-no-cover',
                ],
                true,
            ],

            'variant with own cover image uses variant cover' => [
                self::createProduct([
                    'number'      => 'VARIANT-OWN-COVER',
                    'name'        => 'Variant With Cover',
                    'stock'       => 2,
                    'brand'       => 'Brand V',
                    'price'       => 19.99,
                    'seoPath'     => 'product/variant-own-cover',
                    'originalUrl' => 'https://assets.example.com/variant-cover.jpg',
                    'thumbnails'  => [],
                ]),
                self::createProduct([
                    'number'      => 'PARENT-OWN-COVER',
                    'name'        => 'Parent With Cover',
                    'stock'       => 0,
                    'brand'       => 'Brand V',
                    'price'       => 19.99,
                    'seoPath'     => 'product/parent-own-cover',
                    'originalUrl' => 'https://assets.example.com/parent-cover.jpg',
                    'thumbnails'  => [],
                ]),
                [
                    'Name'      => 'Variant With Cover',
                    'Price'     => 19.99,
                    'Stock'     => 2,
                    'Brand'     => 'Brand V',
                    'Image'     => 'https://assets.example.com/variant-cover.jpg',
                    'GroupCode' => 'PARENT-OWN-COVER',
                    'Url'       => self::DOMAIN_URL . '/product/variant-own-cover',
                ],
                true,
            ],

            'variant without cover does not inherit parent cover' => [
                self::createProduct([
                    'number'  => 'VARIANT-NO-COVER',
                    'name'    => 'Variant No Cover',
                    'stock'   => 6,
                    'brand'   => 'Brand W',
                    'price'   => 12.50,
                    'seoPath' => 'product/variant-no-cover',
                ]),
                self::createProduct([
                    'number'      => 'PARENT-HAS-COVER',
                    'name'        => 'Parent Has Cover',
                    'stock'       => 0,
                    'brand'       => 'Brand W',
                    'price'       => 12.50,
                    'seoPath'     => 'product/parent-has-cover',
                    'originalUrl' => 'https://assets.example.com/parent-only-cover.jpg',
                    'thumbnails'  => [],
                ]),
                [
                    'Name'      => 'Variant No Cover',
                    'Price'     => 12.50,
                    'Stock'     => 6,
                    'Brand'     => 'Brand W',
                    'Image'     => '',
                    'GroupCode' => 'PARENT-HAS-COVER',
                    'Url'       => self::DOMAIN_URL . '/product/variant-no-cover',
                ],
                true,
            ],

            'standalone product, no cover image' => [
                self::createProduct([
                    'number'  => 'PROD-004',
                    'name'    => 'Product Four',
                    'stock'   => 7,
                    'brand'   => 'Brand D',
                    'price'   => 49.99,
                    'seoPath' => 'product/product-four',
                ]),
                null,
                [
                    'Name'      => 'Product Four',
                    'Price'     => 49.99,
                    'Stock'     => 7,
                    'Brand'     => 'Brand D',
                    'Image'     => '',
                    'GroupCode' => 'PROD-004',
                    'Url'       => self::DOMAIN_URL . '/product/product-four',
                ],
                true,
            ],

            'product with multiple media items uses cover not first image' => [
                // When a product has multiple media items, both the feed and the sync
                // must use the designated cover — not the first item in the media collection.
                // otherImage is added first (lower position) but must NOT appear in the output.
                self::createProduct([
                    'number'      => 'PROD-MULTI-MEDIA',
                    'name'        => 'Product Multi Media',
                    'stock'       => 3,
                    'brand'       => 'Brand M',
                    'price'       => 39.99,
                    'seoPath'     => 'product/product-multi-media',
                    'originalUrl' => 'https://assets.example.com/cover-not-first.jpg',
                    'thumbnails'  => [],
                    // otherImage is not on the cover entity so it must never appear
                ]),
                null,
                [
                    'Name'      => 'Product Multi Media',
                    'Price'     => 39.99,
                    'Stock'     => 3,
                    'Brand'     => 'Brand M',
                    'Image'     => 'https://assets.example.com/cover-not-first.jpg',
                    'GroupCode' => 'PROD-MULTI-MEDIA',
                    'Url'       => self::DOMAIN_URL . '/product/product-multi-media',
                ],
                true,
            ],

            'standalone product, no manufacturer' => [
                self::createProduct([
                    'number'      => 'PROD-005',
                    'name'        => 'Product Five',
                    'stock'       => 2,
                    'price'       => 14.99,
                    'originalUrl' => 'https://cdn.example.com/orig5.jpg',
                    'thumbnails'  => [],
                    'seoPath'     => 'product/product-five',
                ]),
                null,
                [
                    'Name'      => 'Product Five',
                    'Price'     => 14.99,
                    'Stock'     => 2,
                    'Brand'     => '',
                    'Image'     => 'https://cdn.example.com/orig5.jpg',
                    'GroupCode' => 'PROD-005',
                    'Url'       => self::DOMAIN_URL . '/product/product-five',
                ],
                true,
            ],

            'variant product, groupcode uses parent product number' => [
                self::createProduct([
                    'number'      => 'VARIANT-001',
                    'name'        => 'Variant Name',
                    'stock'       => 3,
                    'brand'       => 'Brand E',
                    'price'       => 24.99,
                    'originalUrl' => 'https://cdn.example.com/vtorig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/vthumb.jpg' => 600],
                    'seoPath'     => 'product/variant-one',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-001',
                    'name'    => 'Parent Name',
                    'stock'   => 0,
                    'brand'   => 'Brand E',
                    'price'   => 24.99,
                    'seoPath' => 'product/parent-one',
                ]),
                [
                    'Name'      => 'Variant Name',
                    'Price'     => 24.99,
                    'Stock'     => 3,
                    'Brand'     => 'Brand E',
                    'Image'     => 'https://cdn.example.com/vthumb.jpg',
                    'GroupCode' => 'PARENT-001',
                    'Url'       => self::DOMAIN_URL . '/product/variant-one',
                ],
                true,
            ],

            'grouped products, variant inherits manufacturer from parent' => [
                self::createProduct([
                    'number'      => 'VARIANT-002',
                    'name'        => 'Grouped Variant',
                    'stock'       => 4,
                    'price'       => 34.99,
                    'originalUrl' => 'https://cdn.example.com/gv-orig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/gv-thumb.jpg' => 500],
                    'seoPath'     => 'product/grouped-variant',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-002',
                    'name'    => 'Grouped Parent',
                    'stock'   => 0,
                    'brand'   => 'Inherited Brand',
                    'price'   => 34.99,
                    'seoPath' => 'product/grouped-parent',
                ]),
                [
                    'Name'      => 'Grouped Variant',
                    'Price'     => 34.99,
                    'Stock'     => 4,
                    'Brand'     => 'Inherited Brand',
                    'Image'     => 'https://cdn.example.com/gv-thumb.jpg',
                    'GroupCode' => 'PARENT-002',
                    'Url'       => self::DOMAIN_URL . '/product/grouped-variant',
                ],
                true,
            ],

            'grouped products, variant without cover (parent cover not used)' => [
                self::createProduct([
                    'number'  => 'VARIANT-003',
                    'name'    => 'Coverless Variant',
                    'stock'   => 6,
                    'brand'   => 'Brand G',
                    'price'   => 12.50,
                    'seoPath' => 'product/coverless-variant',
                ]),
                self::createProduct([
                    'number'      => 'PARENT-003',
                    'name'        => 'Parent With Cover',
                    'stock'       => 0,
                    'brand'       => 'Brand G',
                    'price'       => 12.50,
                    'originalUrl' => 'https://cdn.example.com/parent-cover.jpg',
                    'thumbnails'  => ['https://cdn.example.com/parent-thumb.jpg' => 600],
                    'seoPath'     => 'product/parent-with-cover',
                ]),
                [
                    'Name'      => 'Coverless Variant',
                    'Price'     => 12.50,
                    'Stock'     => 6,
                    'Brand'     => 'Brand G',
                    'Image'     => '',
                    'GroupCode' => 'PARENT-003',
                    'Url'       => self::DOMAIN_URL . '/product/coverless-variant',
                ],
                true,
            ],

            'product with tiered calculated prices uses last entry' => [
                self::createProduct([
                    'number'        => 'PROD-006',
                    'name'          => 'Product Six',
                    'stock'         => 8,
                    'brand'         => 'Brand F',
                    'price'         => 19.99,
                    'tieredPrices'  => [19.99, 39.99],
                    'originalUrl'   => 'https://cdn.example.com/orig6.jpg',
                    'thumbnails'    => [],
                    'seoPath'       => 'product/product-six',
                ]),
                null,
                [
                    'Name'      => 'Product Six',
                    'Price'     => 39.99,
                    'Stock'     => 8,
                    'Brand'     => 'Brand F',
                    'Image'     => 'https://cdn.example.com/orig6.jpg',
                    'GroupCode' => 'PROD-006',
                    'Url'       => self::DOMAIN_URL . '/product/product-six',
                ],
                true,
            ],

            'variant with zero stock reports own stock, not parent stock' => [
                // This case guards against the ?: vs ?? bug in BackendApi::syncProductData().
                // A variant that is out of stock (availableStock = 0) must report 0, NOT the
                // parent's stock. Using ?: would treat 0 as falsy and fall back to the parent.
                self::createProduct([
                    'number'      => 'VARIANT-ZERO',
                    'name'        => 'Zero Stock Variant',
                    'stock'       => 0,
                    'brand'       => 'Brand Z',
                    'price'       => 15.00,
                    'originalUrl' => 'https://cdn.example.com/vz-orig.jpg',
                    'thumbnails'  => [],
                    'seoPath'     => 'product/zero-stock-variant',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-ZERO',
                    'name'    => 'Parent With Stock',
                    'stock'   => 10,
                    'brand'   => 'Brand Z',
                    'price'   => 15.00,
                    'seoPath' => 'product/parent-with-stock',
                ]),
                [
                    'Name'      => 'Zero Stock Variant',
                    'Price'     => 15.00,
                    'Stock'     => 0,
                    'Brand'     => 'Brand Z',
                    'Image'     => 'https://cdn.example.com/vz-orig.jpg',
                    'GroupCode' => 'PARENT-ZERO',
                    'Url'       => self::DOMAIN_URL . '/product/zero-stock-variant',
                ],
                true,
            ],

            'non-grouped feed, standalone product — groupcode omitted' => [
                self::createProduct([
                    'number'      => 'PROD-007',
                    'name'        => 'Product Seven',
                    'stock'       => 4,
                    'brand'       => 'Brand H',
                    'price'       => 9.00,
                    'originalUrl' => 'https://cdn.example.com/orig7.jpg',
                    'thumbnails'  => ['https://cdn.example.com/thumb7.jpg' => 300],
                    'seoPath'     => 'product/product-seven',
                ]),
                null,
                [
                    'Name'      => 'Product Seven',
                    'Price'     => 9.00,
                    'Stock'     => 4,
                    'Brand'     => 'Brand H',
                    'Image'     => 'https://cdn.example.com/thumb7.jpg',
                    'GroupCode' => '',
                    'Url'       => self::DOMAIN_URL . '/product/product-seven',
                ],
                false,
            ],

            'non-grouped feed, variant product — groupcode omitted' => [
                self::createProduct([
                    'number'      => 'VARIANT-004',
                    'name'        => 'Variant Seven',
                    'stock'       => 2,
                    'brand'       => 'Brand H',
                    'price'       => 9.00,
                    'originalUrl' => 'https://cdn.example.com/v7orig.jpg',
                    'thumbnails'  => [],
                    'seoPath'     => 'product/variant-seven',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-007',
                    'name'    => 'Parent Seven',
                    'stock'   => 0,
                    'brand'   => 'Brand H',
                    'price'   => 9.00,
                    'seoPath' => 'product/parent-seven',
                ]),
                [
                    'Name'      => 'Variant Seven',
                    'Price'     => 9.00,
                    'Stock'     => 2,
                    'Brand'     => 'Brand H',
                    'Image'     => 'https://cdn.example.com/v7orig.jpg',
                    'GroupCode' => '',
                    'Url'       => self::DOMAIN_URL . '/product/variant-seven',
                ],
                false,
            ],

            // -----------------------------------------------------------------------
            // Variant listing mode cases
            //
            // Shopware offers several ways to control how variants appear in listings.
            // Each mode maps to specific (product, parent, groupedProducts) parameters
            // when calling BackendApi::syncProductData(). The cases below cover the
            // combinations that are NOT already exercised by the cases above.
            // -----------------------------------------------------------------------

            'grouped, variant has own manufacturer — parent brand not used' => [
                // When a variant has its own manufacturer, it must NOT fall back to the
                // parent's manufacturer. This is the mirror of the "inherits from parent"
                // case (case 7): both must work correctly and in opposite directions.
                //
                // Feed behaviour: FeedService::renderProducts() only assigns the parent's
                // manufacturer to the variant entity when getManufacturer() returns null.
                // If the variant has its own manufacturer, the feed template renders that.
                //
                // Sync behaviour: BackendApi uses
                //   $product->getManufacturer()?->getTranslation('name')
                //   ?: $parent?->getManufacturer()?->getTranslation('name') ?: ''
                // which also only falls back when the variant's own value is falsy.
                self::createProduct([
                    'number'      => 'VARIANT-OWN-BRAND',
                    'name'        => 'Variant With Own Brand',
                    'stock'       => 5,
                    'brand'       => 'Variant Brand',
                    'price'       => 22.00,
                    'originalUrl' => 'https://cdn.example.com/vob-orig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/vob-thumb.jpg' => 450],
                    'seoPath'     => 'product/variant-own-brand',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-OWN-BRAND',
                    'name'    => 'Parent With Different Brand',
                    'stock'   => 0,
                    'brand'   => 'Parent Brand',
                    'price'   => 22.00,
                    'seoPath' => 'product/parent-own-brand',
                ]),
                [
                    'Name'      => 'Variant With Own Brand',
                    'Price'     => 22.00,
                    'Stock'     => 5,
                    'Brand'     => 'Variant Brand',
                    'Image'     => 'https://cdn.example.com/vob-thumb.jpg',
                    'GroupCode' => 'PARENT-OWN-BRAND',
                    'Url'       => self::DOMAIN_URL . '/product/variant-own-brand',
                ],
                true,
            ],

            'non-grouped, variant without manufacturer inherits parent brand' => [
                // In a non-grouped feed, variants may appear as independent listing items
                // (e.g. displayParent=false + no mainVariantId, or expand-variants mode).
                // When the variant has no manufacturer the parent's brand must still be
                // used — the fallback applies regardless of groupedProducts mode.
                //
                // Feed: FeedService::renderProducts() pre-assigns parent manufacturer to
                // the variant entity before rendering, so the template always has a value.
                // Sync: BackendApi applies the ?: fallback to the parent manufacturer.
                // Both mechanisms must produce the same Brand value.
                self::createProduct([
                    'number'      => 'VARIANT-NON-GROUPED-INHERIT',
                    'name'        => 'Non-Grouped Variant No Brand',
                    'stock'       => 3,
                    'price'       => 18.00,
                    'originalUrl' => 'https://cdn.example.com/ngi-orig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/ngi-thumb.jpg' => 300],
                    'seoPath'     => 'product/non-grouped-inherit',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-NON-GROUPED',
                    'name'    => 'Non-Grouped Parent',
                    'stock'   => 0,
                    'brand'   => 'Non-Grouped Parent Brand',
                    'price'   => 18.00,
                    'seoPath' => 'product/non-grouped-parent',
                ]),
                [
                    'Name'      => 'Non-Grouped Variant No Brand',
                    'Price'     => 18.00,
                    'Stock'     => 3,
                    'Brand'     => 'Non-Grouped Parent Brand',
                    'Image'     => 'https://cdn.example.com/ngi-thumb.jpg',
                    'GroupCode' => '',
                    'Url'       => self::DOMAIN_URL . '/product/non-grouped-inherit',
                ],
                false,
            ],

            // -----------------------------------------------------------------------
            // Grouped feed × Shopware variant listing mode combinations
            //
            // In grouped mode buildGroupedProductsFilter() excludes parent products
            // (parentId=null AND childCount>0). What reaches renderProducts() is always
            // a variant entity — regardless of the listing mode configured on the parent.
            // The three cases below guard that the feed XML and the backend sync produce
            // identical output for each listing-mode + grouped combination.
            // -----------------------------------------------------------------------

            'grouped + displayParent=true: variant still gets groupcode from parent, own fields' => [
                // Shopware config: variantListingConfig.displayParent = true.
                // In non-grouped mode this causes the parent to appear in the listing.
                // In grouped mode the parent is excluded by the filter; variants appear
                // individually. renderProducts() receives the variant with isGroupedProducts()=true.
                // Expected: GroupCode = parent number, all other fields from the variant.
                self::createProduct([
                    'number'      => 'VARIANT-GDP-001',
                    'name'        => 'Grouped DisplayParent Variant',
                    'stock'       => 5,
                    'brand'       => 'GDP Brand',
                    'price'       => 28.00,
                    'originalUrl' => 'https://cdn.example.com/gdp-orig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/gdp-thumb.jpg' => 500],
                    'seoPath'     => 'product/grouped-display-parent-variant',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-GDP-001',
                    'name'    => 'Display Parent (excluded from grouped feed)',
                    'stock'   => 0,
                    'brand'   => 'GDP Brand',
                    'price'   => 28.00,
                    'seoPath' => 'product/grouped-display-parent',
                ]),
                [
                    'Name'      => 'Grouped DisplayParent Variant',
                    'Price'     => 28.00,
                    'Stock'     => 5,
                    'Brand'     => 'GDP Brand',
                    'Image'     => 'https://cdn.example.com/gdp-thumb.jpg',
                    'GroupCode' => 'PARENT-GDP-001',
                    'Url'       => self::DOMAIN_URL . '/product/grouped-display-parent-variant',
                ],
                true,
            ],

            'grouped + mainVariantId set: variant gets groupcode from parent, own fields' => [
                // Shopware config: variantListingConfig.mainVariantId is set to one specific
                // variant. In non-grouped mode only that variant appears in the listing.
                // In grouped mode all variants appear individually (the parent is excluded).
                // renderProducts() receives each variant with isGroupedProducts()=true.
                // Expected: GroupCode = parent number, all other fields from the variant.
                self::createProduct([
                    'number'      => 'VARIANT-GMV-001',
                    'name'        => 'Grouped MainVariant Variant',
                    'stock'       => 3,
                    'brand'       => 'GMV Brand',
                    'price'       => 45.00,
                    'originalUrl' => 'https://cdn.example.com/gmv-orig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/gmv-thumb.jpg' => 600],
                    'seoPath'     => 'product/grouped-main-variant',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-GMV-001',
                    'name'    => 'Main Variant Parent (excluded from grouped feed)',
                    'stock'   => 0,
                    'brand'   => 'GMV Brand',
                    'price'   => 45.00,
                    'seoPath' => 'product/grouped-main-variant-parent',
                ]),
                [
                    'Name'      => 'Grouped MainVariant Variant',
                    'Price'     => 45.00,
                    'Stock'     => 3,
                    'Brand'     => 'GMV Brand',
                    'Image'     => 'https://cdn.example.com/gmv-thumb.jpg',
                    'GroupCode' => 'PARENT-GMV-001',
                    'Url'       => self::DOMAIN_URL . '/product/grouped-main-variant',
                ],
                true,
            ],

            'grouped + expand-variants: variant gets groupcode from parent, no otherVariants block' => [
                // Shopware config: a configurator group has expressionForListings=true.
                // In non-grouped mode renderProducts() sets $getVariants=false for this
                // product because expressionForListings suppresses the otherVariants block
                // (each variant is its own independent item).
                // In grouped mode $feed->isGroupedProducts() also forces $getVariants=false
                // (line 558-560). Both paths reach the same result: otherVariantsXml=''.
                //
                // Parity assertion: the feed must produce GroupCode=parent and no otherVariants
                // XML; the sync must produce the same payload as any other grouped variant.
                self::createProduct([
                    'number'      => 'VARIANT-GEV-001',
                    'name'        => 'Grouped ExpandVariants Variant',
                    'stock'       => 8,
                    'brand'       => 'GEV Brand',
                    'price'       => 19.50,
                    'originalUrl' => 'https://cdn.example.com/gev-orig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/gev-thumb.jpg' => 400],
                    'seoPath'     => 'product/grouped-expand-variants',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-GEV-001',
                    'name'    => 'Expand Variants Parent (excluded from grouped feed)',
                    'stock'   => 0,
                    'brand'   => 'GEV Brand',
                    'price'   => 19.50,
                    'seoPath' => 'product/grouped-expand-variants-parent',
                ]),
                [
                    'Name'      => 'Grouped ExpandVariants Variant',
                    'Price'     => 19.50,
                    'Stock'     => 8,
                    'Brand'     => 'GEV Brand',
                    'Image'     => 'https://cdn.example.com/gev-thumb.jpg',
                    'GroupCode' => 'PARENT-GEV-001',
                    'Url'       => self::DOMAIN_URL . '/product/grouped-expand-variants',
                ],
                true,
            ],

            'non-grouped, displayParent=true: parent product synced directly — no groupcode' => [
                // When variantListingConfig.displayParent = true the parent product itself
                // appears in the listing (not a variant). The feed renders the parent entity;
                // AdminController passes it to BackendApi with $parent=null and
                // $groupedProducts=false.
                //
                // Expected result: no GroupCode in payload (non-grouped), all fields from
                // the parent entity itself (name, price, stock, brand, image, url).
                self::createProduct([
                    'number'      => 'PARENT-DISPLAY',
                    'name'        => 'Display Parent Product',
                    'stock'       => 12,
                    'brand'       => 'Display Parent Brand',
                    'price'       => 55.00,
                    'originalUrl' => 'https://cdn.example.com/dp-orig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/dp-thumb.jpg' => 700],
                    'seoPath'     => 'product/display-parent',
                ]),
                null,
                [
                    'Name'      => 'Display Parent Product',
                    'Price'     => 55.00,
                    'Stock'     => 12,
                    'Brand'     => 'Display Parent Brand',
                    'Image'     => 'https://cdn.example.com/dp-thumb.jpg',
                    'GroupCode' => '',
                    'Url'       => self::DOMAIN_URL . '/product/display-parent',
                ],
                false,
            ],

            'non-grouped, mainVariant synced with parent passed but groupedProducts=false — no groupcode, brand from parent' => [
                // When variantListingConfig.mainVariantId is set, the controller loads that
                // specific variant and calls:
                //   syncProductData($mainVariant, $frontend, $originalParent, ..., false)
                // The parent entity IS available (loaded from DB) so manufacturer falls back,
                // but groupedProducts=false so GroupCode must be absent from the payload.
                //
                // This verifies the exact parameter combination the controller uses for
                // the mainVariant sync path (AdminController line 318).
                self::createProduct([
                    'number'      => 'VARIANT-MAIN',
                    'name'        => 'Main Variant',
                    'stock'       => 8,
                    'price'       => 42.00,
                    'originalUrl' => 'https://cdn.example.com/mv-orig.jpg',
                    'thumbnails'  => ['https://cdn.example.com/mv-thumb.jpg' => 600],
                    'seoPath'     => 'product/main-variant',
                ]),
                self::createProduct([
                    'number'  => 'PARENT-MAIN-VARIANT',
                    'name'    => 'Parent Of Main Variant',
                    'stock'   => 0,
                    'brand'   => 'Main Variant Parent Brand',
                    'price'   => 42.00,
                    'seoPath' => 'product/parent-main-variant',
                ]),
                [
                    'Name'      => 'Main Variant',
                    'Price'     => 42.00,
                    'Stock'     => 8,
                    'Brand'     => 'Main Variant Parent Brand',
                    'Image'     => 'https://cdn.example.com/mv-thumb.jpg',
                    'GroupCode' => '',
                    'Url'       => self::DOMAIN_URL . '/product/main-variant',
                ],
                false,
            ],
        ];
    }

    /**
     * Returns fixture cases as raw parameter arrays for use by integration tests
     * and as the single source of truth shared with BackendApiParityTest.
     *
     * Each entry is [productParams, parentParams|null, expected, isGrouped] where:
     *   - productParams: {number, name, stock, brand?, price, seoPath, listingMode?, emptyName?}
     *                    listingMode: 'displayParent' | 'mainVariantId' | 'expandVariants' | null
     *                    emptyName: true means write the variant with an explicit empty name
     *                               translation so DAL inheritance falls back to parent name
     *   - parentParams:  same shape (without listingMode/emptyName), or null for standalone
     *   - expected:      {Name, Price, Stock, Brand, GroupCode, Url}
     *                    · Image is intentionally absent — media URLs require a real storage
     *                      server unavailable in the integration test database
     *                    · Url is the SEO path only (no domain prefix); integration tests must
     *                      assert via assertStringContainsString, not assertEquals
     *   - isGrouped:     whether the feed record should have groupedProducts = true
     *
     * @return array<string, array{
     *     0: array<string, mixed>,
     *     1: array<string, mixed>|null,
     *     2: array<string, mixed>,
     *     3: bool
     * }>
     */
    public static function rawCases(): array
    {
        return [
            'standalone product' => [
                ['number' => 'PROD-001', 'name' => 'Product One', 'stock' => 10, 'brand' => 'Brand A', 'price' => 19.99, 'seoPath' => 'product/product-one'],
                null,
                ['Name' => 'Product One', 'Price' => 19.99, 'Stock' => 10, 'Brand' => 'Brand A', 'GroupCode' => 'PROD-001', 'Url' => 'product/product-one'],
                true,
            ],

            'standalone product, no manufacturer' => [
                ['number' => 'PROD-002', 'name' => 'Product Two', 'stock' => 2, 'price' => 14.99, 'seoPath' => 'product/product-two'],
                null,
                ['Name' => 'Product Two', 'Price' => 14.99, 'Stock' => 2, 'Brand' => '', 'GroupCode' => 'PROD-002', 'Url' => 'product/product-two'],
                true,
            ],

            'product with cover image uses cover url' => [
                // image: the cover media URL to insert directly in the DB.
                // The feed renders product.cover.media.url; the sync uses getCover()->getMedia()->getUrl().
                // No thumbnails are created — both fall back to the original media URL.
                ['number' => 'PROD-COVER', 'name' => 'Product With Cover', 'stock' => 5, 'brand' => 'Brand C', 'price' => 29.99, 'seoPath' => 'product/product-cover', 'image' => 'https://assets.example.com/cover.jpg'],
                null,
                ['Name' => 'Product With Cover', 'Price' => 29.99, 'Stock' => 5, 'Brand' => 'Brand C', 'GroupCode' => 'PROD-COVER', 'Url' => 'product/product-cover', 'Image' => 'https://assets.example.com/cover.jpg'],
                true,
            ],

            'product with multiple media items uses cover not first image' => [
                // When a product has multiple media items, the image must come from the
                // designated cover — not the first media item in the collection.
                // cover: the cover media URL; otherImage: a non-cover media URL that must NOT appear.
                ['number' => 'PROD-MULTI-MEDIA', 'name' => 'Product Multi Media', 'stock' => 3, 'brand' => 'Brand M', 'price' => 39.99, 'seoPath' => 'product/product-multi-media', 'image' => 'https://assets.example.com/cover-not-first.jpg', 'otherImage' => 'https://assets.example.com/other-image.jpg'],
                null,
                ['Name' => 'Product Multi Media', 'Price' => 39.99, 'Stock' => 3, 'Brand' => 'Brand M', 'GroupCode' => 'PROD-MULTI-MEDIA', 'Url' => 'product/product-multi-media', 'Image' => 'https://assets.example.com/cover-not-first.jpg'],
                true,
            ],

            'product without cover image has no image in output' => [
                ['number' => 'PROD-NO-COVER', 'name' => 'Product No Cover', 'stock' => 4, 'brand' => 'Brand N', 'price' => 9.99, 'seoPath' => 'product/product-no-cover'],
                null,
                ['Name' => 'Product No Cover', 'Price' => 9.99, 'Stock' => 4, 'Brand' => 'Brand N', 'GroupCode' => 'PROD-NO-COVER', 'Url' => 'product/product-no-cover', 'Image' => ''],
                true,
            ],

            'variant with own cover image uses variant cover' => [
                ['number' => 'VARIANT-OWN-COVER', 'name' => 'Variant With Cover', 'stock' => 2, 'brand' => 'Brand V', 'price' => 19.99, 'seoPath' => 'product/variant-own-cover', 'image' => 'https://assets.example.com/variant-cover.jpg'],
                ['number' => 'PARENT-OWN-COVER', 'name' => 'Parent With Cover', 'stock' => 0, 'brand' => 'Brand V', 'price' => 19.99, 'seoPath' => 'product/parent-own-cover', 'image' => 'https://assets.example.com/parent-cover.jpg'],
                ['Name' => 'Variant With Cover', 'Price' => 19.99, 'Stock' => 2, 'Brand' => 'Brand V', 'GroupCode' => 'PARENT-OWN-COVER', 'Url' => 'product/variant-own-cover', 'Image' => 'https://assets.example.com/variant-cover.jpg'],
                true,
            ],

            'variant without cover does not inherit parent cover' => [
                ['number' => 'VARIANT-NO-COVER', 'name' => 'Variant No Cover', 'stock' => 6, 'brand' => 'Brand W', 'price' => 12.50, 'seoPath' => 'product/variant-no-cover'],
                ['number' => 'PARENT-HAS-COVER', 'name' => 'Parent Has Cover', 'stock' => 0, 'brand' => 'Brand W', 'price' => 12.50, 'seoPath' => 'product/parent-has-cover', 'image' => 'https://assets.example.com/parent-only-cover.jpg'],
                ['Name' => 'Variant No Cover', 'Price' => 12.50, 'Stock' => 6, 'Brand' => 'Brand W', 'GroupCode' => 'PARENT-HAS-COVER', 'Url' => 'product/variant-no-cover', 'Image' => ''],
                true,
            ],

            'variant product, groupcode uses parent product number' => [
                ['number' => 'VARIANT-001', 'name' => 'Variant Name', 'stock' => 3, 'brand' => 'Brand E', 'price' => 24.99, 'seoPath' => 'product/variant-one'],
                ['number' => 'PARENT-001', 'name' => 'Parent Name', 'stock' => 0, 'brand' => 'Brand E', 'price' => 24.99, 'seoPath' => 'product/parent-one'],
                ['Name' => 'Variant Name', 'Price' => 24.99, 'Stock' => 3, 'Brand' => 'Brand E', 'GroupCode' => 'PARENT-001', 'Url' => 'product/variant-one'],
                true,
            ],

            'grouped, variant inherits manufacturer from parent' => [
                ['number' => 'VARIANT-002', 'name' => 'Grouped Variant', 'stock' => 4, 'price' => 34.99, 'seoPath' => 'product/grouped-variant'],
                ['number' => 'PARENT-002', 'name' => 'Grouped Parent', 'stock' => 0, 'brand' => 'Inherited Brand', 'price' => 34.99, 'seoPath' => 'product/grouped-parent'],
                ['Name' => 'Grouped Variant', 'Price' => 34.99, 'Stock' => 4, 'Brand' => 'Inherited Brand', 'GroupCode' => 'PARENT-002', 'Url' => 'product/grouped-variant'],
                true,
            ],

            'grouped, variant has own manufacturer — parent brand not used' => [
                ['number' => 'VARIANT-OWN-BRAND', 'name' => 'Variant With Own Brand', 'stock' => 5, 'brand' => 'Variant Brand', 'price' => 22.00, 'seoPath' => 'product/variant-own-brand'],
                ['number' => 'PARENT-OWN-BRAND', 'name' => 'Parent With Different Brand', 'stock' => 0, 'brand' => 'Parent Brand', 'price' => 22.00, 'seoPath' => 'product/parent-own-brand'],
                ['Name' => 'Variant With Own Brand', 'Price' => 22.00, 'Stock' => 5, 'Brand' => 'Variant Brand', 'GroupCode' => 'PARENT-OWN-BRAND', 'Url' => 'product/variant-own-brand'],
                true,
            ],

            'variant with zero stock reports own stock, not parent stock' => [
                ['number' => 'VARIANT-ZERO', 'name' => 'Zero Stock Variant', 'stock' => 0, 'brand' => 'Brand Z', 'price' => 15.00, 'seoPath' => 'product/zero-stock-variant'],
                ['number' => 'PARENT-ZERO', 'name' => 'Parent With Stock', 'stock' => 10, 'brand' => 'Brand Z', 'price' => 15.00, 'seoPath' => 'product/parent-with-stock'],
                ['Name' => 'Zero Stock Variant', 'Price' => 15.00, 'Stock' => 0, 'Brand' => 'Brand Z', 'GroupCode' => 'PARENT-ZERO', 'Url' => 'product/zero-stock-variant'],
                true,
            ],

            'variant with empty name inherits parent name' => [
                // emptyName: true instructs the integration test to write the variant with an
                // explicit empty name translation so the DAL falls back to the parent's name.
                ['number' => 'VARIANT-NO-NAME', 'name' => '', 'stock' => 1, 'brand' => 'Brand X', 'price' => 10.00, 'seoPath' => 'product/no-name-variant', 'emptyName' => true],
                ['number' => 'PARENT-NO-NAME', 'name' => 'Parent Name As Fallback', 'stock' => 0, 'brand' => 'Brand X', 'price' => 10.00, 'seoPath' => 'product/parent-no-name'],
                ['Name' => 'Parent Name As Fallback', 'Price' => 10.00, 'Stock' => 1, 'Brand' => 'Brand X', 'GroupCode' => 'PARENT-NO-NAME', 'Url' => 'product/no-name-variant'],
                true,
            ],

            'non-grouped, standalone product — groupcode omitted' => [
                ['number' => 'PROD-007', 'name' => 'Product Seven', 'stock' => 4, 'brand' => 'Brand H', 'price' => 9.00, 'seoPath' => 'product/product-seven'],
                null,
                ['Name' => 'Product Seven', 'Price' => 9.00, 'Stock' => 4, 'Brand' => 'Brand H', 'GroupCode' => '', 'Url' => 'product/product-seven'],
                false,
            ],

            'non-grouped, variant — groupcode omitted' => [
                ['number' => 'VARIANT-004', 'name' => 'Variant Seven', 'stock' => 2, 'brand' => 'Brand H', 'price' => 9.00, 'seoPath' => 'product/variant-seven'],
                ['number' => 'PARENT-007', 'name' => 'Parent Seven', 'stock' => 0, 'brand' => 'Brand H', 'price' => 9.00, 'seoPath' => 'product/parent-seven'],
                ['Name' => 'Variant Seven', 'Price' => 9.00, 'Stock' => 2, 'Brand' => 'Brand H', 'GroupCode' => '', 'Url' => 'product/variant-seven'],
                false,
            ],

            'non-grouped, variant without manufacturer inherits parent brand' => [
                ['number' => 'VARIANT-NON-GROUPED-INHERIT', 'name' => 'Non-Grouped Variant No Brand', 'stock' => 3, 'price' => 18.00, 'seoPath' => 'product/non-grouped-inherit'],
                ['number' => 'PARENT-NON-GROUPED', 'name' => 'Non-Grouped Parent', 'stock' => 0, 'brand' => 'Non-Grouped Parent Brand', 'price' => 18.00, 'seoPath' => 'product/non-grouped-parent'],
                ['Name' => 'Non-Grouped Variant No Brand', 'Price' => 18.00, 'Stock' => 3, 'Brand' => 'Non-Grouped Parent Brand', 'GroupCode' => '', 'Url' => 'product/non-grouped-inherit'],
                false,
            ],

            'grouped + displayParent=true: variant gets groupcode from parent' => [
                ['number' => 'VARIANT-GDP-001', 'name' => 'Grouped DisplayParent Variant', 'stock' => 5, 'brand' => 'GDP Brand', 'price' => 28.00, 'seoPath' => 'product/grouped-display-parent-variant'],
                ['number' => 'PARENT-GDP-001', 'name' => 'Display Parent', 'stock' => 0, 'brand' => 'GDP Brand', 'price' => 28.00, 'seoPath' => 'product/grouped-display-parent', 'listingMode' => 'displayParent'],
                ['Name' => 'Grouped DisplayParent Variant', 'Price' => 28.00, 'Stock' => 5, 'Brand' => 'GDP Brand', 'GroupCode' => 'PARENT-GDP-001', 'Url' => 'product/grouped-display-parent-variant'],
                true,
            ],

            'grouped + mainVariantId set: variant gets groupcode from parent' => [
                ['number' => 'VARIANT-GMV-001', 'name' => 'Grouped MainVariant Variant', 'stock' => 3, 'brand' => 'GMV Brand', 'price' => 45.00, 'seoPath' => 'product/grouped-main-variant'],
                ['number' => 'PARENT-GMV-001', 'name' => 'Main Variant Parent', 'stock' => 0, 'brand' => 'GMV Brand', 'price' => 45.00, 'seoPath' => 'product/grouped-main-variant-parent', 'listingMode' => 'mainVariantId'],
                ['Name' => 'Grouped MainVariant Variant', 'Price' => 45.00, 'Stock' => 3, 'Brand' => 'GMV Brand', 'GroupCode' => 'PARENT-GMV-001', 'Url' => 'product/grouped-main-variant'],
                true,
            ],

            'grouped + expand-variants: variant gets groupcode from parent' => [
                ['number' => 'VARIANT-GEV-001', 'name' => 'Grouped ExpandVariants Variant', 'stock' => 8, 'brand' => 'GEV Brand', 'price' => 19.50, 'seoPath' => 'product/grouped-expand-variants'],
                ['number' => 'PARENT-GEV-001', 'name' => 'Expand Variants Parent', 'stock' => 0, 'brand' => 'GEV Brand', 'price' => 19.50, 'seoPath' => 'product/grouped-expand-variants-parent', 'listingMode' => 'expandVariants'],
                ['Name' => 'Grouped ExpandVariants Variant', 'Price' => 19.50, 'Stock' => 8, 'Brand' => 'GEV Brand', 'GroupCode' => 'PARENT-GEV-001', 'Url' => 'product/grouped-expand-variants'],
                true,
            ],

            'non-grouped, mainVariant synced with parent — no groupcode, brand from parent' => [
                ['number' => 'VARIANT-MAIN', 'name' => 'Main Variant', 'stock' => 8, 'price' => 42.00, 'seoPath' => 'product/main-variant'],
                ['number' => 'PARENT-MAIN-VARIANT', 'name' => 'Parent Of Main Variant', 'stock' => 0, 'brand' => 'Main Variant Parent Brand', 'price' => 42.00, 'seoPath' => 'product/parent-main-variant', 'listingMode' => 'mainVariantId'],
                ['Name' => 'Main Variant', 'Price' => 42.00, 'Stock' => 8, 'Brand' => 'Main Variant Parent Brand', 'GroupCode' => '', 'Url' => 'product/main-variant'],
                false,
            ],
        ];
    }

    public static function createFrontend(): FrontendEntity
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

    public static function createProduct(array $opts): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setProductNumber($opts['number']);
        $product->setTranslated(['name' => $opts['name'] ?? '']);
        $product->setAvailableStock($opts['stock'] ?? 0);

        if (isset($opts['brand'])) {
            $manufacturer = new ProductManufacturerEntity();
            $manufacturer->setId(Uuid::randomHex());
            $manufacturer->setTranslated(['name' => $opts['brand']]);
            $product->setManufacturer($manufacturer);
        }

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setIsCanonical(true);
        $seoUrl->setSeoPathInfo($opts['seoPath'] ?? 'product/' . $opts['number']);
        $product->setSeoUrls(new SeoUrlCollection([$seoUrl]));

        $basePrice = new CalculatedPrice(
            $opts['price'] ?? 0.0,
            $opts['price'] ?? 0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );
        $product->setCalculatedPrice($basePrice);

        $calculatedPrices = new CalculatedPriceCollection();
        if (!empty($opts['tieredPrices'])) {
            foreach ($opts['tieredPrices'] as $qty => $unitPrice) {
                $calculatedPrices->add(new CalculatedPrice(
                    $unitPrice,
                    $unitPrice,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection(),
                    $qty + 1
                ));
            }
        }
        $product->assign(['calculatedPrices' => $calculatedPrices]);

        if (isset($opts['originalUrl'])) {
            $thumbnails = new MediaThumbnailCollection();
            foreach (($opts['thumbnails'] ?? []) as $url => $width) {
                $thumb = new MediaThumbnailEntity();
                $thumb->setId(Uuid::randomHex());
                $thumb->setWidth($width);
                $thumb->setUrl($url);
                $thumbnails->add($thumb);
            }

            $media = new MediaEntity();
            $media->setId(Uuid::randomHex());
            $media->setUrl($opts['originalUrl']);
            $media->setThumbnails($thumbnails);

            $cover = new ProductMediaEntity();
            $cover->setId(Uuid::randomHex());
            $cover->setMedia($media);
            $product->setCover($cover);
        }

        $product->assign([
            'categories' => new CategoryCollection(),
            'streams'    => new ProductStreamCollection(),
        ]);

        return $product;
    }
}
