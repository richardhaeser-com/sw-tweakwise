<?php declare(strict_types=1);

namespace RH\Tweakwise\Twig;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Md5Extension extends AbstractExtension
{

    public function getFunctions(): array
    {
        return [
            new TwigFunction('md5', [$this, 'md5']),
        ];
    }


    public function getFilters(): array
    {
        return [
            new TwigFunction('md5', [$this, 'md5']),
        ];
    }

    public function md5(string $text): string
    {
        return md5($text);
    }
}
