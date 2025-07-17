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
    private EntityRepository $categoryRepository;

    public function __construct(EntityRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tw_category_tree', [$this, 'getCategoryTree']),
            new TwigFunction('tw_convert_category_path', [$this, 'convertCategoryTree']),
        ];
    }

    public function convertCategoryTree(string $path, string $rootCategoryId, string $currentCategoryId, string $domainId): string
    {
        $categories = array_filter(explode('|', $path), function (string $pathEntry) use ($rootCategoryId) {
            if (!($pathEntry)) {
                return false;
            }
            if ($pathEntry === $rootCategoryId) {
                return false;
            }

            return true;
        });

        $categories[] = $currentCategoryId;
        $categoryHashes = [];
        foreach ($categories as $category) {
            $categoryHashes[] = md5($category . '_' . $domainId);
        }
        return implode('-', $categoryHashes);
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
