<?php declare(strict_types=1);

namespace RH\Tweakwise\Subscriber;

use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function array_key_exists;

class StorefrontRenderSubscriber implements EventSubscriberInterface
{
    private EntityRepository $frontendRepository;

    public function __construct(EntityRepository $frontendRepository)
    {
        $this->frontendRepository = $frontendRepository;
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

        $twConfiguration = [
            'domainId' => $domainId,
            'rootCategoryId' => $rootCategoryId,
            'instanceKey' => $result->getToken(),
            'integration' => $result->getIntegration(),
            'wayOfSearch' => $result->getWayOfSearch(),
        ];

        $parameters = $event->getParameters();
        $page = $parameters['page'] ?? null;
        if ($page instanceof Page) {
            $page->addExtensions([
                'twConfiguration' => new ArrayStruct($twConfiguration),
            ]);
        }
    }
}
