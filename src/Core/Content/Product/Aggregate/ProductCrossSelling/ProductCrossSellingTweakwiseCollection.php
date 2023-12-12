<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Product\Aggregate\ProductCrossSelling;

use RH\Tweakwise\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingTweakwiseEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(ProductCrossSellingTweakwiseEntity $entity)
 * @method void                      set(string $key, ProductCrossSellingTweakwiseEntity $entity)
 * @method ProductCrossSellingTweakwiseEntity[]    getIterator()
 * @method ProductCrossSellingTweakwiseEntity[]    getElements()
 * @method ProductCrossSellingTweakwiseEntity|null get(string $key)
 * @method ProductCrossSellingTweakwiseEntity|null first()
 * @method ProductCrossSellingTweakwiseEntity|null last()
 */
class ProductCrossSellingTweakwiseCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductCrossSellingTweakwiseEntity::class;
    }
}
