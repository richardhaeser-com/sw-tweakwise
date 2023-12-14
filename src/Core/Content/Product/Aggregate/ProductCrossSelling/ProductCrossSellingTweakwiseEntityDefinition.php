<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class ProductCrossSellingTweakwiseEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product_cross_selling_tweakwise';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ProductCrossSellingTweakwiseCollection::class;
    }

    public function getEntityClass(): string
    {
        return ProductCrossSellingTweakwiseEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('group_key', 'groupKey')),
        ]);
    }
}
