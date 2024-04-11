<?php  declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Feed;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;

class FeedEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $name;

    protected bool $includeHiddenCategories = false;

    /**
     * @var SalesChannelDomainCollection|null
     */
    protected $salesChannelDomains;

    /**
     * @var null|\DateTimeInterface
     */
    protected ?\DateTimeInterface $lastStartedAt;

    /**
     * @var null|\DateTimeInterface
     */
    protected ?\DateTimeInterface $lastGeneratedAt;

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

    /**
     * @return \DateTimeInterface|null
     */
    public function getLastGeneratedAt(): ?\DateTimeInterface
    {
        return $this->lastGeneratedAt;
    }

    /**
     * @param \DateTimeInterface|null $lastGeneratedAt
     */
    public function setLastGeneratedAt(?\DateTimeInterface $lastGeneratedAt): void
    {
        $this->lastGeneratedAt = $lastGeneratedAt;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getLastStartedAt(): ?\DateTimeInterface
    {
        return $this->lastStartedAt;
    }

    /**
     * @param \DateTimeInterface|null $lastStartedAt
     */
    public function setLastStartedAt(?\DateTimeInterface $lastStartedAt): void
    {
        $this->lastStartedAt = $lastStartedAt;
    }

    public function isIncludeHiddenCategories(): bool
    {
        return $this->includeHiddenCategories;
    }

    public function setIncludeHiddenCategories(bool $includeHiddenCategories): void
    {
        $this->includeHiddenCategories = $includeHiddenCategories;
    }

}
