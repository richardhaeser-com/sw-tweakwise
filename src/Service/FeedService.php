<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use League\Flysystem\FilesystemInterface;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\CheckoutRuleScope;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use function array_key_exists;
use function array_unique;
use function crc32;
use function dirname;
use function file_exists;
use function file_put_contents;
use function ltrim;
use function md5;
use function str_replace;
use function unlink;
use function version_compare;

class FeedService
{
    public const EXPORT_PATH = 'tweakwise/feed-{id}.xml';
    public const TMP_EXPORT_PATH = 'tweakwise/feed-{id}-tmp.xml';
    private EntityRepository $categoryRepository;
    private Environment $twig;
    private TemplateFinder $templateFinder;
    private array $uniqueCategoryIds = [];
    private AbstractSalesChannelContextFactory $salesChannelContextFactory;
    private NavigationLoader $navigationLoader;
    private int $categoryRank = 1;
    private EntityRepository $feedRepository;
    private FilesystemInterface $filesystem;
    private ProductListingLoader $listingLoader;
    private array $uniqueProductIds = [];
    private EntityRepository $productRepository;
    private string $shopwareVersion;
    private AbstractRuleLoader $ruleLoader;

    public function __construct(
        EntityRepository $categoryRepository,
        Environment $twig,
        TemplateFinder $templateFinder,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        NavigationLoader $navigationLoader,
        EntityRepository $feedRepository,
        FilesystemInterface $filesystem,
        ProductListingLoader $listingLoader,
        EntityRepository $productRepository,
        string $shopwareVersion,
        AbstractRuleLoader $ruleLoader
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->twig = $twig;
        $this->templateFinder = $templateFinder;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->navigationLoader = $navigationLoader;
        $this->feedRepository = $feedRepository;
        $this->filesystem = $filesystem;
        $this->listingLoader = $listingLoader;
        $this->productRepository = $productRepository;
        $this->shopwareVersion = $shopwareVersion;
        $this->ruleLoader = $ruleLoader;
    }

    public function readFeed(FeedEntity $feedEntity): ?string
    {
        $path = ltrim($this->getExportPath($feedEntity, false), 'files/');
        if (!$this->filesystem->has($path)) {
            return null;
        }
        return $this->filesystem->read($path);
    }

    public function getTimestampOfFeed(FeedEntity $feedEntity): int
    {
        $path = ltrim($this->getExportPath($feedEntity, false), 'files/');
        return $this->filesystem->getTimestamp($path);
    }

    public function generateFeed(FeedEntity $feed, $context)
    {
        $this->uniqueProductIds = [];
        $this->feedRepository->update([
            [
                'id' => $feed->getId(),
                'lastStartedAt' => new \DateTime(),
            ],
        ], $context);

        $this->prepareXmlFeed($feed);
        $this->generateHeader($feed);
        $this->generateTopLevelCategories($feed);
        $this->generateCategories($feed);
        $this->generateMiddle($feed);
        $this->generateItems($feed);
        $this->generateFooter($feed);
        $this->finishXmlFeed($feed);

        $this->feedRepository->update([
            [
                'id' => $feed->getId(),
                'lastGeneratedAt' => new \DateTime(),
            ],
        ], $context);
    }

