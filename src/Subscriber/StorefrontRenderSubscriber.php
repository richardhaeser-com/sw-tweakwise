<?php declare(strict_types=1);

namespace RH\Tweakwise\Subscriber;

use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\Product\ProductPage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function array_key_exists;
use function crc32;
use function json_encode;
use function sprintf;
use function version_compare;

class StorefrontRenderSubscriber implements EventSubscriberInterface
{
    private EntityRepository $frontendRepository;
    private NavigationLoader $navigationLoader;
    private EntityRepository $productRepository;
    private string $shopwareVersion;

    public function __construct(
        EntityRepository $frontendRepository,
        EntityRepository $productRepository,
        NavigationLoader $navigationLoader,
        string $shopwareVersion,
    )
    {
        $this->frontendRepository = $frontendRepository;
        $this->navigationLoader = $navigationLoader;
        $this->productRepository = $productRepository;
        $this->shopwareVersion = $shopwareVersion;
    }

    public static function getSubscribedEvents()
    {
        return [
            StorefrontRenderEvent::class => 'getTweakwiseConfig',
        ];
    }

    public function getTweakwiseConfig(StorefrontRenderEvent $event): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('salesChannelDomains.id', $event->getSalesChannelContext()->getDomainId())
        );

        /** @var ?FrontendEntity $result */
        $result = $this->frontendRepository->search($criteria, $event->getContext())->first();
        if ($result === null) {
            return;
        }

        $domainId = $event->getSalesChannelContext()->getDomainId();
        $rootCategoryId = $event->getSalesChannelContext()->getSalesChannel()->getNavigationCategoryId();

        $categoryData = [];
        $navigationTree = $this->navigationLoader->load($rootCategoryId, $event->getSalesChannelContext(), $rootCategoryId, 99);
        foreach ($navigationTree->getTree() as $treeItem)
        {
            $this->parseCategoryData($categoryData, $domainId, $treeItem);
        }

        $twConfiguration = [
            'domainId' => $domainId,
            'rootCategoryId' => $rootCategoryId,
            'instanceKey' => $result->getToken(),
            'integration' => $result->getIntegration(),
            'wayOfSearch' => $result->getWayOfSearch(),
            'categoryData' => $categoryData
        ];

        $parameters = $event->getParameters();
        $page = $parameters['page'] ?? null;
        if ($page instanceof ProductPage) {
            $product = $page->getProduct();

            if ($product instanceof ProductEntity) {
                if (version_compare($this->shopwareVersion, '6.5.0', '>=')) {
                    if ($product->getParentId()) {
                        $criteria = new Criteria([$product->getParentId()]);
                        /** @var ProductEntity $parentProduct */
                        $parentProduct = $this->productRepository->search($criteria, $event->getContext())->first();
                        /** @phpstan-ignore-next-line */
                        if ($parentProduct->getVariantListingConfig()->getDisplayParent()) {
                            $product = $parentProduct;
                        } elseif ($parentProduct->getVariantListingConfig()->getMainVariantId()) {
                            $product = $this->productRepository->search(
                                /** @phpstan-ignore-next-line */
                                new Criteria([$parentProduct->getVariantListingConfig()->getMainVariantId()]),
                                $event->getContext()
                            )->first();
                        } else {
                            $criteria = new Criteria();
                            $criteria->addFilter(
                                new EqualsFilter('parentId', $product->getParentId())
                            );
                            /** @phpstan-ignore-next-line */
                            $product = $this->productRepository->search($criteria, $event->getContext())->first();
                        }
                    }
                    /** @phpstan-ignore-next-line */
                    $productNumber = $product->getProductNumber();
                    $twConfiguration['crossSellProductId'] = sprintf('%s (%s - %x)', $productNumber, $event->getRequest()->getLocale(), crc32($domainId));
                }
            }
        }
        if ($page instanceof Page) {
            $page->addExtensions([
                'twConfiguration' => new ArrayStruct($twConfiguration),
            ]);
        }
    }

    private function parseCategoryData(&$categoryData, $domainId, TreeItem $treeItem): void
    {
        $category = $treeItem->getCategory();
        $id = md5($category->getId() . '_' . $domainId);
        $categoryData[$id] = $category->getId();

        if ($treeItem->getChildren()) {
            foreach ($treeItem->getChildren() as $child) {
                $this->parseCategoryData($categoryData, $domainId, $child);
            }
        }
    }
}
