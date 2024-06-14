<?php

namespace RH\Tweakwise\Service\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class GenerateFeedTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'tweakwise.generate_feed';
    }

    public static function getDefaultInterval(): int
    {
        return 60; // 5 minutes
    }
}
