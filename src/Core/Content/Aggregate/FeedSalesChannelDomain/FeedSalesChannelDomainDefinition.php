<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Aggregate\FeedSalesChannelDomain;

use RH\Tweakwise\Core\Content\Feed\FeedDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

class FeedSalesChannelDomainDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 's_plugin_rhae_tweakwise_sales_channel_domains';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('feed_id', 'feedId', FeedDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('sales_channel_domain_id', 'salesChannelDomainId', SalesChannelDomainDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new ManyToOneAssociationField('feed', 'feed_id', FeedDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('salesChannelDomain', 'sales_channel_domain_id', SalesChannelDomainDefinition::class))->addFlags(new ApiAware()),
            (new CreatedAtField())->addFlags(new ApiAware())
        ]);
    }
}
