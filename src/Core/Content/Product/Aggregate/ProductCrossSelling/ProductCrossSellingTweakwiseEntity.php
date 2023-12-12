<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductCrossSellingTweakwiseEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $groupKey;

    protected ?ProductCrossSellingEntity $productCrossSelling;

    public function getGroupKey(): ?string
    {
        return $this->groupKey;
    }

    public function setGroupKey(?string $groupKey): void
    {
        $this->groupKey = $groupKey;
    }

    public function getProductCrossSelling(): ?ProductCrossSellingEntity
    {
        return $this->productCrossSelling;
    }

    public function setProductCrossSelling(?ProductCrossSellingEntity $productCrossSelling): ProductCrossSellingTweakwiseEntity
    {
        $this->productCrossSelling = $productCrossSelling;

        return $this;
    }
}
