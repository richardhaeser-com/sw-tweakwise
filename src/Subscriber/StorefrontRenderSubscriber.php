<?php declare(strict_types=1);

namespace RH\Tweakwise\Subscriber;

use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Twig\ErrorTemplateStruct;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\Product\ProductPage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class StorefrontRenderSubscriber implements EventSubscriberInterface
{
    private EntityRepository $frontendRepository;
    private NavigationLoader $navigationLoader;
    private ProductDataService $productDataService;
    private RequestStack $requestStack;

    public function __construct(
        EntityRepository $frontendRepository,
        NavigationLoader $navigationLoader,
        ProductDataService $productDataService,
        RequestStack $requestStack
    ) {
        $this->frontendRepository = $frontendRepository;
        $this->navigationLoader = $navigationLoader;
        $this->productDataService = $productDataService;
        $this->requestStack = $requestStack;
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
        foreach ($navigationTree->getTree() as $treeItem) {
            $this->parseCategoryData($categoryData, $domainId, $treeItem);
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $session = $request->getSession();
        $route = $request->attributes->get('_route');
        $profileKey = $session->get('tweakwise_profile_key');

        if (!$profileKey && $route !== 'payment.finalize.transaction') {
            $profileKey = Uuid::randomHex();
            $session->set('tweakwise_profile_key', $profileKey);
        }

        $twConfiguration = [
            'domainId' => $domainId,
            'profileKey' => $profileKey,
            'rootCategoryId' => $rootCategoryId,
            'instanceKey' => $result->getToken(),
            'integration' => $result->getIntegration(),
            'wayOfSearch' => $result->getWayOfSearch(),
            'eventTagEnabled' => $result->isEventTagEnabled(),
            'fullPathCid' => $result->isFullPathCid(),
            'products' => [
                'desktop' => $result->getProductsDesktop(),
                'tablet' => $result->getProductsTablet(),
                'mobile' => $result->getProductsMobile(),
            ],
            'paginationType' => $result->getPaginationType(),
            'checkoutSales' => [
                'type' => $result->getCheckoutSales(),
                'featuredProductsId' => $result->getCheckoutSalesFeaturedProductsId(),
                'recommendationsGroupKey' => $result->getCheckoutSalesRecommendationsGroupKey(),
            ],
            'categoryData' => $categoryData
        ];

        $parameters = $event->getParameters();
        $page = $parameters['page'] ?? null;
        if ($page instanceof ProductPage) {
            $product = $this->productDataService->getProductShownInListing($page->getProduct(), $event->getSalesChannelContext());

            $twConfiguration['crossSellProductId'] = ProductDataService::getTweakwiseProductId($product, $domainId);
        }
        if ($page instanceof Page || $page instanceof ErrorTemplateStruct) {
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
