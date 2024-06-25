<?php

namespace RH\Tweakwise\Service\ScheduledTask;

use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class GenerateFeedTaskHandler extends ScheduledTaskHandler
{
    protected FeedService $feedService;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        FeedService $feedService
    ) {
        $this->feedService = $feedService;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages (): iterable
    {
        return [ GenerateFeedTask::class ];
    }

    public function run(): void
    {
        $this->feedService->fixFeedRecords();
        $this->feedService->generateScheduledFeeds();
        $this->feedService->scheduleFeeds();
    }
}
