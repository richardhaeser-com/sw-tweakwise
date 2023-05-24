<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use League\Flysystem\FilesystemInterface;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
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
use Twig\Environment;
use function array_key_exists;
use function array_unique;
use function str_replace;
use function time;

class FeedService
{
    public const EXPORT_PATH = 'tweakwise/feed-{id}.xml';
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
    private int $categoryRank = 1;

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

    public function readFeed(FeedEntity $feedEntity): ?string
    {
        if (!$this->filesystem->has($this->getExportPath($feedEntity)) || time() - $this->getTimestampOfFeed($feedEntity) > 86400) {
            return null;
        }
        return $this->filesystem->read($this->getExportPath($feedEntity));
    }

    public function getTimestampOfFeed(FeedEntity $feedEntity): int
    {
        return $this->filesystem->getTimestamp($this->getExportPath($feedEntity));
    }

    public function generateFeed(FeedEntity $feed)
    {
        foreach ($feed->getSalesChannelDomains() as $salesChannelDomain) {
            $salesChannel = $salesChannelDomain->getSalesChannel();

            if (!array_key_exists('salesChannels', $this->categoryData) || !array_key_exists($salesChannel->getId(),$this->categoryData['salesChannels'])) {
                $this->categoryData['salesChannels'][$salesChannel->getId()] = [
                    'name' => $salesChannel->getName(),
                ];
                $this->defineCategories($salesChannelDomain);
                $this->defineProducts($salesChannelDomain);
            }
        }

        $content = $this->twig->render($this->resolveView('tweakwise/feed.xml.twig'), [
            'categoryData' => $this->categoryData,
        ]);

        if ($this->filesystem->has($this->getExportPath($feed))) {
            $this->filesystem->delete($this->getExportPath($feed));
        }

        $this->filesystem->write($this->getExportPath($feed), $content);
    }

    private function renderCategory(CategoryEntity $category, SalesChannelDomainEntity $domain): string
    {
        return $this->twig->render($this->resolveView('tweakwise/category.xml.twig'), [
            'domainId' => $domain->getId(),
            'category' => $category,
            'rank' => $this->categoryRank
        ]);
    }

    private function renderProducts(array $products, SalesChannelDomainEntity $domain): string
    {
        $output = '';
        foreach ($products as $product) {
            $output .= $this->twig->render($this->resolveView('tweakwise/product.xml.twig'), [
                'categoryIdsInFeed' => array_unique($this->uniqueCategoryIds),
                'domainId' => $domain->getId(),
                'domainUrl' => rtrim($domain->getUrl(), '/') . '/',
                'product' => $product,
                'lang' => $domain->getLanguage()->getTranslationCode()->getCode()
            ]);
        }

        return $output;
    }

    private function resolveView(string $view): string
    {
        return $this->templateFinder->find('@Storefront/' . $view, true, '@RhTweakwise/' . $view);
    }


    public function defineCategories(SalesChannelDomainEntity $domain): void
    {
        $salesChannel = $domain->getSalesChannel();
        $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $domain->getLanguageId()]);

        $context = new Context(new SystemSource(), [], $domain->getCurrencyId(), [$domain->getLanguageId(), $salesChannel->getLanguageId()]);
        $criteria = new Criteria([$salesChannel->getNavigationCategoryId()]);
        /** @var CategoryEntity $rootCategory */
        $rootCategory = $this->categoryRepository->search($criteria, $context)->first();

        $navigation = $this->navigationLoader->load($rootCategory->getId(), $salesChannelContext, $rootCategory->getId(), 99);
        $categories = $this->parseTreeItems([], $navigation->getTree(), $domain);

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
        $criteria->addAssociation('productReviews');
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

        $this->categoryData['salesChannels'][$salesChannel->getId()]['domains'][$domain->getId() ]['products'] = $this->renderProducts($result->getElements(), $domain);
    }

    protected function parseTreeItems(array $categories, array $treeItems, SalesChannelDomainEntity $domainEntity): array
    {
        /** @var TreeItem $treeItem */
        foreach ($treeItems as $treeItem) {
            $this->uniqueCategoryIds[] = $treeItem->getCategory()->getId() . '_' . $domainEntity->getId();
            $categories[] = $this->renderCategory($treeItem->getCategory(), $domainEntity);
            $this->categoryRank++;

            $categories = $this->parseTreeItems($categories, $treeItem->getChildren(), $domainEntity);
        }

        return $categories;
    }

    protected function getExportPath(FeedEntity $feedEntity)
    {
        return str_replace('{id}', $feedEntity->getId(), self::EXPORT_PATH);
    }
}
