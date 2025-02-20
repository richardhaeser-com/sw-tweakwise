<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use function array_key_exists;
use function array_unique;
use Cron\CronExpression;
use DateInterval;
use DateTime;
use function dirname;
use const FILE_APPEND;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use GuzzleHttp\Client;
use function in_array;
use function is_array;
use function is_dir;
use function md5;
use function mkdir;
use function rename;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Events\TweakwiseProductFeedCriteriaEvent;
use RH\Tweakwise\Events\TweakwiseProductFeedResultEvent;
use function rtrim;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use function sprintf;
use function str_replace;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use function unlink;

class FeedService
{
    public const EXPORT_PATH = 'tweakwise/feed-{id}.xml';
    public const TMP_EXPORT_PATH = 'tweakwise/feed-{id}-tmp.xml';
    private EntityRepository $categoryRepository;
    private Environment $twig;
    private TemplateFinder $templateFinder;
    private array $uniqueCategoryIds = [];
    private AbstractSalesChannelContextFactory $salesChannelContextFactory;

    private TweakwiseCategoryLoader $categoryLoader;
    private int $categoryRank = 1;
    private EntityRepository $feedRepository;
    private ProductListingLoader $listingLoader;
    private array $uniqueProductIds = [];
    private EntityRepository $productRepository;
    private string $shopwareVersion;
    private AbstractRuleLoader $ruleLoader;
    private string $path;
    private EventDispatcherInterface $eventDispatcher;
    private LocaleSwitcher $localeSwitcher;

    public function __construct(
        EntityRepository $categoryRepository,
        Environment $twig,
        TemplateFinder $templateFinder,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        TweakwiseCategoryLoader $categoryLoader,
        EntityRepository $feedRepository,
        ProductListingLoader $listingLoader,
        EntityRepository $productRepository,
        string $shopwareVersion,
        AbstractRuleLoader $ruleLoader,
        string $path,
        EventDispatcherInterface $eventDispatcher,
        LocaleSwitcher $localeSwitcher
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->twig = $twig;
        $this->templateFinder = $templateFinder;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->categoryLoader = $categoryLoader;
        $this->feedRepository = $feedRepository;
        $this->listingLoader = $listingLoader;
        $this->productRepository = $productRepository;
        $this->shopwareVersion = $shopwareVersion;
        $this->ruleLoader = $ruleLoader;
        $this->path = $path;
        $this->eventDispatcher = $eventDispatcher;
        $this->localeSwitcher = $localeSwitcher;
    }

    public function fixFeedRecords(bool $forceFeedGeneration = false): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');
        $criteria->addAssociation('salesChannelDomains.language');
        $criteria->addAssociation('salesChannelDomains.language.translationCode');
        $context = Context::createDefaultContext();

