<?php declare(strict_types=1);

namespace RH\Tweakwise\Controller;

use RH\Tweakwise\Api\BackendApi;
use RH\Tweakwise\Api\FrontendApi;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route(defaults: ['_routeScope' => ['administration']])]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityRepository $frontendRepository,
        private EntityRepository $productRepository,
        private EntityRepository $propertyGroupRepository,
        private EntityRepository $customFieldSetRepository,
        private readonly AbstractProductPriceCalculator $calculator,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private RouterInterface $router,
        private readonly RequestStack $requestStack,
        private EntityRepository $feedRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
    ) {
    }
    #[Route('/api/_action/rhae-tweakwise/check-possibilities/{token}', name: 'rhae.tweakwise.check_possibilities', methods: ['GET'])]
    public function checkFieldVisible(string $token): JsonResponse
    {
        $frontendApi = new FrontendApi($token);
        $instanceData = $frontendApi->getInstance();
        $validToken = $instanceData['validToken'] ?: false;
        $features = [];
        if (array_key_exists('features', $instanceData)) {
            foreach ($instanceData['features'] as $featureLine) {
                $features[$featureLine['name']] = $featureLine['value'];
            }
        }

        return new JsonResponse([
            'validToken' => $validToken,
            'features' => $features,
            'token' => $token
        ]);
    }
    #[Route('/api/_action/rhae-tweakwise/sync-options', name: 'rhae.tweakwise.sync_options', methods: ['GET'])]
    public function syncOptions(Context $context): JsonResponse
    {
        $main = ['name' => 'name', 'unitPrice' => 'unitPrice', 'availableStock' => 'availableStock', 'manufacturer' => 'manufacturer', 'url' => 'url', 'images' => 'images', 'categories' => 'categories', 'groupcode' => 'groupcode'];
        $properties = [];
        $propertyGroups = $this->propertyGroupRepository->search(new Criteria(), $context);
        /** @var PropertyGroupEntity $propertyGroup */
        foreach ($propertyGroups as $propertyGroup) {
            $properties[$propertyGroup->getId()] = $propertyGroup->getName();
        }

        $customFields = [];
        $criteria = new Criteria();
        $criteria->addAssociation('relations');
        $criteria->addAssociation('customFields');
        $criteria->addFilter(new EqualsFilter('relations.entityName', 'product'));
        $customFieldsetObjects = $this->customFieldSetRepository->search($criteria, $context);
        /** @var CustomFieldSetEntity $customFieldset */
        foreach ($customFieldsetObjects as $customFieldset) {
            foreach ($customFieldset->getCustomFields() as $customField) {
                $customFields[$customField->getName()] = (reset($customField->getConfig()['label']) . ' (' . reset($customFieldset->getConfig()['label']) . ')');
            }
        }
        return new JsonResponse([
            'main' => $main,
            'properties' => $properties,
            'customFields' => $customFields,
        ]);
    }

    #[Route('/api/_action/rhae-tweakwise/check-data/{frontendId}/{productId}', name: 'rhae.tweakwise.check_data', methods: ['GET'])]
    public function getTweakwiseProductData(string $frontendId, string $productId, Context $context): JsonResponse
    {
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        if (!$product instanceof ProductEntity) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productId, 'error' => true, 'message' => 'Product not found.']);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $frontendId));
        $criteria->addAssociation('salesChannelDomains');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productId, 'product' => $product, 'error' => true, 'message' => 'Frontend not found.']);
        }

        $productIdHash = ProductDataService::getTweakwiseProductId($product, $frontend->getSalesChannelDomains()->first()->getId());
        if (!$frontend->getAccessToken()) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'error' => true, 'message' => 'No access token.']);
        }

        $backendApi = new BackendApi($frontend->getToken(), $frontend->getAccessToken(), $this->router);
        $productData = $backendApi->getProductData($product, $frontend->getSalesChannelDomains()->first()->getId());

        if (array_key_exists('error', $productData) && $productData['error']) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'error' => true, 'code' => $productData['code'], 'message' => $productData['message']]);
        }
        return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'productData' => $productData]);
    }
    #[Route('/api/_action/rhae-tweakwise/sync-data/{frontendId}/{productId}', name: 'rhae.tweakwise.sync_data', methods: ['GET'])]
    public function syncTweakwiseProductData(string $frontendId, string $productId, Context $context): JsonResponse
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('options');
        $criteria->addAssociation('properties');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('seoUrls');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('cover.media.thumbnails');
        $criteria->addAssociation('categories');
        $criteria->addAssociation('streams');
        $criteria->addAssociation('streams.categories');
        $product = $this->productRepository->search($criteria, $context)->first();
        if (!$product instanceof ProductEntity) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productId, 'error' => true, 'message' => 'Product not found.']);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $frontendId));
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productId, 'product' => $product, 'error' => true, 'message' => 'Frontend not found.']);
        }

        $productIdHash = ProductDataService::getTweakwiseProductId($product, $frontend->getSalesChannelDomains()->first()->getId());
        if (!$frontend->getAccessToken()) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'error' => true, 'message' => 'No access token.']);
        }

        $feedId = $this->requestStack->getCurrentRequest()->query->get('feedId');
        $feed = $this->resolveApplicableFeed($frontend, $feedId, $context);

        if ($feed === 'needs_selection') {
            $domainIds = $frontend->getSalesChannelDomains()->getIds();
            $feedCriteria = new Criteria();
            $feedCriteria->addFilter(new EqualsAnyFilter('salesChannelDomains.id', array_values($domainIds)));
            $feeds = $this->feedRepository->search($feedCriteria, $context)->getElements();
            return new JsonResponse([
                'needsFeedSelection' => true,
                'feeds' => array_values(array_map(fn (FeedEntity $f) => ['id' => $f->getId(), 'name' => $f->getName()], $feeds)),
            ]);
        }

        $shouldSyncVariants = false;
        $shouldSyncMainVariantId = null;
        $salesChannelId = $frontend->getSalesChannelDomains()->first()->getSalesChannel()->getId();
        if ($feed instanceof FeedEntity) {
            if ($feed->isGroupedProducts() && $product->getChildCount() > 0) {
                $syncVariants = $this->requestStack->getCurrentRequest()->query->getBoolean('syncVariants');
                if (!$syncVariants) {
                    return new JsonResponse(['error' => true, 'code' => 'PARENT_NOT_IN_GROUPED_FEED']);
                }
                $shouldSyncVariants = true;
            }
            if ($feed->isExcludeChildren() && $product->getParentId()) {
                return new JsonResponse(['error' => true, 'code' => 'CHILD_EXCLUDED_FROM_FEED']);
            }
            // The feed uses ProductAvailableFilter which requires active=true and a visibility
            // record for the sales channel. Syncing a product that is excluded from the feed
            // would create a ghost product in Tweakwise. Use the same filter to check eligibility.
            if (!$shouldSyncVariants && !$this->isProductInFeed($product->getId(), $salesChannelId, $context)) {
                return new JsonResponse(['error' => true, 'code' => 'PRODUCT_NOT_IN_FEED']);
            }
            if (!$feed->isGroupedProducts() && $product->getChildCount() > 0) {
                $listingConfig = $product->getVariantListingConfig();
                if ($listingConfig === null || $listingConfig->getDisplayParent() !== true) {
                    $mainVariantId = $listingConfig?->getMainVariantId();
                    if ($mainVariantId) {
                        $syncMainVariant = $this->requestStack->getCurrentRequest()->query->getBoolean('syncMainVariant');
                        if (!$syncMainVariant) {
                            return new JsonResponse(['error' => true, 'code' => 'PARENT_HAS_MAIN_VARIANT']);
                        }
                        $shouldSyncMainVariantId = $mainVariantId;
                    } else {
                        $syncVariants = $this->requestStack->getCurrentRequest()->query->getBoolean('syncVariants');
                        if (!$syncVariants) {
                            return new JsonResponse(['error' => true, 'code' => 'PARENT_USES_VARIANT_LISTING']);
                        }
                        $shouldSyncVariants = true;
                    }
                }
            }
        }

        $parent = null;
        if ($product->getParentId()) {
            $criteria = new Criteria([$product->getParentId()]);
            $criteria->addAssociation('manufacturer');
            $parent = $this->productRepository->search($criteria, $context)->first();
        }
        $backendApi = new BackendApi($frontend->getToken(), $frontend->getAccessToken(), $this->router);

        $salesChannelDomain = $frontend->getSalesChannelDomains()->first();
        $salesChannel = $salesChannelDomain->getSalesChannel();
        $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $salesChannelDomain->getLanguageId()]);

        $product->assign([
            'calculatedPrices' => new PriceCollection(),
            'calculatedListingPrice' => null,
            'calculatedPrice' => null,
            'cheapestPrice' => null,
        ]);
        $this->calculator->calculate(new ProductCollection([$product]), $salesChannelContext);

        if ($parent) {
            $parent->assign([
                'calculatedPrices' => new PriceCollection(),
                'calculatedListingPrice' => null,
                'calculatedPrice' => null,
                'cheapestPrice' => null,
            ]);
            $this->calculator->calculate(new ProductCollection([$parent]), $salesChannelContext);
        }

        $criteria = new Criteria();
        $criteria->addAssociation('relations');
        $criteria->addAssociation('customFields');
        $criteria->addFilter(new EqualsFilter('relations.entityName', 'product'));
        $customFieldsetObjects = $this->customFieldSetRepository->search($criteria, $context);

        $customFieldNames = [];
        /** @var CustomFieldSetEntity $customFieldsetObject */
        foreach ($customFieldsetObjects as $customFieldsetObject) {
            /** @var CustomFieldEntity $customField */
            foreach ($customFieldsetObject->getCustomFields() as $customField) {
                $customFieldNames[$customField->getName()] = reset($customField->getConfig()['label']);
            }
        }
        $groupedProducts = $feed instanceof FeedEntity ? $feed->isGroupedProducts() : true;

        if ($shouldSyncVariants) {
            $variantCriteria = new Criteria();
            $variantCriteria->addFilter(new EqualsFilter('parentId', $product->getId()));
            // Only sync variants that would appear in the feed — same filter as FeedService.
            // Use inheritance-aware context so variants correctly inherit active/visibility from parent.
            $variantCriteria->addFilter(new ProductAvailableFilter($salesChannelId, ProductVisibilityDefinition::VISIBILITY_LINK));
            $variantCriteria->addAssociation('manufacturer');
            $variantCriteria->addAssociation('options');
            $variantCriteria->addAssociation('properties');
            $variantCriteria->addAssociation('properties.group');
            $variantCriteria->addAssociation('seoUrls');
            $variantCriteria->addAssociation('cover');
            $variantCriteria->addAssociation('cover.media');
            $variantCriteria->addAssociation('cover.media.thumbnails');
            $variantCriteria->addAssociation('categories');
            $variantCriteria->addAssociation('streams');
            $variantCriteria->addAssociation('streams.categories');

            if ($feed instanceof FeedEntity
                && $feed->isRespectHideCloseoutProductsWhenOutOfStock()
                && $this->systemConfigService->getBool('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelContext->getSalesChannelId())
            ) {
                $variantCriteria->addFilter($this->productCloseoutFilterFactory->create($salesChannelContext));
            }

            $inheritanceContext = clone $context;
            $inheritanceContext->setConsiderInheritance(true);
            $variants = $this->productRepository->search($variantCriteria, $inheritanceContext);

            $variantCollection = new ProductCollection($variants->getElements());
            foreach ($variantCollection as $variant) {
                $variant->assign([
                    'calculatedPrices' => new PriceCollection(),
                    'calculatedListingPrice' => null,
                    'calculatedPrice' => null,
                    'cheapestPrice' => null,
                ]);
            }
            $this->calculator->calculate($variantCollection, $salesChannelContext);

            $syncedCount = 0;
            foreach ($variants as $variant) {
                $result = $backendApi->syncProductData($variant, $frontend, $product, $customFieldNames, $groupedProducts);
                if (!($result['error'] ?? false)) {
                    $syncedCount++;
                }
            }

            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'updated' => true, 'syncedVariants' => $syncedCount]);
        }

        if ($shouldSyncMainVariantId !== null) {
            $mainVariantCriteria = new Criteria([$shouldSyncMainVariantId]);
            $mainVariantCriteria->addAssociation('manufacturer');
            $mainVariantCriteria->addAssociation('options');
            $mainVariantCriteria->addAssociation('properties');
            $mainVariantCriteria->addAssociation('properties.group');
            $mainVariantCriteria->addAssociation('seoUrls');
            $mainVariantCriteria->addAssociation('cover');
            $mainVariantCriteria->addAssociation('cover.media');
            $mainVariantCriteria->addAssociation('cover.media.thumbnails');
            $mainVariantCriteria->addAssociation('categories');
            $mainVariantCriteria->addAssociation('streams');
            $mainVariantCriteria->addAssociation('streams.categories');
            $mainVariant = $this->productRepository->search($mainVariantCriteria, $context)->first();
            if (!$mainVariant instanceof ProductEntity) {
                return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'error' => true, 'message' => 'Main variant not found.']);
            }
            $mainVariant->assign([
                'calculatedPrices' => new PriceCollection(),
                'calculatedListingPrice' => null,
                'calculatedPrice' => null,
                'cheapestPrice' => null,
            ]);
            $this->calculator->calculate(new ProductCollection([$mainVariant]), $salesChannelContext);

            $response = $backendApi->syncProductData($mainVariant, $frontend, $product, $customFieldNames, false);
            if (array_key_exists('error', $response) && $response['error']) {
                return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'error' => true, 'code' => $response['code'] ?? null, 'message' => $response['message'] ?? null]);
            }
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'updated' => true]);
        }

        $response = $backendApi->syncProductData($product, $frontend, $parent, $customFieldNames, $groupedProducts);

        if (array_key_exists('error', $response) && $response['error']) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'error' => true, 'code' => $response['code'], 'message' => $response['message']]);
        }
        return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'updated' => true]);
    }

    #[Route('/api/_action/rhae-tweakwise/categoryTree', name: 'rhae.tweakwise.category_tree', methods: ['GET'])]
    public function getCategoryTree(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('accessToken', null),
                    new EqualsFilter('accessToken', ''),
                ]
            )
        );
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse([]);
        }

        $frontendApi = new FrontendApi($frontend->getToken());
        $data = $frontendApi->getCategoryTree();
        $categoryTree = [];
        if (!array_key_exists('error', $data)) {
            foreach ($data as $categoryId => $categoryName) {
                $categoryTree[] = ['value' => $categoryId, 'label' => $categoryName];
            }
            return new JsonResponse($categoryTree);

        }
        return new JsonResponse([]);
    }

    #[Route('/api/_action/rhae-tweakwise/filterTemplates', name: 'rhae.tweakwise.filter_templates', methods: ['GET'])]
    public function getFilterTemplates(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('accessToken', null),
                    new EqualsFilter('accessToken', ''),
                ]
            )
        );
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse([]);
        }

        $frontendApi = new FrontendApi($frontend->getToken());
        $data = $frontendApi->getFilterTemplates();
        $filterTemplates = [];
        if (!array_key_exists('error', $data)) {
            foreach ($data as $filterTemplate) {
                $filterTemplates[] = ['value' => $filterTemplate['templateid'], 'label' => $filterTemplate['name']];
            }
            return new JsonResponse($filterTemplates);

        }
        return new JsonResponse([]);
    }

    #[Route('/api/_action/rhae-tweakwise/filterAttributes', name: 'rhae.tweakwise.filter_attributes', methods: ['GET'])]
    public function getFilterAttributes(Context $context): JsonResponse
    {
        $categoryId = $this->requestStack->getCurrentRequest()->query->get('categoryId');
        $filterTemplateId = $this->requestStack->getCurrentRequest()->query->get('filterTemplateId');

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('accessToken', null),
                    new EqualsFilter('accessToken', ''),
                ]
            )
        );
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse([]);
        }

        $fontendApi = new FrontendApi($frontend->getToken());
        $data = $fontendApi->getFacetsForCategory($categoryId, $filterTemplateId);
        $filterAttributes = [];
        if (!array_key_exists('error', $data)) {
            foreach ($data['facets'] ?: [] as $facet) {
                if (array_key_exists('facetsettings', $facet) && is_array($facet['facetsettings'])) {
                    if (strtolower($facet['facetsettings']['source']) === 'category') {
                        continue;
                    }
                    $filterAttributes[] = ['value' => $facet['facetsettings']['urlkey'], 'label' => $facet['facetsettings']['attributename']];
                }
            }

            return new JsonResponse($filterAttributes);

        }
        return new JsonResponse([]);
    }
    #[Route('/api/_action/rhae-tweakwise/filterAttributeValues', name: 'rhae.tweakwise.filter_attribute_values', methods: ['GET'])]
    public function getFilterAttributeValues(Context $context): JsonResponse
    {
        $urlKey = $this->requestStack->getCurrentRequest()->query->get('urlKey');
        $categoryId = $this->requestStack->getCurrentRequest()->query->get('categoryId');

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('accessToken', null),
                    new EqualsFilter('accessToken', ''),
                ]
            )
        );
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse([]);
        }

        $frontendApi = new FrontendApi($frontend->getToken());
        $data = $frontendApi->getAttributesForFacet($urlKey, $categoryId);

        $filterAttributeValues = [];
        if (!array_key_exists('error', $data)) {
            foreach ($data['attributes'] ?: [] as $attribute) {
                $filterAttributeValues[] = ['value' => $attribute['title'], 'label' => $attribute['title']];
            }

            return new JsonResponse($filterAttributeValues);

        }
        return new JsonResponse([]);
    }

    #[Route('/api/_action/rhae-tweakwise/sortTemplates', name: 'rhae.tweakwise.sort_templates', methods: ['GET'])]
    public function getSortTemplates(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('accessToken', null),
                    new EqualsFilter('accessToken', ''),
                ]
            )
        );
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse([]);
        }

        $frontendApi = new FrontendApi($frontend->getToken());
        $data = $frontendApi->getSortTemplates();
        $sortTemplates = [];
        if (!array_key_exists('error', $data)) {
            foreach ($data as $sortTemplate) {
                $sortTemplates[] = ['value' => $sortTemplate['templateid'], 'label' => $sortTemplate['name']];
            }
            return new JsonResponse($sortTemplates);

        }
        return new JsonResponse([]);
    }

    /**
     * Returns the feed to use for a sync, 'needs_selection' when multiple feeds match and no feedId is given, or null when none match.
     */
    private function resolveApplicableFeed(FrontendEntity $frontend, ?string $feedId, Context $context): FeedEntity|string|null
    {
        if ($feedId) {
            $feed = $this->feedRepository->search(new Criteria([$feedId]), $context)->first();
            return $feed instanceof FeedEntity ? $feed : null;
        }

        $domainIds = $frontend->getSalesChannelDomains()->getIds();
        if (empty($domainIds)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('salesChannelDomains.id', array_values($domainIds)));
        $feeds = $this->feedRepository->search($criteria, $context)->getElements();

        if (count($feeds) === 1) {
            $feed = array_values($feeds)[0];
            return $feed instanceof FeedEntity ? $feed : null;
        }

        if (count($feeds) > 1) {
            return 'needs_selection';
        }

        return null;
    }

    /**
     * Returns true if the product would appear in the feed for the given sales channel.
     * Uses ProductAvailableFilter with inheritance-aware context so that variants
     * correctly inherit active status and visibility from their parent.
     */
    private function isProductInFeed(string $productId, string $salesChannelId, Context $context): bool
    {
        $criteria = new Criteria([$productId]);
        $criteria->addFilter(new ProductAvailableFilter($salesChannelId, ProductVisibilityDefinition::VISIBILITY_LINK));

        $inheritanceContext = clone $context;
        $inheritanceContext->setConsiderInheritance(true);

        return $this->productRepository->searchIds($criteria, $inheritanceContext)->getTotal() > 0;
    }

    #[Route('/api/_action/rhae-tweakwise/builderTemplates', name: 'rhae.tweakwise.builder_templates', methods: ['GET'])]
    public function getBuilderTemplates(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('accessToken', null),
                    new EqualsFilter('accessToken', ''),
                ]
            )
        );
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse([]);
        }

        $frontendApi = new FrontendApi($frontend->getToken());
        $data = $frontendApi->getBuilderTemplates();

        $builderTemplates = [];
        if (!array_key_exists('error', $data)) {
            foreach ($data ?: [] as $builderTemplate) {
                $builderTemplates[] = ['value' => $builderTemplate['id'], 'label' => $builderTemplate['name']];
            }
            return new JsonResponse($builderTemplates);

        }
        return new JsonResponse([]);
    }
}
