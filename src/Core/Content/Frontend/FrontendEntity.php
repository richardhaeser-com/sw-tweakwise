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
}
