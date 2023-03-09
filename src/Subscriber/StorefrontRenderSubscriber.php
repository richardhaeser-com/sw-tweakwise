<?php declare(strict_types=1);

namespace RH\Tweakwise\Subscriber;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function array_key_exists;

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
        $parameters = $event->getParameters();
        if (is_array($parameters) && array_key_exists('page', $parameters)) {
            /** @var Page $page */
            $page = $event->getParameters()['page'];
            if ($page instanceof Page) {
                $page->addExtensions(['customFields' => new ArrayStruct($customFields)]);
            }
        }
    }
}
