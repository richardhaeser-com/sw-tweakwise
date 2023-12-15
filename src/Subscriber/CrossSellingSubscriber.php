<?php declare(strict_types=1);

namespace RH\Tweakwise\Subscriber;

use Shopware\Core\Content\Product\Events\ProductCrossSellingsLoadedEvent;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CrossSellingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductCrossSellingsLoadedEvent::class => 'onCrossSellingsLoaded'
        ];
    }

    public function onCrossSellingsLoaded(ProductCrossSellingsLoadedEvent $event)
    {
        /** @var CrossSellingElementCollection $crossSellings */
        $crossSellings = $event->getCrossSellings();
        foreach ($crossSellings as $crossSelling) {
            if ($crossSelling->getCrossSelling()->getType() === 'tweakwiseRecommendation') {
                $crossSelling->setTotal(1);
            }
        }
    }
}
