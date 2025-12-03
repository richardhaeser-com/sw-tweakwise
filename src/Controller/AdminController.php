<?php declare(strict_types=1);

namespace RH\Tweakwise\Controller;

use RH\Tweakwise\Api\BackendApi;
use RH\Tweakwise\Api\FrontendApi;
use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private RouterInterface $router
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
        $main = ['name' => 'name', 'unitPrice' => 'unitPrice', 'availableStock' => 'availableStock', 'manufacturer' => 'manufacturer', 'url' => 'url', 'images' => 'images', 'categories' => 'categories'];
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
        };
        $response = $backendApi->syncProductData($product, $frontend, $parent, $customFieldNames);

        if (array_key_exists('error', $response) && $response['error']) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'error' => true, 'code' => $response['code'], 'message' => $response['message']]);
        }
        return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'updated' => true]);
    }
}
