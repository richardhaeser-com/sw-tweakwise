<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;

class TweakwiseProductListingCmsElementResolver extends ProductListingCmsElementResolver
{
    private ProductListingCmsElementResolver $elementResolver;

    public function __construct(ProductListingCmsElementResolver $elementResolver, AbstractProductListingRoute $listingRoute)
    {
        $this->elementResolver = $elementResolver;

        parent::__construct($listingRoute);
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getConfig();
        $boxLayout = $config['boxLayout']['value'] ?: '';

        if ($boxLayout !== 'tweakwise') {
            $this->elementResolver->enrich($slot, $resolverContext, $result);
        } else {
            $data = new ProductListingStruct();
            $slot->setData($data);
        }
    }

}
