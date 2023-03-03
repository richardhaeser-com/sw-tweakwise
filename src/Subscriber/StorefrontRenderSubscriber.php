<?php declare(strict_types=1);

namespace RH\Tweakwise\Subscriber;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorefrontRenderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            StorefrontRenderEvent::class => 'getTweakwiseConfig',
        ];
    }
    public function getTweakwiseConfig(StorefrontRenderEvent $event): void
    {
        $salesChannel = $event->getSalesChannelContext()->getSalesChannel();
        $customFields = $salesChannel->getCustomFields();
        /** @var Page $page */
        $page = $event->getParameters()['page'];
        $page->addExtensions(['customFields' => new ArrayStruct($customFields)]);
    }
}
