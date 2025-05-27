<?php declare(strict_types=1);

namespace RH\Tweakwise\Twig;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    private array $categories = [];

    public function __construct(private readonly EntityRepository $categoryRepository)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tw_category_tree', $this->getCategoryTree(...)),
        ];
    }

    public function getCategoryTree(SalesChannelContext $context): ?array
    {
        if ($context->getSalesChannel()->getNavigationCategoryId()) {
            $criteria = new Criteria([$context->getSalesChannel()->getNavigationCategoryId()]);
            $rootCategory = $this->categoryRepository->search($criteria, $context->getContext())->first();
            if ($rootCategory !== null) {
                $this->parseCategoryTree($rootCategory, $context);
            }
        }

        return $this->categories;
    }

    private function parseCategoryTree(CategoryEntity $category, SalesChannelContext $context): void
    {
        $this->categories[] = $category;

        $criteria = new Criteria();
        $criteria->addAssociation('parent');
        $criteria->addAssociation('products');
        $criteria->addFilter(new EqualsFilter('parentId', $category->getId()));
        $subCategories = $this->categoryRepository->search($criteria, $context->getContext());

        foreach ($subCategories as $subCategory) {
            $this->parseCategoryTree($subCategory, $context);
        }
    }
}
