<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductCrossSellingEntityExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new FkField(
            'product_cross_selling_tweakwise_id',
            'tweakwiseId',
            ProductCrossSellingTweakwiseEntityDefinition::class
        ));

        $collection->add(
            (new OneToOneAssociationField(
                'tweakwise',
                'product_cross_selling_tweakwise_id',
                'id',
                ProductCrossSellingTweakwiseEntityDefinition::class
            ))
        );
    }
    public function getDefinitionClass(): string
    {
        return ProductCrossSellingDefinition::class;
    }
    public function getEntityName(): string
    {
        return ProductCrossSellingDefinition::ENTITY_NAME;
    }

}