    private function prepareXmlFeed(FeedEntity $feed)
    {
        $path = $this->getExportPath($feed, true, true);
        if (file_exists($path)) {
            unlink($path);
        }
        $exportDirectory = dirname($path);
        if (!file_exists($exportDirectory)) {
            if (!mkdir($exportDirectory, 0755, true) && !is_dir($exportDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $exportDirectory));
            }
        }
    }

    private function finishXmlFeed(FeedEntity $feed)
    {
        $path = $this->getExportPath($feed, false, true);
        if (file_exists($path)) {
            unlink($path);
        }
        rename($this->getExportPath($feed, true, true), $this->getExportPath($feed, false, true));
    }

    private function generateItems(FeedEntity $feed)
    {
        /** @var SalesChannelDomainEntity $salesChannelDomain */
        foreach ($feed->getSalesChannelDomains() as $salesChannelDomain) {

            /** @var SalesChannelEntity $salesChannel */
            $salesChannel = $salesChannelDomain->getSalesChannel();
            $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $salesChannelDomain->getLanguageId()]);

            $rules = $this->ruleLoader->load($salesChannelContext->getContext());
            $scope = new CheckoutRuleScope($salesChannelContext);

            $rules = $rules->filter(function ($rule) use ($scope) {
                return $rule->getPayload()->match($scope);
            });

            $salesChannelContext->setRuleIds($rules->getIds());

            $criteria = new Criteria();
            $criteria->setOffset(0);
            $criteria->setLimit(10);
            $criteria->addAssociation('customFields');
            $criteria->addAssociation('options');
            $criteria->addAssociation('options.group');
            $criteria->addAssociation('properties');
            $criteria->addAssociation('properties.group');
            $criteria->addAssociation('manufacturer');
            $criteria->addAssociation('categories');
            $criteria->addAssociation('productReviews');
            $criteria->addAssociation('cover.media.thumbnails');
            $criteria->addAssociation('children');
            $criteria->addAssociation('children.options');
            $criteria->addAssociation('children.options.group');
            $criteria->addAssociation('tags');
            $criteria->getAssociation('seoUrls')
                ->setLimit(1)
                ->addFilter(new EqualsFilter('isCanonical', true));

            $criteria->addAssociation('seoUrls.url');

            $criteria->addFilter(
                new ProductAvailableFilter($salesChannel->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
            );

            /** @var ProductListingResult $result */
            while (($result = $this->loadProducts($criteria, $salesChannelContext)) !== null) {
                $this->renderProducts($result->getElements(), $salesChannelDomain, $feed, $salesChannelContext->getContext());
                $criteria->setOffset($criteria->getOffset() + $criteria->getLimit());
            }
        }
    }

    private function loadProducts(Criteria $criteria, SalesChannelContext $salesChannelContext)
    {
        $entities = $this->listingLoader->load($criteria, $salesChannelContext);
        $result = ProductListingResult::createFrom($entities);
        if ($result->getTotal() > 0) {
            $result->addState(...$entities->getStates());
            return $result;
        }

        return null;
    }

    private function generateHeader(FeedEntity $feed): void
    {
        $content = $this->twig->render($this->resolveView('tweakwise/header.xml.twig'), []);
        $this->writeContent($content, $feed);
    }
    private function generateMiddle(FeedEntity $feed): void
    {
        $content = $this->twig->render($this->resolveView('tweakwise/middle.xml.twig'), []);
        $this->writeContent($content, $feed);
    }
    private function generateFooter(FeedEntity $feed): void
    {
        $content = $this->twig->render($this->resolveView('tweakwise/footer.xml.twig'), []);
        $this->writeContent($content, $feed);
    }

    private function generateTopLevelCategories(FeedEntity $feed)
    {
        $rootCategoryEntity = new CategoryEntity();
        $rootCategoryEntity->setName('Shopware feed');
        $rootCategoryEntity->setTranslated(['name' => 'Shopware feed']);

        $content = $this->twig->render($this->resolveView('tweakwise/category.xml.twig'), [
            'elementId' => 'root',
            'category' => $rootCategoryEntity,
            'rank' => $this->categoryRank,
        ]);
        $this->categoryRank++;

        $salesChannels = $this->getSalesChannelsFromFeed($feed);
        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $salesChannelCategory = new CategoryEntity();
            $salesChannelCategory->setName($salesChannel->getName());
            $salesChannelCategory->setTranslated(['name' => $salesChannel->getName()]);

            $content .= $this->twig->render($this->resolveView('tweakwise/category.xml.twig'), [
                'elementId' => md5($salesChannel->getId()),
                'category' => $salesChannelCategory,
                'parentElementId' => 'root',
                'rank' => $this->categoryRank,
            ]);
            $this->categoryRank++;
        }

        foreach ($feed->getSalesChannelDomains() as $salesChannelDomain) {
            $salesChannelDomainCategory = new CategoryEntity();
            $salesChannelDomainCategory->setName($salesChannelDomain->getLanguage()->getTranslationCode()->getCode());
            $salesChannelDomainCategory->setTranslated(['name' => $salesChannelDomain->getLanguage()->getTranslationCode()->getCode()]);

            $content .= $this->twig->render($this->resolveView('tweakwise/category.xml.twig'), [
                'elementId' => md5($salesChannelDomain->getSalesChannel()->getNavigationCategoryId() . '_' . $salesChannelDomain->getId()),
                'category' => $salesChannelDomainCategory,
                'parentElementId' => md5($salesChannelDomain->getSalesChannel()->getId()),
                'rank' => $this->categoryRank,
            ]);
            $this->categoryRank++;
        }
        $this->writeContent($content, $feed);
    }

    private function getSalesChannelsFromFeed(FeedEntity $feed): array
    {
        $salesChannels = [];
        foreach ($feed->getSalesChannelDomains() as $salesChannelDomain) {
            if (!in_array($salesChannelDomain->getSalesChannel(), $salesChannels)) {
                $salesChannels[] = $salesChannelDomain->getSalesChannel();
            }
        }
        return $salesChannels;
    }
    private function renderCategory(CategoryEntity $category, SalesChannelDomainEntity $domain, FeedEntity $feed)
    {
        $content = $this->twig->render($this->resolveView('tweakwise/category.xml.twig'), [
            'domainId' => $domain->getId(),
            'category' => $category,
            'rank' => $this->categoryRank,
        ]);
        $this->writeContent($content, $feed);
    }

    private function writeContent(string $content, FeedEntity $feed)
    {
        file_put_contents($this->getExportPath($feed, true, true), $content, FILE_APPEND);
    }

    /**
     * @param EntityCollection|array $products
     * @param SalesChannelDomainEntity $domain
     * @param FeedEntity $feed
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function renderProducts($products, SalesChannelDomainEntity $domain, FeedEntity $feed, Context $context): void
    {
        $content = '';
        /** @var ProductEntity $product */
        foreach ($products as $product) {
            $productId = $product->getProductNumber() . ' (' . $domain->getLanguage()->getTranslationCode()->getCode() . ' - ' . crc32($domain->getId()) . ')';
            if (!in_array($productId, $this->uniqueProductIds, true)) {
                $otherVariants = null;
                if ($product->getParentId()) {
                    // only 1 variant is shown in listing
//                    $context = new Context(new SystemSource(), [], $domain->getCurrencyId(), [$domain->getLanguageId(), $domain->getLanguageId()]);
                    $criteria = new Criteria([$product->getParentId()]);
                    $criteria->addAssociation('children');
                    $criteria->addAssociation('children.options');
                    $criteria->addAssociation('children.options.group');

                    /** @var ProductEntity $parent */
                    $parent = $this->productRepository->search($criteria, $context)->first();
                    if ($parent->getChildCount() > 0) {
                        $configurationGroupConfigArray = [];
                        if (version_compare($this->shopwareVersion, '6.4.15', '>=')) {
                            /** @phpstan-ignore-next-line */
                            $listingConfig = $parent->getVariantListingConfig();
                            if ($listingConfig) {
                                $configurationGroupConfigArray = $listingConfig->getConfiguratorGroupConfig();
                            }
                        } else {
                            $configurationGroupConfigArray = $parent->getConfiguratorGroupConfig();
                        }

                        $getVariants = true;
                        if (!$parent->getMainVariantId()) {
                            foreach ($configurationGroupConfigArray as $configurationGroupConfig) {
                                if (
                                    is_array($configurationGroupConfig)
                                    && array_key_exists('expressionForListings', $configurationGroupConfig)
                                    && $configurationGroupConfig['expressionForListings'] === true
                                ) {
                                    $getVariants = false;
                                    break;
                                }
                            }
                        }
                        if ($getVariants === true) {
                            $otherVariants = $parent->getChildren();
                        }

                    }
                }
                if ($product->getChildCount() > 0) {
                    $otherVariants = $product->getChildren();
                }

                $content .= $this->twig->render($this->resolveView('tweakwise/product.xml.twig'), [
                    'categoryIdsInFeed' => array_unique($this->uniqueCategoryIds),
                    'domainId' => $domain->getId(),
                    'domainUrl' => rtrim($domain->getUrl(), '/') . '/',
                    'product' => $product,
                    'otherVariants' => $otherVariants,
                    'lang' => $domain->getLanguage()->getTranslationCode()->getCode(),
                ]);
                $this->uniqueProductIds[] = $productId;
            }
        }

        $this->writeContent($content, $feed);
    }

    private function resolveView(string $view): string
    {
        return $this->templateFinder->find('@Storefront/' . $view, true, '@RhTweakwise/' . $view);
    }

    public function generateCategories(FeedEntity $feed)
    {
        foreach ($feed->getSalesChannelDomains() as $salesChannelDomain) {

            $salesChannel = $salesChannelDomain->getSalesChannel();
            $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $salesChannelDomain->getLanguageId()]);

            $context = new Context(new SystemSource(), [], $salesChannelDomain->getCurrencyId(), [$salesChannelDomain->getLanguageId(), $salesChannel->getLanguageId()]);
            $criteria = new Criteria([$salesChannel->getNavigationCategoryId()]);
            /** @var CategoryEntity $rootCategory */
            $rootCategory = $this->categoryRepository->search($criteria, $context)->first();
            $navigation = $this->navigationLoader->load($rootCategory->getId(), $salesChannelContext, $rootCategory->getId(), 99);

            $this->parseTreeItems([], $navigation->getTree(), $salesChannelDomain, $feed);
        }
    }

    protected function parseTreeItems(array $categories, array $treeItems, SalesChannelDomainEntity $domainEntity, FeedEntity $feed): void
    {
        /** @var TreeItem $treeItem */
        foreach ($treeItems as $treeItem) {
            $this->uniqueCategoryIds[] = $treeItem->getCategory()->getId() . '_' . $domainEntity->getId();
            $this->renderCategory($treeItem->getCategory(), $domainEntity, $feed);
            $this->categoryRank++;

            $this->parseTreeItems($categories, $treeItem->getChildren(), $domainEntity, $feed);
        }
    }

    protected function getExportPath(FeedEntity $feedEntity, bool $temporarily = true, bool $absolute = false): string
    {
        $pathPrefix = '';
        if ($absolute) {
            /** @phpstan-ignore-next-line */
            $pathPrefix = $this->filesystem->getAdapter()->getPathPrefix();
        }

        if ($temporarily) {
            return $pathPrefix . str_replace('{id}', $feedEntity->getId(), self::TMP_EXPORT_PATH);
        }
        return $pathPrefix . str_replace('{id}', $feedEntity->getId(), self::EXPORT_PATH);
    }
}
