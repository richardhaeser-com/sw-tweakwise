<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Environment;
use function array_key_exists;
use function time;

class FeedService
{
    public const EXPORT_PATH = 'tweakwise/feed.xml';
    private EntityRepository $salesChannelRepository;
    private EntityRepository $categoryRepository;
    private Context $context;
    private Environment $twig;
    private TemplateFinder $templateFinder;
    private array $categoryData = [];
    private EntityRepository $productsRepository;
    private AbstractProductPriceCalculator $priceCalculator;
    private AbstractSalesChannelContextFactory $salesChannelContextFactory;
    private ProductListingLoader $listingLoader;
    private FilesystemInterface $filesystem;

    public function __construct(
        EntityRepository $salesChannelRepository,
        EntityRepository $categoryRepository,
        Environment $twig,
        TemplateFinder $templateFinder,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        ProductListingLoader $listingLoader,
        FilesystemInterface $filesystem
    )
    {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->categoryRepository = $categoryRepository;
        $this->context = Context::createDefaultContext();
        $this->twig = $twig;
        $this->templateFinder = $templateFinder;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->listingLoader = $listingLoader;
        $this->filesystem = $filesystem;
    }

    public function readFeed(): string
    {
        if (!$this->filesystem->has(self::EXPORT_PATH) || time() - $this->getTimestampOfFeed() > 86400) {
            $this->generateFeed();
        }
        return $this->filesystem->read(self::EXPORT_PATH);
    }

    public function getTimestampOfFeed(): int
    {
        return $this->filesystem->getTimestamp(self::EXPORT_PATH);
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

        if ($this->filesystem->has(self::EXPORT_PATH)) {
            $this->filesystem->delete(self::EXPORT_PATH);
        }

        $this->filesystem->write(self::EXPORT_PATH, $content);
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

        $criteria = new Criteria();
        $criteria->addAssociation('customFields');
        $criteria->addAssociation('options');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('categories');
        $criteria->getAssociation('seoUrls')
            ->setLimit(1)
            ->addFilter(new EqualsFilter('isCanonical', true));

        $criteria->addAssociation('seoUrls.url');

        $criteria->addFilter(
            new ProductAvailableFilter($salesChannel->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );

        $entities = $this->listingLoader->load($criteria, $salesChannelContext);

        $result = ProductListingResult::createFrom($entities);
        $result->addState(...$entities->getStates());

        $this->categoryData['salesChannels'][$salesChannel->getId()]['domains'][$domain->getId() ]['products'] = $result->getElements();
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
