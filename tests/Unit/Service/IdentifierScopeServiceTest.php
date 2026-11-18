<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Service\IdentifierScopeService;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Content\Product\ProductEntity;
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

    public function testUsesDomainIdWhenTheSettingHasNotBeenStoredYet(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(null);

        $this->assertSame('domain-id', (new IdentifierScopeService($systemConfigService))->getScopeForDomain($this->domain()));
    }

    public function testLegacyScopeProducesTheHistoricalProductIdentifier(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(false);
        $product = new ProductEntity();
        $product->setId('01a1629b7d41721d900b7203af2e12dc');

        $scope = (new IdentifierScopeService($systemConfigService))->getScopeForDomain($this->domain());

        $this->assertSame('462ab2db-01a1629b7d41721d900b7203af2e12dc', ProductDataService::getTweakwiseProductId($product, $scope));
    }

    public function testContextStrategyKeepsProductIdentifierWhenTheDomainIsReplaced(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('get')->willReturn(true);
        $product = new ProductEntity();
        $product->setId('01a1629b7d41721d900b7203af2e12dc');
        $service = new IdentifierScopeService($systemConfigService);

        $before = $service->getScopeForDomain($this->domain());
        $after = $service->getScopeForDomain($this->domain('replacement-domain-id'));

        $this->assertSame(
            ProductDataService::getTweakwiseProductId($product, $before),
            ProductDataService::getTweakwiseProductId($product, $after)
        );
    }

    private function domain(string $id = 'domain-id'): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId($id);
        $domain->setSalesChannelId('sales-channel-id');
        $domain->setLanguageId('language-id');
        $domain->setCurrencyId('currency-id');

        return $domain;
    }
}
