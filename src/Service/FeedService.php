<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Environment;
use function array_key_exists;

class FeedService
{
    private EntityRepository $salesChannelRepository;
    private EntityRepository $categoryRepository;
    private Context $context;
    private Environment $twig;
    private TemplateFinder $templateFinder;
    private array $categoryData = [];

    public function __construct(EntityRepository $salesChannelRepository, EntityRepository $categoryRepository, Environment $twig, TemplateFinder $templateFinder)
    {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->categoryRepository = $categoryRepository;
        $this->context = Context::createDefaultContext();
        $this->twig = $twig;
        $this->templateFinder = $templateFinder;
    }

    public function generateFeed()
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociations(['language', 'languages', 'currency', 'currencies', 'domains', 'type', 'customFields', 'customField']);
        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, $this->context)->getEntities();
        $this->defineCategories($salesChannels);

        $content = $this->twig->render($this->resolveView('tweakwise/feed2.xml.twig'), [
            'categoryData' => $this->categoryData
        ]);
        return $content;
    }

    private function resolveView(string $view): string
    {
        $this->templateFinder->reset();

        return $this->templateFinder->find('@Storefront/' . $view, true, '@RhTweakwise/' . $view);
    }


    public function defineCategories(SalesChannelCollection $salesChannelCollection): void
    {
        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannelCollection as $salesChannel) {
            $customFields = $salesChannel->getCustomFields();
            if ($customFields && array_key_exists('rh_tweakwise_exclude_from_feed', $customFields)) {
                continue;
            }

            $this->categoryData['salesChannels'][$salesChannel->getId()] = [
                'name' => $salesChannel->getName(),
            ];

            /** @var SalesChannelDomainEntity $domain */
            foreach ($salesChannel->getDomains() as $domain) {
                $context = new Context(new SystemSource(), [], $domain->getCurrencyId(), [$domain->getLanguageId(), $salesChannel->getLanguageId()]);
                $criteria = new Criteria([$salesChannel->getNavigationCategoryId()]);
                $criteria->addAssociation('products');
                $criteria->addAssociation('products.customFields');
                /** @var CategoryEntity $rootCategory */
                $rootCategory = $this->categoryRepository->search($criteria, $context)->first();

                $this->categoryData['salesChannels'][$salesChannel->getId()]['domains'][$domain->getId() ] = [
                    'name' => $domain->getUrl(),
                    'rootCategoryId' => $rootCategory->getId(),
                    'categories' => $this->parseCategory([], $rootCategory, $context, $domain, false)
                ];

            }
        }
    }

    public function defineProducts(SalesChannelCollection $salesChannelCollection): void
    {

    }
    protected function parseCategory(array $categories, CategoryEntity $categoryEntity, Context $context, SalesChannelDomainEntity $domainEntity, bool $includeCurrentLevel = true): array
    {
        if ($includeCurrentLevel) {
            $categories[] = $categoryEntity;

            $this->categoryMapping[$categoryEntity->getId()][] = $categoryEntity->getId() . '-' . $domainEntity->getId();
        }

        $criteria = new Criteria();
        $criteria->addAssociation('parent');
        $criteria->addAssociation('products');
        $criteria->addFilter(new EqualsFilter('parentId', $categoryEntity->getId()));
        $subCategories = $this->categoryRepository->search($criteria, $context);
        foreach ($subCategories as $subCategory) {
            $categories = $this->parseCategory($categories, $subCategory, $context, $domainEntity);
        }
        return $categories;
    }
}
