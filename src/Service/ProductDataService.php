<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductDataService
{
    private EntityRepository $productRepository;
    private ProductListingLoader $listingLoader;

    public function __construct(EntityRepository $productRepository, ProductListingLoader $listingLoader)
    {
        $this->productRepository = $productRepository;
        $this->listingLoader = $listingLoader;
    }

    public function getProductFromProductNumber(string $productNumber, Context $context): ?ProductEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('productNumber', $productNumber)
        );

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->first();
        return $product;
    }

    public function getProductShownInListing(ProductEntity $product, SalesChannelContext $context): ProductEntity
    {
        $criteria = new Criteria();
        if (!$product->getParentId()) {
            $criteria->addFilter(
                new EqualsFilter('id', $product->getId())
            );
        } else {
            $criteria->addFilter(
                new EqualsFilter('parentId', $product->getParentId())
            );
        }
        $criteria->addAssociation('options');
        $criteria->addAssociation('options.group');
        $criteria->setOffset(0);
        $criteria->setLimit(1);

        $criteria->getAssociation('seoUrls')
            ->setLimit(1)
            ->addFilter(new EqualsFilter('isCanonical', true));

        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));

        $entities = $this->listingLoader->load($criteria, $context);
        $result = ProductListingResult::createFrom($entities);
        if ($result->getTotal() > 0) {
            $result->addState(...$entities->getStates());

            if ($result->first() instanceof ProductEntity) {
                return $result->first();
            }
        }

        return $product;
    }

    public static function getTweakwiseProductId(string $productNumber, string $locale, string $domainId)
    {
        return sprintf('%s (%s - %x)', $productNumber, $locale, crc32($domainId));
    }
}