        $feeds = $this->feedRepository->search($criteria, $context)->getEntities();
        /** @var FeedEntity $feed */
        foreach ($feeds as $feed) {
            $data = [];

            if (!$feed->getInterval()) {
                $data['interval'] = '0 3 * * *';
            }
            if (!$feed->getStatus() || $forceFeedGeneration) {
                $data['status'] = FeedEntity::STATUS_QUEUED;
            }
            if ($forceFeedGeneration === true || $feed->getNextGenerationAt() === null) {
                $data['nextGenerationAt'] = new \DateTime();
            }

            if (!empty($data)) {
                $data['id'] = $feed->getId();
                $this->feedRepository->update([
                    $data,
                ], $context);

            }
        }
    }

    public function scheduleFeeds(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');
        $criteria->addAssociation('salesChannelDomains.language');
        $criteria->addAssociation('salesChannelDomains.language.translationCode');
        $criteria->addFilter(new EqualsFilter('status', FeedEntity::STATUS_COMPLETED));
        $context = Context::createDefaultContext();

        $feeds = $this->feedRepository->search($criteria, $context)->getEntities();
        /** @var FeedEntity $feed */
        foreach ($feeds as $feed) {
            if ($feed->getLastGeneratedAt()) {
                try {
                    $cron = new CronExpression($feed->getInterval());
                    $newDate = $cron->getNextRunDate();
                } catch (\InvalidArgumentException $e) {
                    $interval = DateInterval::createFromDateString($feed->getInterval() . ' minutes');
                    /** @var DateTime $lastGenerated */
                    $lastGenerated = $feed->getLastGeneratedAt();
                    $newDate = $lastGenerated->add($interval);
                }

                $this->feedRepository->update([
                    [
                        'id' => $feed->getId(),
                        'status' => FeedEntity::STATUS_QUEUED,
                        'nextGenerationAt' => $newDate,
                    ],
                ], $context);
            }
        }
    }

    public function generateScheduledFeeds(): void
    {
        $now = new \DateTime();
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');
        $criteria->addAssociation('salesChannelDomains.language');
        $criteria->addAssociation('salesChannelDomains.language.translationCode');

        $criteria->addFilter(new EqualsFilter('status', FeedEntity::STATUS_QUEUED));
        $criteria->addFilter(new RangeFilter('nextGenerationAt', [RangeFilter::LTE => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));

        $context = Context::createDefaultContext();

        $feeds = $this->feedRepository->search($criteria, $context)->getEntities();
        /** @var FeedEntity $feed */
        foreach ($feeds as $feed) {
            $this->generateFeed($feed, $context);
        }
    }
    public function readFeed(FeedEntity $feedEntity): ?string
    {
        $path = $this->getExportPath($feedEntity, false, true);
        if (!file_exists($path)) {
            return null;
        }

        return (string)file_get_contents($path);
    }

    public function generateFeed(FeedEntity $feed, $context)
    {
        $this->uniqueProductIds = [];
        $this->feedRepository->update([
            [
                'id' => $feed->getId(),
                'lastStartedAt' => new \DateTime(),
                'status' => FeedEntity::STATUS_RUNNING,
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
        $this->startImportTask($feed);

        $this->feedRepository->update([
            [
                'id' => $feed->getId(),
                'lastGeneratedAt' => new \DateTime(),
                'status' => FeedEntity::STATUS_COMPLETED,
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

    private function startImportTask(FeedEntity $feed): void
    {
        if (!$feed->getImportTaskUrl()) {
            return;
        }

        $client = new Client();
        $client->request('GET', $feed->getImportTaskUrl());
    }

    private function generateItems(FeedEntity $feed)
    {
        /** @var SalesChannelDomainEntity $salesChannelDomain */
        foreach ($feed->getSalesChannelDomains() as $salesChannelDomain) {

            $this->localeSwitcher->setLocale($salesChannelDomain->getLanguage()->getTranslationCode()->getCode());
            /** @var SalesChannelEntity $salesChannel */
            $salesChannel = $salesChannelDomain->getSalesChannel();
            $salesChannelContext = $this->salesChannelContextFactory->create(Uuid::randomHex(), $salesChannel->getId(), [SalesChannelContextService::LANGUAGE_ID => $salesChannelDomain->getLanguageId()]);
            $cart = new Cart(Uuid::randomHex());
            $rules = $this->ruleLoader->load($salesChannelContext->getContext())->filterMatchingRules($cart, $salesChannelContext);
            $salesChannelContext->setRuleIds($rules->getIds());

            $criteria = new Criteria();
            $criteria->setOffset(0);
            $criteria->setLimit(1);
            $criteria->addAssociation('customFields');

            if (!$feed->isExcludeOptions()) {
                $criteria->addAssociation('options');
                $criteria->addAssociation('options.group');
            }
            if (!$feed->isExcludeProperties()) {
                $criteria->addAssociation('properties');
                $criteria->addAssociation('properties.group');
            }
            $criteria->addAssociation('manufacturer');
            $criteria->addAssociation('categories');
            $criteria->addAssociation('streams');
            $criteria->addAssociation('streams.categories');
            $criteria->addAssociation('media');

            if (!$feed->isExcludeReviews()) {
                $criteria->addAssociation('productReviews');
            }
            $criteria->addAssociation('cover.media.thumbnails');

            if (!$feed->isExcludeTags()) {
                $criteria->addAssociation('tags');
            }

            $criteria->getAssociation('seoUrls')
                ->setLimit(1)
                ->addFilter(new EqualsFilter('isCanonical', true));

            $criteria->addAssociation('seoUrls.url');
            $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));
            $criteria->addFilter(
                new ProductAvailableFilter($salesChannel->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
            );

            $this->eventDispatcher->dispatch(
                new TweakwiseProductFeedCriteriaEvent($criteria, $salesChannelContext)
            );

            /** @var ProductListingResult $result */
            while (($result = $this->loadProducts($criteria, $salesChannelContext)) !== null) {
                $this->eventDispatcher->dispatch(
                    new TweakwiseProductFeedResultEvent($result, $salesChannelContext)
                );

                $this->renderProducts($result->getElements(), $salesChannelDomain, $feed, $salesChannelContext);
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
        $filename = __DIR__ . '/../../composer.json';
        $composerData = json_decode(file_get_contents($filename), true);
        $version = $composerData['version'] ?: '-';

        $variables = [
            'pluginVersion' => $version,
            'shopwareVersion' => $this->shopwareVersion,
            'feed' => $feed
        ];
        $content = $this->twig->render($this->resolveView('header.xml.twig', $feed), $variables);
        $this->writeContent($content, $feed);
    }

    private function generateMiddle(FeedEntity $feed): void
    {
        $content = $this->twig->render($this->resolveView('middle.xml.twig', $feed), ['feed' => $feed]);
        $this->writeContent($content, $feed);
    }
    private function generateFooter(FeedEntity $feed): void
    {
        $content = $this->twig->render($this->resolveView('footer.xml.twig', $feed), ['feed' => $feed]);
        $this->writeContent($content, $feed);
    }

    private function generateTopLevelCategories(FeedEntity $feed)
    {
        $rootCategoryEntity = new CategoryEntity();
        $rootCategoryEntity->setName('Shopware feed');
        $rootCategoryEntity->setTranslated(['name' => 'Shopware feed']);

        $content = $this->twig->render($this->resolveView('category.xml.twig', $feed), [
            'elementId' => 'root',
            'category' => $rootCategoryEntity,
            'rank' => $this->categoryRank,
            'feed' => $feed
        ]);
        $this->categoryRank++;

        $salesChannels = $this->getSalesChannelsFromFeed($feed);
        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $salesChannelCategory = new CategoryEntity();
            $salesChannelCategory->setName($salesChannel->getName());
            $salesChannelCategory->setTranslated(['name' => $salesChannel->getName()]);

            $content .= $this->twig->render($this->resolveView('category.xml.twig', $feed), [
                'elementId' => md5($salesChannel->getId()),
                'category' => $salesChannelCategory,
                'parentElementId' => 'root',
                'rank' => $this->categoryRank,
                'feed' => $feed
            ]);
            $this->categoryRank++;
        }

        foreach ($feed->getSalesChannelDomains() as $salesChannelDomain) {
            $salesChannelDomainCategory = new CategoryEntity();
            $salesChannelDomainCategory->setName($salesChannelDomain->getLanguage()->getTranslationCode()->getCode());
            $salesChannelDomainCategory->setTranslated(['name' => $salesChannelDomain->getLanguage()->getTranslationCode()->getCode()]);

            $content .= $this->twig->render($this->resolveView('category.xml.twig', $feed), [
                'elementId' => md5($salesChannelDomain->getSalesChannel()->getNavigationCategoryId() . '_' . $salesChannelDomain->getId()),
                'category' => $salesChannelDomainCategory,
                'parentElementId' => md5($salesChannelDomain->getSalesChannel()->getId()),
                'rank' => $this->categoryRank,
                'feed' => $feed
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
        $content = $this->twig->render($this->resolveView('category.xml.twig', $feed), [
            'domainId' => $domain->getId(),
            'domainUrl' => rtrim($domain->getUrl(), '/') . '/',
            'category' => $category,
            'rank' => $this->categoryRank,
            'feed' => $feed
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
    private function renderProducts($products, SalesChannelDomainEntity $domain, FeedEntity $feed, SalesChannelContext $salesChannelContext): void
    {
        $content = '';
        /** @var ProductEntity $product */
        foreach ($products as $product) {
            echo '.';
            $productId = ProductDataService::getTweakwiseProductId($product->getProductNumber(), $domain->getLanguage()->getTranslationCode()->getCode(), $domain->getId());
            if (!in_array($productId, $this->uniqueProductIds, true)) {
                $childFilter = null;
                $getVariants = false;
                if ($product->getParentId()) {
                    $criteria = new Criteria([$product->getParentId()]);
                    $criteria->addAssociation('children');

                    /** @var ProductEntity $parent */
                    $parent = $this->productRepository->search($criteria, $salesChannelContext->getContext())->first();
                    if ($parent->getChildCount() > 0) {
                        $configurationGroupConfigArray = [];
                        $listingConfig = $parent->getVariantListingConfig();
                        if ($listingConfig && !$listingConfig->getDisplayParent() && !$listingConfig->getMainVariantId()) {
                            $configurationGroupConfigArray = $listingConfig->getConfiguratorGroupConfig() ?: [];
                        }

                        $getVariants = true;
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

                        if ($getVariants === true) {
                            $childFilter = new EqualsFilter('parentId', $parent->getId());
                        }

                    }
                }

                if ($product->getChildCount() > 0) {
                    $getVariants = true;
                    $childFilter = new EqualsFilter('parentId', $product->getId());
                }

                $otherVariantsXml = '';

                if ($getVariants && $childFilter) {
                    $criteria = new Criteria();
                    $criteria->addFilter($childFilter);
                    $criteria->addFilter(new EqualsFilter('active', 1));
                    $criteria->addAssociation('options');
                    $criteria->addAssociation('options.group');
                    $criteria->addAssociation('properties');
                    $criteria->addAssociation('properties.group');

                    $criteria->setLimit(1);
                    $criteria->setOffset(0);
                    while ($childProducts = $this->productRepository->search($criteria, $salesChannelContext->getContext())->getElements()) {
                        /** @var ProductEntity $childProduct */
                        foreach ($childProducts as $childProduct) {
                            $otherVariantsXml .= $this->twig->render($this->resolveView('otherVariants.xml.twig', $feed), [
                                'variant' => $childProduct,
                            ]);

                            $otherVariantsXml .= $this->twig->render($this->resolveView('variantAttributes.xml.twig', $feed), [
                                'name' => 'sw-stock',
                                'value' => $childProduct->getStock(),
                            ]);

                            foreach ($childProduct->getOptions() as $option) {
                                $otherVariantsXml .= $this->twig->render($this->resolveView('variantAttributes.xml.twig', $feed), [
                                    'name' => $option->getGroup()->getTranslated()['name'],
                                    'value' => $option->getTranslated()['name'],
                                ]);
                            }
                            foreach ($childProduct->getProperties() as $property) {
                                $otherVariantsXml .= $this->twig->render($this->resolveView('variantAttributes.xml.twig', $feed), [
                                    'name' => $property->getGroup()->getTranslated()['name'],
                                    'value' => $property->getTranslated()['name'],
                                ]);
                            }
                        }
                        $criteria->setOffset($criteria->getOffset() + 1);
                        echo '.';
                    }
                }

                $categories = [];
                foreach ($product->getCategories() as $pCategory) {
                    if ($pCategory->getProductAssignmentType() === 'product') {
                        if (!array_key_exists($pCategory->getId(), $categories)) {
                            $categories[$pCategory->getId()] = $pCategory;
                        }
                    }
                }
                foreach ($product->getStreams() as $pStream) {
                    foreach ($pStream->getCategories() as $sCategory) {
                        if (!array_key_exists($sCategory->getId(), $categories)) {
                            $categories[$sCategory->getId()] = $sCategory;
                        }
                    }
                }

                $content .= $this->twig->render($this->resolveView('product.xml.twig', $feed), [
                    'categoryIdsInFeed' => array_unique($this->uniqueCategoryIds),
                    'categories' => $categories,
                    'domainId' => $domain->getId(),
                    'domainUrl' => rtrim($domain->getUrl(), '/') . '/',
                    'product' => $product,
                    'productId' => $productId,
                    'prices' => $this->getLowestAndHighestPrice($product, $salesChannelContext),
                    'otherVariantsXml' => $otherVariantsXml,
                    'lang' => $domain->getLanguage()->getTranslationCode()->getCode(),
                    'salesChannel' => $domain->getSalesChannel(),
                    'feed' => $feed
                ]);
                $this->uniqueProductIds[] = $productId;
            }
        }

        $this->writeContent($content, $feed);
    }

    private function getLowestAndHighestPrice(ProductEntity $product, SalesChannelContext $salesChannelContext): array
    {
        $prices = $product->getPrices();
        if (count($prices) < 2) {
            // no lowest and highest price when just 1 price is available
            return [
                'lowest' => [
                    'price_net' => 0,
                    'price_gross' => 0,
                    'list_price_net' => 0,
                    'list_price_gross' => 0,
                    'cheapest_price_net' => 0,
                    'cheapest_price_gross' => 0,
                    'quantity_start' => '',
                    'quantity_end' => '',
                ],
                'highest' => [
                    'price_net' => 0,
                    'price_gross' => 0,
                    'list_price_net' => 0,
                    'list_price_gross' => 0,
                    'cheapest_price_net' => 0,
                    'cheapest_price_gross' => 0,
                    'quantity_start' => '',
                    'quantity_end' => '',
                ],
            ];
        }
        $lowest = $highest = null;

        /** @var ProductPriceEntity $price */
        foreach ($prices as $price) {
            if (!$price->getRuleId() || !in_array($price->getRuleId(), $salesChannelContext->getRuleIds())) {
                continue;
            }
            if ($lowest === null) {
                $lowest = $price;
            } else {
                if ($price->getPrice()->first()->getNet() < $lowest->getPrice()->first()->getNet()) {
                    $lowest = $price;
                }
            }

            if ($highest === null) {
                $highest = $price;
            } else {
                if ($price->getPrice()->first()->getNet() > $highest->getPrice()->first()->getNet()) {
                    $highest = $price;
                }
            }
        }

        if ($lowest instanceof ProductPriceEntity && $highest instanceof ProductPriceEntity) {
            $lowestListPrice = $lowest->getPrice()->first()->getListPrice();
            $lowestRegulationPrice = $lowest->getPrice()->first()->getRegulationPrice();
            $highestListPrice = $highest->getPrice()->first()->getListPrice();
            $highestRegulationPrice = $highest->getPrice()->first()->getRegulationPrice();

            return [
                'lowest' => [
                    'price_net' => $lowest->getPrice()->first()->getNet(),
                    'price_gross' => $lowest->getPrice()->first()->getGross(),
                    'list_price_net' => ($lowestListPrice) ? $lowestListPrice->getNet(): 0,
                    'list_price_gross' => ($lowestListPrice) ? $lowestListPrice->getGross() : 0,
                    'cheapest_price_net' => ($lowestRegulationPrice) ? $lowestRegulationPrice->getNet() : 0,
                    'cheapest_price_gross' => ($lowestRegulationPrice) ? $lowestRegulationPrice->getGross() : 0,
                    'quantity_start' => $lowest->getQuantityStart(),
                    'quantity_end' => $lowest->getQuantityEnd(),
                ],
                'highest' => [
                    'price_net' => $highest->getPrice()->first()->getNet(),
                    'price_gross' => $highest->getPrice()->first()->getGross(),
                    'list_price_net' => ($highestListPrice) ? $highestListPrice->getNet() : 0,
                    'list_price_gross' => ($highestListPrice) ? $highestListPrice->getGross() : 0,
                    'cheapest_price_net' => ($highestRegulationPrice) ? $highestRegulationPrice->getNet() : 0,
                    'cheapest_price_gross' => ($highestRegulationPrice) ? $highestRegulationPrice->getGross() : 0,
                    'quantity_start' => $highest->getQuantityStart(),
                    'quantity_end' => $highest->getQuantityEnd(),
                ],
            ];
        } else {
            return [
                'lowest' => [
                    'price_net' => 0,
                    'price_gross' => 0,
                    'list_price_net' => 0,
                    'list_price_gross' => 0,
                    'cheapest_price_net' => 0,
                    'cheapest_price_gross' => 0,
                    'quantity_start' => '',
                    'quantity_end' => '',
                ],
                'highest' => [
                    'price_net' => 0,
                    'price_gross' => 0,
                    'list_price_net' => 0,
                    'list_price_gross' => 0,
                    'cheapest_price_net' => 0,
                    'cheapest_price_gross' => 0,
                    'quantity_start' => '',
                    'quantity_end' => '',
                ],
            ];
        }
    }

    private function resolveView(string $view, FeedEntity $feed): string
    {
        $folder = 'tweakwise';
        if ($feed->getType() !== 'full') {
            $folder = $folder . '/' . strtolower($feed->getType());
        }
        return $this->templateFinder->find('@Storefront/' . $folder . '/' . $view, true, '@RhaeTweakwise/' . $folder . '/' . $view);
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
            $navigation = $this->categoryLoader->load($rootCategory->getId(), $salesChannelContext, $rootCategory->getId(), 99, $feed->isIncludeHiddenCategories());

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
            $pathPrefix = rtrim($this->path, '/') . '/';
        }

        if ($temporarily) {
            return $pathPrefix . str_replace('{id}', $feedEntity->getId(), self::TMP_EXPORT_PATH);
        }
        return $pathPrefix . str_replace('{id}', $feedEntity->getId(), self::EXPORT_PATH);
    }
}
