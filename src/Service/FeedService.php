<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use League\Flysystem\FilesystemInterface;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\SalesChannelRepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use SplFileInfo;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use function array_unique;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function ltrim;
use function md5;
use function pathinfo;
use function str_replace;
use function time;
use function unlink;

class FeedService
{
    public const EXPORT_PATH = 'files/tweakwise/feed-{id}.xml';
    public const TMP_EXPORT_PATH = 'files/tweakwise/feed-{id}-tmp.xml';
    private EntityRepository $categoryRepository;
    private Environment $twig;
    private TemplateFinder $templateFinder;
    private array $uniqueCategoryIds = [];
    private AbstractSalesChannelContextFactory $salesChannelContextFactory;
    private NavigationLoader $navigationLoader;
    private int $categoryRank = 1;
    private EntityRepository $feedRepository;
    private SalesChannelRepositoryInterface $productRepository;
    private AbstractProductPriceCalculator $calculator;
    private FilesystemInterface $filesystem;

    public function __construct(
        EntityRepository $categoryRepository,
        Environment $twig,
        TemplateFinder $templateFinder,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        NavigationLoader $navigationLoader,
        EntityRepository $feedRepository,
        SalesChannelRepositoryInterface $productRepository,
        AbstractProductPriceCalculator $calculator,
        FilesystemInterface $filesystem
    )
    {
        $this->categoryRepository = $categoryRepository;
        $this->twig = $twig;
        $this->templateFinder = $templateFinder;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->navigationLoader = $navigationLoader;
        $this->feedRepository = $feedRepository;
        $this->productRepository = $productRepository;
        $this->calculator = $calculator;
        $this->filesystem = $filesystem;
    }

    public function readFeed(FeedEntity $feedEntity): ?string
    {
        $path = ltrim($this->getExportPath($feedEntity, false), 'files/');
        if (!$this->filesystem->has($path) || time() - $this->getTimestampOfFeed($feedEntity) > 86400) {
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
        $path = $this->getExportPath($feed);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function finishXmlFeed(FeedEntity $feed)
    {
        $path = $this->getExportPath($feed, false);
        if (file_exists($path)) {
            unlink($path);
        }
        rename($this->getExportPath($feed), $this->getExportPath($feed, false));
    }

    private function generateItems(FeedEntity $feed)
    {
        foreach ($feed->getSalesChannelDomains() as $salesChannelDomain) {
            $salesChannel = $salesChannelDomain->getSalesChannel();
            $salesChannelContext = $this->salesChannelContextFactory->create('', $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $salesChannelDomain->getLanguageId()]);

            $criteria = new Criteria();
            $criteria->setLimit(1);
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

            $iterator = new SalesChannelRepositoryIterator($this->productRepository, $salesChannelContext, $criteria);
            while (($result = $iterator->fetch()) !== null) {
                $this->calculator->calculate(
                    $result->getElements(),
                    $salesChannelContext
                );
                $this->renderProducts($result->getElements(), $salesChannelDomain, $feed);
            }

        }
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
        file_put_contents($this->getExportPath($feed), $content, FILE_APPEND);
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
    private function renderProducts($products, SalesChannelDomainEntity $domain, FeedEntity $feed): void
    {
        $content = '';
        foreach ($products as $product) {
            $content .= $this->twig->render($this->resolveView('tweakwise/product.xml.twig'), [
                'categoryIdsInFeed' => array_unique($this->uniqueCategoryIds),
                'domainId' => $domain->getId(),
                'domainUrl' => rtrim($domain->getUrl(), '/') . '/',
                'product' => $product,
                'lang' => $domain->getLanguage()->getTranslationCode()->getCode(),
            ]);
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

    protected function getExportPath(FeedEntity $feedEntity, bool $temporarily = true)
    {
        if ($temporarily) {
            return str_replace('{id}', $feedEntity->getId(), self::TMP_EXPORT_PATH);
        }
        return str_replace('{id}', $feedEntity->getId(), self::EXPORT_PATH);
    }
}
