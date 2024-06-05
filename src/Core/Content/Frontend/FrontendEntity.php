<?php  declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Frontend;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;

class FrontendEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $name;

    protected ?string $token;

    protected ?string $integration;

    protected ?string $wayOfSearch;

    protected ?string $checkoutSales;

    protected ?string $checkoutSalesFeaturedProductsId;

    protected ?string $checkoutSalesRecommendationsGroupKey;

    protected int $productsDesktop = 3;
    protected int $productsTablet = 2;
    protected int $productsMobile = 1;

    protected string $paginationType;

    /**
     * @var SalesChannelDomainCollection|null
     */
    protected $salesChannelDomains;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSalesChannelDomains(): ?SalesChannelDomainCollection
    {
        return $this->salesChannelDomains;
    }

    public function setSalesChannelDomains(?SalesChannelDomainCollection $salesChannelDomains): void
    {
        $this->salesChannelDomains = $salesChannelDomains;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getIntegration(): ?string
    {
        return $this->integration;
    }

    public function setIntegration(?string $integration): void
    {
        $this->integration = $integration;
    }

    public function getWayOfSearch(): ?string
    {
        return $this->wayOfSearch;
    }

    public function setWayOfSearch(?string $wayOfSearch): void
    {
        $this->wayOfSearch = $wayOfSearch;
    }

    public function getCheckoutSales(): ?string
    {
        return $this->checkoutSales;
    }

    public function setCheckoutSales(?string $checkoutSales): void
    {
        $this->checkoutSales = $checkoutSales;
    }

    public function getCheckoutSalesFeaturedProductsId(): ?string
    {
        return $this->checkoutSalesFeaturedProductsId;
    }

    public function setCheckoutSalesFeaturedProductsId(?string $checkoutSalesFeaturedProductsId): void
    {
        $this->checkoutSalesFeaturedProductsId = $checkoutSalesFeaturedProductsId;
    }

    public function getCheckoutSalesRecommendationsGroupKey(): ?string
    {
        return $this->checkoutSalesRecommendationsGroupKey;
    }

    public function setCheckoutSalesRecommendationsGroupKey(?string $checkoutSalesRecommendationsGroupKey): void
    {
        $this->checkoutSalesRecommendationsGroupKey = $checkoutSalesRecommendationsGroupKey;
    }

    public function getProductsDesktop(): int
    {
        return $this->productsDesktop;
    }

    public function setProductsDesktop(int $productsDesktop): void
    {
        $this->productsDesktop = $productsDesktop;
    }

    public function getProductsTablet(): int
    {
        return $this->productsTablet;
    }

    public function setProductsTablet(int $productsTablet): void
    {
        $this->productsTablet = $productsTablet;
    }

    public function getProductsMobile(): int
    {
        return $this->productsMobile;
    }

    public function setProductsMobile(int $productsMobile): void
    {
        $this->productsMobile = $productsMobile;
    }

    public function getPaginationType(): string
    {
        return $this->paginationType;
    }

    public function setPaginationType(string $paginationType): void
    {
        $this->paginationType = $paginationType;
    }
}
