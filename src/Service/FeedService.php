<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
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
use Symfony\Component\Console\Helper\ProgressBar;
use Twig\Environment;
use function array_key_exists;
use function array_unique;
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
    private array $uniqueCategoryIds = [];
    private AbstractSalesChannelContextFactory $salesChannelContextFactory;
    private ProductListingLoader $listingLoader;
    private FilesystemInterface $filesystem;
    private NavigationLoader $navigationLoader;

    public function __construct(
        EntityRepository $salesChannelRepository,
        EntityRepository $categoryRepository,
        Environment $twig,
        TemplateFinder $templateFinder,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        ProductListingLoader $listingLoader,
        FilesystemInterface $filesystem,
        NavigationLoader $navigationLoader
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
        $this->navigationLoader = $navigationLoader;
    }

    public function readFeed(): string
    {
        if (!$this->filesystem->has(self::EXPORT_PATH) || time() - $this->getTimestampOfFeed() > 86400) {
            return '';
        }
        return $this->filesystem->read(self::EXPORT_PATH);
    }

    public function getTimestampOfFeed(): int
    {
        return $this->filesystem->getTimestamp(self::EXPORT_PATH);
    }

    public function generateFeed(ProgressBar $salesChannelProgressBar = null, ProgressBar $domainProgressBar = null, ProgressBar $categoryProgressBar = null, ProgressBar $productProgressBar = null)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociations(['language', 'languages', 'currency', 'currencies', 'domains', 'domains.salesChannel', 'domains.language', 'domains.language.translationCode', 'type', 'customFields']);
        /** @var SalesChannelCollection $salesChannels */
        $salesChannels = $this->salesChannelRepository->search($criteria, $this->context)->getEntities();

        if ($salesChannelProgressBar instanceof ProgressBar) {
            $salesChannelProgressBar->setMaxSteps($salesChannels->count());
            $salesChannelProgressBar->start();
        }

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            if ($salesChannelProgressBar instanceof ProgressBar) {
                $salesChannelProgressBar->setMessage($salesChannel->getName(), 'sales-channel');
                $salesChannelProgressBar->display();
            }
            $customFields = $salesChannel->getCustomFields();
            if ($customFields && array_key_exists('rh_tweakwise_exclude_from_feed', $customFields)) {
                continue;
            }

            $this->categoryData['salesChannels'][$salesChannel->getId()] = [
                'name' => $salesChannel->getName(),
            ];

            if ($domainProgressBar instanceof ProgressBar) {
                $domainProgressBar->setMaxSteps($salesChannel->getDomains()->count());
                $domainProgressBar->start();
            }

            /** @var SalesChannelDomainEntity $domain */
            foreach ($salesChannel->getDomains() as $domain) {
                if ($domainProgressBar instanceof ProgressBar) {
                    $domainProgressBar->setMessage($domain->getUrl(), 'domain');
                    $domainProgressBar->display();
                }

                if ($categoryProgressBar instanceof ProgressBar) {
                    $categoryProgressBar->start();
                    $this->defineCategories($domain, $categoryProgressBar);
                    $categoryProgressBar->finish();
                } else {
                    $this->defineCategories($domain, $categoryProgressBar);
                }

                if ($productProgressBar instanceof ProgressBar) {
                    $productProgressBar->setMessage('Generating...');
                    $productProgressBar->setMaxSteps(1);
                    $productProgressBar->start();
                }

                $this->defineProducts($domain);
                if ($productProgressBar instanceof ProgressBar) {
                    $productProgressBar->advance();
                    $productProgressBar->setMessage('Done');
                    $productProgressBar->finish();
                    $productProgressBar->clear();
                }

                if ($domainProgressBar instanceof ProgressBar) {
                    $domainProgressBar->advance();
                }
            }

            if ($domainProgressBar instanceof ProgressBar) {
                $domainProgressBar->finish();
            }

            if ($salesChannelProgressBar instanceof ProgressBar) {
                $salesChannelProgressBar->advance();
            }
        }
        if ($salesChannelProgressBar instanceof ProgressBar) {
            $salesChannelProgressBar->finish();
        }

        $content = $this->twig->render($this->resolveView('tweakwise/feed.xml.twig'), [
            'categoryIdsInFeed' => array_unique($this->uniqueCategoryIds),
            'categoryData' => $this->categoryData,
        ]);

        if ($this->filesystem->has(self::EXPORT_PATH)) {
            $this->filesystem->delete(self::EXPORT_PATH);
        }

        $this->filesystem->write(self::EXPORT_PATH, $content);
    }

    private function resolveView(string $view): string
    {
        return $this->templateFinder->find('@Storefront/' . $view, true, '@RhTweakwise/' . $view);
    }


    public function defineCategories(SalesChannelDomainEntity $domain, ProgressBar $categoryProgressBar = null): void
    {
        $salesChannel = $domain->getSalesChannel();
        $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $domain->getLanguageId()]);

        $context = new Context(new SystemSource(), [], $domain->getCurrencyId(), [$domain->getLanguageId(), $salesChannel->getLanguageId()]);
        $criteria = new Criteria([$salesChannel->getNavigationCategoryId()]);
        /** @var CategoryEntity $rootCategory */
        $rootCategory = $this->categoryRepository->search($criteria, $context)->first();

        $navigation = $this->navigationLoader->load($rootCategory->getId(), $salesChannelContext, $rootCategory->getId(), 99);
        $categories = $this->parseTreeItems([], $navigation->getTree(), $domain, $categoryProgressBar);

        $this->categoryData['salesChannels'][$salesChannel->getId()]['domains'][$domain->getId() ] = [
            'name' => $domain->getUrl(),
            'lang' => $domain->getLanguage()->getTranslationCode()->getCode(),
            'url' => rtrim($domain->getUrl(), '/') . '/',
            'rootCategoryId' => $rootCategory->getId(),
            'categories' => $categories,
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

    protected function parseTreeItems(array $categories, array $treeItems, SalesChannelDomainEntity $domainEntity, ProgressBar $categoryProgressBar = null): array
    {
        /** @var TreeItem $treeItem */
        foreach ($treeItems as $treeItem) {
            $this->uniqueCategoryIds[] = $treeItem->getCategory()->getId() . '_' . $domainEntity->getId();
            $categories[] = $treeItem->getCategory();

            if ($categoryProgressBar instanceof ProgressBar) {
                $categoryProgressBar->setMessage($treeItem->getCategory()->getTranslated()['name'] ?: '-', 'category');
                $categoryProgressBar->advance();
            }

            $categories = $this->parseTreeItems($categories, $treeItem->getChildren(), $domainEntity, $categoryProgressBar);
        }

        return $categories;
    }
}
