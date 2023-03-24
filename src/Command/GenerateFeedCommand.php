<?php declare(strict_types=1);

namespace RH\Tweakwise\Command;

use RH\Tweakwise\Service\FeedService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFeedCommand extends Command
{
    protected static $defaultName = 'tweakwise:generate-feed';
    private FeedService $feedService;

    public function __construct(FeedService $feedService)
    {
        $this->feedService = $feedService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Generate Tweakwise feed to prepare for download');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $time_start = microtime(true);
        $this->feedService->generateFeed();
        $time_end = microtime(true);
        $execution_time = ceil($time_end - $time_start);

        $output->writeln('Creation of feed took ' . $execution_time . ' sec');
        $output->writeln('Feed is created on ' . date('d-m-Y H:i:s', $this->feedService->getTimestampOfFeed()));

        return Command::SUCCESS;
    }
}
