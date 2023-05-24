<?php declare(strict_types=1);

namespace RH\Tweakwise\Twig;

use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Service\FeedService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FeedExtension extends AbstractExtension
{
    private FeedService $feedService;

    public function __construct(FeedService $feedService)
    {
        $this->feedService = $feedService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tw_feed_date', [$this, 'getFeedCreationDate']),
        ];
    }

    public function getFeedCreationDate(FeedEntity $feed): int
    {
        return $this->feedService->getTimestampOfFeed($feed);
    }
}
