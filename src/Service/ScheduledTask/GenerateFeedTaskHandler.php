<?php

namespace RH\Tweakwise\Service\ScheduledTask;

use Psr\Log\LoggerInterface;
use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: GenerateFeedTask::class)]
class GenerateFeedTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected FeedService $feedService,
        LoggerInterface $exceptionLogger,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }
    public function run(): void
    {
        $this->feedService->fixFeedRecords();
        $this->feedService->generateScheduledFeeds();
        $this->feedService->scheduleFeeds();
    }
}
