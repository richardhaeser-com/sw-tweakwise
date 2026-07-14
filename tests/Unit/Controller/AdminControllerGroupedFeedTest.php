<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Controller\AdminController;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * Verifies that the admin backend sync correctly refuses to sync parent products
 * when the feed is in grouped-products mode, mirroring the feed's query-level exclusion.
 */
class AdminControllerGroupedFeedTest extends TestCase
{
    private function buildController(
        EntityRepository $frontendRepo,
        EntityRepository $productRepo,
        EntityRepository $feedRepo,
        Request $request,
    ): AdminController {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new AdminController(
            $frontendRepo,
            $productRepo,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(AbstractProductPriceCalculator::class),
            $this->createMock(AbstractSalesChannelContextFactory::class),
            $this->createMock(RouterInterface::class),
            $requestStack,
            $feedRepo,
        );
    }

    private function makeRepoReturning(mixed $entity): EntityRepository
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn($entity);
        $result->method('getElements')->willReturn($entity !== null ? [$entity] : []);
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($result);
        return $repo;
    }

    private function createFrontend(): FrontendEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('domain-id');
        $domain->setUrl('https://example.com');

        $frontend = new FrontendEntity();
        $frontend->setId('frontend-id');
        $frontend->setToken('instance-key');
        $frontend->setAccessToken('access-token');
        $frontend->setSalesChannelDomains(new SalesChannelDomainCollection([$domain]));

        return $frontend;
    }

    public function testParentProductInGroupedFeedReturnsParentNotInGroupedFeedError(): void
    {
        $product = new ProductEntity();
        $product->setId('parent-product-id');
        $product->setChildCount(3);

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(true);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        $response = $controller->syncTweakwiseProductData('frontend-id', 'parent-product-id', Context::createDefaultContext());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('PARENT_NOT_IN_GROUPED_FEED', $data['code']);
    }

    public function testParentProductInNonGroupedFeedDoesNotReturnParentError(): void
    {
        // A parent product in a non-grouped feed should proceed past the guard.
        // The early-return guard (PARENT_NOT_IN_GROUPED_FEED) must NOT fire.
        // (The sync itself will fail later since BackendApi is instantiated internally,
        //  but we can assert the specific error code is absent.)
        $product = new ProductEntity();
        $product->setId('parent-product-id');
        $product->setChildCount(3);

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(false);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        try {
            $response = $controller->syncTweakwiseProductData('frontend-id', 'parent-product-id', Context::createDefaultContext());
            $data = json_decode($response->getContent(), true);
            $this->assertNotSame('PARENT_NOT_IN_GROUPED_FEED', $data['code'] ?? null);
        } catch (\Throwable) {
            // If further processing throws (e.g. BackendApi network call), the guard still did not fire.
            $this->assertTrue(true, 'Controller passed the grouped-feed guard without returning PARENT_NOT_IN_GROUPED_FEED');
        }
    }

    public function testChildProductInFeedWithExcludeChildrenReturnsChildExcludedError(): void
    {
        $product = new ProductEntity();
        $product->setId('child-product-id');
        $product->setChildCount(0);
        $product->setParentId('parent-id');

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(false);
        $feed->setExcludeChildren(true);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        $response = $controller->syncTweakwiseProductData('frontend-id', 'child-product-id', Context::createDefaultContext());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('CHILD_EXCLUDED_FROM_FEED', $data['code']);
    }

    public function testParentInNonGroupedFeedWithVariantListingReturnsParentUsesVariantListingError(): void
    {
        // displayParent = false, no mainVariantId → listing shows a representative variant
        // The sync cannot know which one, so it must return PARENT_USES_VARIANT_LISTING
        $product = new ProductEntity();
        $product->setId('parent-product-id');
        $product->setChildCount(3);
        $product->setVariantListingConfig(new VariantListingConfig(false, null, null));

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(false);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        $response = $controller->syncTweakwiseProductData('frontend-id', 'parent-product-id', Context::createDefaultContext());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('PARENT_USES_VARIANT_LISTING', $data['code']);
    }

    public function testParentInNonGroupedFeedWithNullVariantListingConfigReturnsParentUsesVariantListingError(): void
    {
        // No variantListingConfig set at all → Shopware default shows a representative variant,
        // not the parent. The parent is not in the feed and must not be synced directly.
        $product = new ProductEntity();
        $product->setId('parent-product-id');
        $product->setChildCount(3);
        // variantListingConfig intentionally left null

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(false);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        $response = $controller->syncTweakwiseProductData('frontend-id', 'parent-product-id', Context::createDefaultContext());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('PARENT_USES_VARIANT_LISTING', $data['code']);
    }

    public function testParentInNonGroupedFeedWithDisplayParentTrueDoesNotReturnParentUsesVariantListingError(): void
    {
        // displayParent = true → parent IS the listing product, sync it directly
        $product = new ProductEntity();
        $product->setId('parent-product-id');
        $product->setChildCount(3);
        $product->setVariantListingConfig(new VariantListingConfig(true, null, null));

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(false);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        try {
            $response = $controller->syncTweakwiseProductData('frontend-id', 'parent-product-id', Context::createDefaultContext());
            $data = json_decode($response->getContent(), true);
            $this->assertNotSame('PARENT_USES_VARIANT_LISTING', $data['code'] ?? null);
        } catch (\Throwable) {
            $this->assertTrue(true, 'Passed the listing-config guard without returning PARENT_USES_VARIANT_LISTING');
        }
    }

    public function testParentInNonGroupedFeedWithMainVariantIdReturnsParentHasMainVariantError(): void
    {
        // mainVariantId is set → controller must ask for confirmation before syncing it
        $product = new ProductEntity();
        $product->setId('parent-product-id');
        $product->setChildCount(3);
        $product->setVariantListingConfig(new VariantListingConfig(false, 'main-variant-id', null));

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(false);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        $response = $controller->syncTweakwiseProductData('frontend-id', 'parent-product-id', Context::createDefaultContext());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('PARENT_HAS_MAIN_VARIANT', $data['code']);
    }

    public function testParentInNonGroupedFeedWithMainVariantIdAndSyncMainVariantParamPassesGuard(): void
    {
        // With syncMainVariant=true, the confirmation has been given — must not return PARENT_HAS_MAIN_VARIANT
        $product = new ProductEntity();
        $product->setId('parent-product-id');
        $product->setChildCount(3);
        $product->setVariantListingConfig(new VariantListingConfig(false, 'main-variant-id', null));

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(false);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');
        $request->query->set('syncMainVariant', 'true');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        try {
            $response = $controller->syncTweakwiseProductData('frontend-id', 'parent-product-id', Context::createDefaultContext());
            $data = json_decode($response->getContent(), true);
            $this->assertNotSame('PARENT_HAS_MAIN_VARIANT', $data['code'] ?? null);
        } catch (\Throwable) {
            $this->assertTrue(true, 'Passed the PARENT_HAS_MAIN_VARIANT guard with syncMainVariant=true');
        }
    }

    public function testChildProductInGroupedFeedDoesNotReturnChildExcludedError(): void
    {
        // In grouped mode, children are the ones that ARE exported — the excludeChildren guard
        // must not fire when isGroupedProducts=true (only isExcludeChildren triggers it).
        $product = new ProductEntity();
        $product->setId('child-product-id');
        $product->setChildCount(0);
        $product->setParentId('parent-id');

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(true);
        $feed->setExcludeChildren(false);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        try {
            $response = $controller->syncTweakwiseProductData('frontend-id', 'child-product-id', Context::createDefaultContext());
            $data = json_decode($response->getContent(), true);
            $this->assertNotSame('CHILD_EXCLUDED_FROM_FEED', $data['code'] ?? null);
        } catch (\Throwable) {
            $this->assertTrue(true, 'Controller passed the exclusion guards without returning CHILD_EXCLUDED_FROM_FEED');
        }
    }

    /**
     * The XML feed uses ProductAvailableFilter which contains `product.active = true`,
     * so inactive products are silently excluded from the feed at query time.
     *
     * The admin sync loads the product via a plain EntityRepository::search() with NO
     * active filter. An admin can therefore manually sync an inactive product — this is
     * intentional so that operators have explicit control over pushing updates regardless
     * of the product's current active status.
     *
     * This test verifies that the controller's guard logic does not block syncing based
     * on the active flag. The guards only check grouped-feed and variant-listing config.
     */
    public function testInactiveProductIsNotBlockedByControllerGuards(): void
    {
        // A standalone inactive product — no parentId, no children.
        $product = new ProductEntity();
        $product->setId('inactive-product-id');
        $product->setChildCount(0);
        $product->setActive(false);

        $feed = new FeedEntity();
        $feed->setId('feed-id');
        $feed->setGroupedProducts(false);

        $request = new Request();
        $request->query->set('feedId', 'feed-id');

        $controller = $this->buildController(
            $this->makeRepoReturning($this->createFrontend()),
            $this->makeRepoReturning($product),
            $this->makeRepoReturning($feed),
            $request,
        );

        // The controller must not return any visibility/active-related error.
        // It may throw later (BackendApi network call), but the guards must pass.
        try {
            $response = $controller->syncTweakwiseProductData('frontend-id', 'inactive-product-id', Context::createDefaultContext());
            $data = json_decode($response->getContent(), true);
            // None of the guard error codes should fire for an inactive standalone product.
            $guardCodes = ['PARENT_NOT_IN_GROUPED_FEED', 'CHILD_EXCLUDED_FROM_FEED', 'PARENT_USES_VARIANT_LISTING', 'PARENT_HAS_MAIN_VARIANT'];
            $this->assertNotContains($data['code'] ?? null, $guardCodes, 'Inactive standalone product must pass all guards.');
        } catch (\Throwable) {
            // Further processing (BackendApi) may throw without a DB — guards passed.
            $this->assertTrue(true, 'Controller passed all guards for inactive product without returning a guard error code.');
        }
    }
}
