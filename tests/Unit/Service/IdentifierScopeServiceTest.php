<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Service\IdentifierScopeService;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class IdentifierScopeServiceTest extends TestCase
{
    public function testUsesDomainIdForLegacyStrategy(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(false);

        $this->assertSame('domain-id', (new IdentifierScopeService($systemConfigService))->getScopeForDomain($this->domain()));
    }

    public function testUsesSalesChannelLanguageAndCurrencyForContextStrategy(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(true);

        $this->assertSame('sales-channel-id_language-id_currency-id', (new IdentifierScopeService($systemConfigService))->getScopeForDomain($this->domain()));
    }

    public function testUsesContextStrategyWhenTheSettingHasNotBeenStoredYet(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(null);

        $this->assertSame('sales-channel-id_language-id_currency-id', (new IdentifierScopeService($systemConfigService))->getScopeForDomain($this->domain()));
    }

    private function domain(): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('domain-id');
        $domain->setSalesChannelId('sales-channel-id');
        $domain->setLanguageId('language-id');
        $domain->setCurrencyId('currency-id');

        return $domain;
    }
}
