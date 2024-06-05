<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Frontend;

use RH\Tweakwise\Core\Content\Aggregate\FrontendSalesChannelDomain\FrontendSalesChannelDomainDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

class FrontendDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 's_plugin_rhae_tweakwise_frontend';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FrontendEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FrontendCollection::class;
    }

    public function getDefaults(): array
    {
        return [
            'name' => 'Main frontend',
        ];
    }
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('token', 'token'))->addFlags(new ApiAware()),
            (new StringField('integration', 'integration'))->addFlags(new ApiAware()),
            (new StringField('wayOfSearch', 'wayOfSearch'))->addFlags(new ApiAware()),
            (new StringField('checkoutSales', 'checkoutSales'))->addFlags(new ApiAware()),
            (new IntField('productsDesktop', 'productsDesktop'))->addFlags(new ApiAware()),
            (new IntField('productsTablet', 'productsTablet'))->addFlags(new ApiAware()),
            (new IntField('productsMobile', 'productsMobile'))->addFlags(new ApiAware()),
            (new StringField('paginationType', 'paginationType'))->addFlags(new ApiAware()),
            (new StringField('checkoutSalesFeaturedProductsId', 'checkoutSalesFeaturedProductsId'))->addFlags(new ApiAware()),
            (new StringField('checkoutSalesRecommendationsGroupKey', 'checkoutSalesRecommendationsGroupKey'))->addFlags(new ApiAware()),
            (new ManyToManyAssociationField('salesChannelDomains', SalesChannelDomainDefinition::class, FrontendSalesChannelDomainDefinition::class, 'frontend_id', 'sales_channel_domain_id'))->addFlags(new ApiAware()),
        ]);
    }
}
