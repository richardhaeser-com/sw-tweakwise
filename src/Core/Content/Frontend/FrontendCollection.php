<?php declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Frontend;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void            add(FrontendEntity $entity)
 * @method void            set(string $key, FrontendEntity $entity)
 * @method FrontendEntity[]    getIterator()
 * @method FrontendEntity[]    getElements()
 * @method FrontendEntity|null get(string $key)
 * @method FrontendEntity|null first()
 * @method FrontendEntity|null last()
 */
class FrontendCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FrontendEntity::class;
    }
}
