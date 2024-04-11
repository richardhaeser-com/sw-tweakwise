<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use function array_key_exists;
use function is_array;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use function version_compare;

class ProductDataService
{
    private string $shopwareVersion;
    private EntityRepository $productRepository;

    public function __construct(EntityRepository $productRepository, string $shopwareVersion)
    {
        $this->shopwareVersion = $shopwareVersion;
        $this->productRepository = $productRepository;
    }

    public function getProductFromProductNumber(string $productNumber, Context $context): ?ProductEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('productNumber', $productNumber)
        );

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, $context)->first();
        return $product;
    }

    public function getProductShownInListing(ProductEntity $product, Context $context): ProductEntity
    {
        if (version_compare($this->shopwareVersion, '6.5.0', '>=')) {
            if ($product->getParentId()) {
                $criteria = new Criteria([$product->getParentId()]);
                /** @var ProductEntity $parentProduct */
                $parentProduct = $this->productRepository->search($criteria, $context)->first();
                if ($parentProduct instanceof ProductEntity) {
                    $configurationGroupConfigArray = [];
                    if (version_compare($this->shopwareVersion, '6.4.15', '>=')) {
                        /** @phpstan-ignore-next-line */
                        $listingConfig = $parentProduct->getVariantListingConfig();

                        if ($listingConfig) {
                            if ($listingConfig->getDisplayParent()) {
                                return $parentProduct;
                            }

                            if ($listingConfig->getMainVariantId()) {
                                /** @var ProductEntity $mainVariant */
                                $mainVariant = $this->productRepository->search(
                                    /** @phpstan-ignore-next-line */
                                    new Criteria([$listingConfig->getMainVariantId()]),
                                    $context
                                )->first();

                                return $mainVariant;
                            }

                            $configurationGroupConfigArray = $listingConfig->getConfiguratorGroupConfig() ?: [];
                        }
                    } else {
                        /** @phpstan-ignore-next-line */
                        $configurationGroupConfigArray = $parentProduct->getConfiguratorGroupConfig() ?: [];
                    }

                    $useParentProduct = true;
                    foreach ($configurationGroupConfigArray as $configurationGroupConfig) {
                        if (
                            is_array($configurationGroupConfig)
                            && array_key_exists('expressionForListings', $configurationGroupConfig)
                            && $configurationGroupConfig['expressionForListings'] === true
                        ) {
                            $useParentProduct = false;
                            break;
                        }
                    }
                    if ($useParentProduct) {
                        if (version_compare($this->shopwareVersion, '6.4.15', '>=')) {
                            $criteria = new Criteria();
                            $criteria->addFilter(
                                new EqualsFilter('parentId', $parentProduct->getId())
                            );

                            /** @var ProductEntity $firstVariant */
                            $firstVariant = $this->productRepository->search(
                                $criteria,
                                $context
                            )->first();
                            return $firstVariant;
                        }

                        return $parentProduct;
                    }
                }
            }
        }

        return $product;
    }
}
