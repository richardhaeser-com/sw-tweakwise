<?php declare(strict_types=1);

namespace RH\Tweakwise\Command;

use RH\Tweakwise\Service\FeedService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tweakwise:generate-feed')]
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
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        $time_start = microtime(true);

        $output->writeln('Checking for wrong feed records');
        $this->feedService->fixFeedRecords(true);
        $output->writeln('Generating scheduled feeds');
        $this->feedService->generateScheduledFeeds();
        $output->writeln('Schedule feeds');
        $this->feedService->scheduleFeeds();

        $time_end = microtime(true);
        $execution_time = ceil($time_end - $time_start);

        $output->writeln('Creation of feeds took ' . $execution_time . ' sec');

        return Command::SUCCESS;
    }
}
