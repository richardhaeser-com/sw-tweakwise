<?php declare(strict_types=1);

namespace RH\Tweakwise\Service;

use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class IdentifierScopeService
{
    private const CONFIG_KEY = 'RhaeTweakwise.config.useContextBasedIdentifiers';

    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getScopeForDomain(SalesChannelDomainEntity $domain): string
    {
        if (!$this->usesContextBasedIdentifiers()) {
            return $domain->getId();
        }

        return $this->getContextScope($domain->getSalesChannelId(), $domain->getLanguageId(), $domain->getCurrencyId());
    }

    public function getScopeForContext(SalesChannelContext $context): string
    {
        if (!$this->usesContextBasedIdentifiers()) {
            return $context->getDomainId();
        }

        return $this->getContextScope($context->getSalesChannelId(), $context->getLanguageId(), $context->getCurrencyId());
    }

    private function usesContextBasedIdentifiers(): bool
    {
        return $this->systemConfigService->get(self::CONFIG_KEY) === true;
    }

    private function getContextScope(string $salesChannelId, string $languageId, string $currencyId): string
    {
        return implode('_', [$salesChannelId, $languageId, $currencyId]);
    }
}
