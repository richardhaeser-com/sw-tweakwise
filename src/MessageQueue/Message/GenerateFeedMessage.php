<?php declare(strict_types=1);

namespace RH\Tweakwise\MessageQueue\Message;

use RH\Tweakwise\Core\Content\Feed\FeedEntity;

class GenerateFeedMessage
{
    private FeedEntity $feed;

    public function __construct(FeedEntity $feed)
    {
        $this->feed = $feed;
    }

    /**
     * @return FeedEntity
     */
    public function getFeed(): FeedEntity
    {
        return $this->feed;
    }

}
