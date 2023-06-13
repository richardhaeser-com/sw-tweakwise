<?php declare(strict_types=1);

namespace RH\Tweakwise\Controller;

use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class TweakwiseFeedController extends StorefrontController
{
    private FeedService $feedService;
    private EntityRepositoryInterface $feedRepository;

    public function __construct(FeedService $feedService, EntityRepositoryInterface $feedRepository)
    {
        $this->feedService = $feedService;
        $this->feedRepository = $feedRepository;
    }

    /**
     * @Route("/tweakwise/feed-{feedId}.xml", name="storefront.tweakwise.feed", methods={"GET"})
     */
    public function feed(Request $request, SalesChannelContext $context, $feedId): Response
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $feedId));

        try {
            $feed = $this->feedRepository->search($criteria, $context->getContext())->first();
        } catch (InvalidUuidException $e) {
            return new Response('Id is not valid', 404);
        }

        if (!$feed instanceof FeedEntity) {
            return new Response('No valid feed found with id ' . $feedId, 404);
        }

        $content = $this->feedService->readFeed($feed);
        if (!$content) {
            return new Response('Feed not found, please generate feed', 404);
        }
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/xml;charset=UTF-8');
        return $response;
    }
}
