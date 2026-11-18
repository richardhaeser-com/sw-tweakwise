<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Core\Content\Frontend\FrontendCollection;
use RH\Tweakwise\Core\Content\Frontend\FrontendDefinition;
use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use RH\Tweakwise\Service\ProductDataService;
use RH\Tweakwise\Subscriber\StorefrontRenderSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class StorefrontRenderSubscriberTest extends TestCase
{
    #[DataProvider('functionsInitialisationSettings')]
    public function testSearchResultsReceiveFunctionsInitialisationSetting(bool $enabled): void
    {
        $context = Context::createDefaultContext();
        $frontend = new FrontendEntity();
        $frontend->setId(Uuid::randomHex());
        $frontend->setFunctionsInitialisationEnabled($enabled);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            FrontendDefinition::ENTITY_NAME,
            1,
            new FrontendCollection([$frontend]),
            null,
            new Criteria(),
            $context
        ));

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId(Uuid::randomHex());
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $salesChannelContext->method('getDomainId')->willReturn(Uuid::randomHex());
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $request = Request::create('/search-results');
        $request->attributes->set('_route', 'storefront.tweakwise.search');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $page = new Page();

        $subscriber = new StorefrontRenderSubscriber(
            $repository,
            $this->createMock(ProductDataService::class),
            $requestStack
        );
        $subscriber->getTweakwiseConfig(new StorefrontRenderEvent(
            '@Storefront/storefront/page/search.html.twig',
            ['page' => $page],
            $request,
            $salesChannelContext
        ));

        $configuration = $page->getExtension('twConfiguration');
        self::assertInstanceOf(ArrayStruct::class, $configuration);
        self::assertSame($enabled, $configuration->get('functionsInitialisationEnabled'));
    }

    public static function functionsInitialisationSettings(): iterable
    {
        yield 'disabled' => [false];
        yield 'enabled' => [true];
    }
}
