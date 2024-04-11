<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractNavigationRoute;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class TweakwiseCategoryLoader
{
    /**
     * @var TreeItem
     */
    private $treeItem;

    /**
     * @var AbstractNavigationRoute
     */
    private $navigationRoute;

    /**
     * @internal
     */
    public function __construct(
        AbstractNavigationRoute $navigationRoute
    ) {
        $this->treeItem = new TreeItem(null, []);
        $this->navigationRoute = $navigationRoute;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CategoryNotFoundException
     */
    public function load(string $activeId, SalesChannelContext $context, string $rootId, int $depth = 2, bool $includeHiddenCategories = false): Tree
    {
        $request = new Request();
        $request->query->set('buildTree', 'false');
        $request->query->set('depth', (string) $depth);

        $criteria = new Criteria();
        $criteria->setTitle('header::navigation');

        $categories = $this->navigationRoute
            ->load($activeId, $rootId, $request, $context, $criteria)
            ->getCategories();

        return $this->getTree($rootId, $categories, $categories->get($activeId), $includeHiddenCategories);
    }

    private function getTree(?string $rootId, CategoryCollection $categories, ?CategoryEntity $active, bool $includeHiddenCategories = false): Tree
    {
        $parents = [];
        $items = [];
        foreach ($categories as $category) {
            $item = clone $this->treeItem;
            $item->setCategory($category);

            $parents[$category->getParentId()][$category->getId()] = $item;
            $items[$category->getId()] = $item;
        }

        foreach ($parents as $parentId => $children) {
            if (empty($parentId)) {
                continue;
            }

            $sorted = AfterSort::sort($children);

            $filtered = \array_filter($sorted, static function (TreeItem $filter) use ($includeHiddenCategories) {
                if ($includeHiddenCategories) {
                    return $filter->getCategory()->getActive();
                }
                return $filter->getCategory()->getActive() && $filter->getCategory()->getVisible();
            });

            if (!isset($items[$parentId])) {
                continue;
            }

            $item = $items[$parentId];
            $item->setChildren($filtered);
        }

        $root = $parents[$rootId] ?? [];
        $root = AfterSort::sort($root);

        $filtered = [];
        /** @var TreeItem $item */
        foreach ($root as $key => $item) {
            if (!$item->getCategory()->getActive()) {
                continue;
            }
            if (!$includeHiddenCategories && !$item->getCategory()->getVisible()) {
                continue;
            }
            $filtered[$key] = $item;
        }

        return new Tree($active, $filtered);
    }
}
