<?php declare(strict_types=1);

namespace RH\Tweakwise\Twig;

use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProductDataExtension extends AbstractExtension
{
    private ProductDataService $productDataService;

    public function __construct(ProductDataService $productDataService)
    {
        $this->productDataService = $productDataService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tweakwise_cross_sell_product_id', [$this, 'getCrossSellId'], ['needs_context' => true]),
            new TwigFunction('tweakwise_product_id_by_product_number', [$this, 'getCrossSellIdByProductNumber'], ['needs_context' => true]),
            new TwigFunction('tweakwise_product_number', [$this, 'getProductNumber'], ['needs_context' => true]),
            new TwigFunction('tweakwise_product_from_product_number', [$this, 'getProductFromProductNumber'], ['needs_context' => true]),
        ];
    }

    public function getCrossSellId(array $twigContext, ProductEntity $product, string $domainId): string
    {
        return ProductDataService::getTweakwiseProductId($product, $domainId);
    }

    public function getCrossSellIdByProductNumber(array $twigContext, string $productNumber, string $domainId): string
    {
        $product = $this->productDataService->getProductFromProductNumber($productNumber, $twigContext['context']->getContext());
        if ($product instanceof ProductEntity) {
            return ProductDataService::getTweakwiseProductId($this->productDataService->getProductShownInListing($product, $twigContext['context']), $domainId);
        }
        return '';
    }

    public function getProductFromProductNumber(array $twigContext, string $productNumber): ?ProductEntity
    {
        if (!\array_key_exists('context', $twigContext) || !$twigContext['context'] instanceof SalesChannelContext) {
            return null;
        }
        return $this->productDataService->getProductFromProductNumber($productNumber, $twigContext['context']->getContext());
    }

    public function getProductNumber(array $twigContext, ProductEntity $product): string
    {
        if (!\array_key_exists('context', $twigContext) || !$twigContext['context'] instanceof SalesChannelContext) {
            return $product->getProductNumber();
        }

        $product = $this->productDataService->getProductShownInListing($product, $twigContext['context']);
        return $product->getProductNumber();
    }
}
