<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
    private EntityRepository $productsRepository;
    private AbstractProductPriceCalculator $priceCalculator;
    private AbstractSalesChannelContextFactory $salesChannelContextFactory;

    public function __construct(EntityRepository $salesChannelRepository, EntityRepository $categoryRepository, EntityRepository $productsRepository, Environment $twig, TemplateFinder $templateFinder, AbstractProductPriceCalculator $priceCalculator, AbstractSalesChannelContextFactory $salesChannelContextFactory)
    {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productsRepository = $productsRepository;
        $this->context = Context::createDefaultContext();
        $this->twig = $twig;
        $this->templateFinder = $templateFinder;
        $this->priceCalculator = $priceCalculator;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
    }

    public function generateFeed()
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociations(['language', 'languages', 'currency', 'currencies', 'domains', 'domains.salesChannel', 'domains.language', 'domains.language.translationCode', 'type', 'customFields', 'customField']);
        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, $this->context)->getEntities();
        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $customFields = $salesChannel->getCustomFields();
            if ($customFields && array_key_exists('rh_tweakwise_exclude_from_feed', $customFields)) {
                continue;
            }

            $this->categoryData['salesChannels'][$salesChannel->getId()] = [
                'name' => $salesChannel->getName(),
            ];

            /** @var SalesChannelDomainEntity $domain */
            foreach ($salesChannel->getDomains() as $domain) {
                $this->defineCategories($domain);
                $this->defineProducts($domain);
            }
        }

        $content = $this->twig->render($this->resolveView('tweakwise/feed.xml.twig'), [
            'categoryData' => $this->categoryData
        ]);
        return $content;
    }

    private function resolveView(string $view): string
    {
        $this->templateFinder->reset();

        return $this->templateFinder->find('@Storefront/' . $view, true, '@RhTweakwise/' . $view);
    }


    public function defineCategories(SalesChannelDomainEntity $domain): void
    {
        $salesChannel = $domain->getSalesChannel();

        $context = new Context(new SystemSource(), [], $domain->getCurrencyId(), [$domain->getLanguageId(), $salesChannel->getLanguageId()]);

        $criteria = new Criteria([$salesChannel->getNavigationCategoryId()]);
        $criteria->addAssociation('products');
        $criteria->addAssociation('products.customFields');
        /** @var CategoryEntity $rootCategory */
        $rootCategory = $this->categoryRepository->search($criteria, $context)->first();

        $this->categoryData['salesChannels'][$salesChannel->getId()]['domains'][$domain->getId() ] = [
            'name' => $domain->getUrl(),
            'lang' => $domain->getLanguage()->getTranslationCode()->getCode(),
            'url' => rtrim($domain->getUrl(), '/') . '/',
            'rootCategoryId' => $rootCategory->getId(),
            'categories' => $this->parseCategory([], $rootCategory, $context, $domain, false)
        ];
    }

    public function defineProducts(SalesChannelDomainEntity $domain): void
    {
        $salesChannel = $domain->getSalesChannel();
        $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $domain->getLanguageId()]);
        $context = new Context(new SystemSource(), [], $domain->getCurrencyId(), [$domain->getLanguageId(), $salesChannel->getLanguageId()]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('visibilities.salesChannel.id', [$salesChannel->getId()]));

        $criteria->addAssociation('customFields');
        $criteria->addAssociation('categories');
        $criteria->addAssociation('options');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('price');
        $criteria->addAssociation('cover');
        $criteria->addAssociation('prices');
        $criteria->addAssociation('children');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('calculatedPrices');

        $criteria->getAssociation('seoUrls')
            ->setLimit(1)
            ->addFilter(new EqualsFilter('isCanonical', true));

        $criteria->addAssociation('seoUrls.url');
        $criteria->addAssociation('seoUrls.sales');
        $products = $this->productsRepository->search($criteria, $context);

        $this->priceCalculator->calculate($products, $salesChannelContext);
        $this->categoryData['salesChannels'][$salesChannel->getId()]['domains'][$domain->getId() ]['products'] = $products->getElements();
    }

    protected function parseCategory(array $categories, CategoryEntity $categoryEntity, Context $context, SalesChannelDomainEntity $domainEntity, bool $includeCurrentLevel = true): array
    {
        if ($includeCurrentLevel) {
            $categories[] = $categoryEntity;

            $this->categoryMapping[$categoryEntity->getId()][] = $categoryEntity->getId() . '_' . $domainEntity->getId();
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
