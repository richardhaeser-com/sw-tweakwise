<?php
namespace RH\Tweakwise\Helper;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function count;
use function is_array;

class FeedHelper
{
    private ContainerInterface $container;
    private SystemConfigService $config;

    public function __construct(ContainerInterface $container, SystemConfigService $systemConfigService)
    {
        $this->container = $container;
        $this->config = $systemConfigService;
    }

    public function getFeeds(Criteria $criteria, SalesChannelContext $salesChannelContext, ?string $sortOrder)
    {
        $repo = $this->container->get('s_plugin_rhae_tweakwise.repository');

        $criteria->addSorting(new FieldSorting(
                'name', $sortOrder == 'asc' ? FieldSorting::ASCENDING : FieldSorting::DESCENDING)
        );

        return $repo->search($criteria, $salesChannelContext->getContext());
    }
}
