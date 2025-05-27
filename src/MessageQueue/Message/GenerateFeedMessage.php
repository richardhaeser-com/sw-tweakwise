<?php declare(strict_types=1);

namespace RH\Tweakwise\MessageQueue\Message;

use RH\Tweakwise\Core\Content\Feed\FeedEntity;

class GenerateFeedMessage
{
    public function __construct(private readonly FeedEntity $feed)
    {
    }

    /**
     * @return FeedEntity
     */
    public function getFeed(): FeedEntity
    {
        return $this->feed;
    }

}
