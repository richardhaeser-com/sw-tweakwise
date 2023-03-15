<?php declare(strict_types=1);

namespace RH\Tweakwise\Controller;

use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Controller\StorefrontController;
use Swag\PayPal\RestApi\V1\Api\Payment\Transaction\RelatedResource\Sale;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use function dd;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class TweakwiseFeedController extends StorefrontController
{
    private EntityRepository $salesChannelRepository;
    private EntityRepository $categoryRepository;
    private Context $context;
    private array $categoryData = [];
    private array $categoryMapping = [];

    private array $productData = [];
    private FeedService $feedService;

    public function __construct(EntityRepository $salesChannelRepository, EntityRepository $categoryRepository, FeedService $feedService)
    {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->categoryRepository = $categoryRepository;
        $this->context = Context::createDefaultContext();
        $this->feedService = $feedService;
    }

    /**
     * @Route("/tweakwise/feed2.xml", name="storefront.tweakwise.feed2", methods={"GET"})
     */
    public function feed2(): Response
    {
        $content = $this->feedService->generateFeed();
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/xml;charset=UTF-8');
        return $response;
    }

    /**
     * @Route("/tweakwise/feed.xml", name="storefront.tweakwise.feed", methods={"GET"})
     */
    public function feed(): Response
    {
        //@todo check if static file exists and is not to old

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociations(['language', 'languages', 'currency', 'currencies', 'domains', 'type', 'customFields', 'customField']);
        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, $this->context)->getEntities();
        $this->defineCategories($salesChannels);

        $response = $this->render('@Storefront/tweakwise/feed.xml.twig', [
            'categoryData' => $this->categoryData,
            'categoryMapping' => $this->categoryMapping
        ]);
        $response->headers->set('Content-Type', 'text/xml;charset=UTF-8');

        dd($response->getContent());
        return $response;
    }

}
