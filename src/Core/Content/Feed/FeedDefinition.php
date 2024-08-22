<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Feed;

use RH\Tweakwise\Core\Content\Aggregate\FeedSalesChannelDomain\FeedSalesChannelDomainDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;

class FeedDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 's_plugin_rhae_tweakwise_feed';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FeedEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FeedCollection::class;
    }

    public function getDefaults(): array
    {
        return [
            'name' => 'Main feed',
        ];
    }
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey(), new ApiAware()),
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('status', 'status'))->addFlags(new ApiAware()),
            (new StringField('interval', 'interval'))->addFlags(new Required(), new ApiAware()),
            (new StringField('type', 'type'))->addFlags(new Required(), new ApiAware()),
            (new BoolField('includeHiddenCategories', 'includeHiddenCategories'))->addFlags(new ApiAware()),
            (new DateTimeField('last_started_at', 'lastStartedAt'))->addFlags(new ApiAware()),
            (new DateTimeField('last_generated_at', 'lastGeneratedAt'))->addFlags(new ApiAware()),
            (new DateTimeField('next_generation_at', 'nextGenerationAt'))->addFlags(new ApiAware()),
            (new ManyToManyAssociationField('salesChannelDomains', SalesChannelDomainDefinition::class, FeedSalesChannelDomainDefinition::class, 'feed_id', 'sales_channel_domain_id'))->addFlags(new ApiAware()),
        ]);
    }
}
