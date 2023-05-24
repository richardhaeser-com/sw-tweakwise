<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Feed;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void            add(FeedEntity $entity)
 * @method void            set(string $key, FeedEntity $entity)
 * @method FeedEntity[]    getIterator()
 * @method FeedEntity[]    getElements()
 * @method FeedEntity|null get(string $key)
 * @method FeedEntity|null first()
 * @method FeedEntity|null last()
 */
class FeedCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FeedEntity::class;
    }
}
