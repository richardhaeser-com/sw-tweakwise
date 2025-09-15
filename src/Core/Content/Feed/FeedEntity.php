<?php  declare(strict_types=1);

namespace RH\Tweakwise\Core\Content\Feed;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;

class FeedEntity extends Entity
{
    const STATUS_QUEUED = 'queued';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';

    use EntityIdTrait;

    protected ?string $name = null;

    protected bool $includeHiddenCategories = false;

    /**
     * @var SalesChannelDomainCollection|null
     */
    protected $salesChannelDomains;

    /**
     * @var null|\DateTimeInterface
     */
    protected ?\DateTimeInterface $lastStartedAt = null;

    /**
     * @var null|\DateTimeInterface
     */
    protected ?\DateTimeInterface $lastGeneratedAt = null;

    /**
     * @var null|\DateTimeInterface
     */
    protected ?\DateTimeInterface $nextGenerationAt = null;

    protected ?string $status = null;

    protected ?string $interval = null;

    protected ?string $type = null;

    protected ?string $importTaskUrl = null;

    protected bool $excludeChildren = false;
    protected bool $excludeReviews = false;
    protected bool $excludeTags = false;
    protected bool $excludeOptions = false;
    protected bool $excludeProperties = false;
    protected bool $groupedProducts = false;
    protected bool $includeCustomFields = true;

    protected bool $respectHideCloseoutProductsWhenOutOfStock = false;

    protected ?string $limit = '10';

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

    public function getNextGenerationAt(): ?\DateTimeInterface
    {
        return $this->nextGenerationAt;
    }

    public function setNextGenerationAt(?\DateTimeInterface $nextGenerationAt): void
    {
        if ($nextGenerationAt === null) {
            $nextGenerationAt = new \DateTime();
        }
        $this->nextGenerationAt = $nextGenerationAt;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getInterval(): ?string
    {
        return $this->interval;
    }

    public function setInterval(?string $interval): void
    {
        $this->interval = $interval;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getImportTaskUrl(): ?string
    {
        return $this->importTaskUrl;
    }

    public function setImportTaskUrl(?string $importTaskUrl): void
    {
        $this->importTaskUrl = $importTaskUrl;
    }

    public function isExcludeChildren(): bool
    {
        return $this->excludeChildren;
    }

    public function setExcludeChildren(bool $excludeChildren): void
    {
        $this->excludeChildren = $excludeChildren;
    }

    public function isExcludeReviews(): bool
    {
        return $this->excludeReviews;
    }

    public function setExcludeReviews(bool $excludeReviews): void
    {
        $this->excludeReviews = $excludeReviews;
    }

    public function isExcludeTags(): bool
    {
        return $this->excludeTags;
    }

    public function setExcludeTags(bool $excludeTags): void
    {
        $this->excludeTags = $excludeTags;
    }

    public function isExcludeOptions(): bool
    {
        return $this->excludeOptions;
    }

    public function setExcludeOptions(bool $excludeOptions): void
    {
        $this->excludeOptions = $excludeOptions;
    }

    public function isExcludeProperties(): bool
    {
        return $this->excludeProperties;
    }

    public function setExcludeProperties(bool $excludeProperties): void
    {
        $this->excludeProperties = $excludeProperties;
    }

    public function isGroupedProducts(): bool
    {
        return $this->groupedProducts;
    }

    public function setGroupedProducts(bool $groupedProducts): void
    {
        $this->groupedProducts = $groupedProducts;
    }

    public function getLimit(): ?string
    {
        return $this->limit;
    }

    public function setLimit(?string $limit): void
    {
        $this->limit = $limit;
    }

    public function isIncludeCustomFields(): bool
    {
        return $this->includeCustomFields;
    }

    public function setIncludeCustomFields(bool $includeCustomFields): void
    {
        $this->includeCustomFields = $includeCustomFields;
    }

    public function isRespectHideCloseoutProductsWhenOutOfStock(): bool
    {
        return $this->respectHideCloseoutProductsWhenOutOfStock;
    }

    public function setRespectHideCloseoutProductsWhenOutOfStock(bool $respectHideCloseoutProductsWhenOutOfStock): void
    {
        $this->respectHideCloseoutProductsWhenOutOfStock = $respectHideCloseoutProductsWhenOutOfStock;
    }

}
