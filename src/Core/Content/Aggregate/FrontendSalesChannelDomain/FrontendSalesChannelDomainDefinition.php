<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Aggregate\FrontendSalesChannelDomain;

use RH\Tweakwise\Core\Content\Frontend\FrontendDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

class FrontendSalesChannelDomainDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 's_plugin_rhae_tweakwise_frontend_sales_channel_domains';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('frontend_id', 'frontendId', FrontendDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('sales_channel_domain_id', 'salesChannelDomainId', SalesChannelDomainDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new ManyToOneAssociationField('frontend', 'frontend_id', FrontendDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('salesChannelDomain', 'sales_channel_domain_id', SalesChannelDomainDefinition::class))->addFlags(new ApiAware()),
            (new CreatedAtField())->addFlags(new ApiAware())
        ]);
    }
}
