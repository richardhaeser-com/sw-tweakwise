<?php declare(strict_types=1);

namespace RH\Tweakwise\Command;

use RH\Tweakwise\Service\FeedService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
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
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        $section1 = $output->section();
        $section2 = $output->section();
        $section3 = $output->section();
        $section4 = $output->section();

        $progressBarSalesChannels = new ProgressBar($section1);
        $progressBarSalesChannels->setFormat(sprintf("Sales-channel: <info>%%sales-channel%%</info>\n%s\n\n", $progressBarSalesChannels->getFormatDefinition('normal')));

        $progressBarDomain = new ProgressBar($section2);
        $progressBarDomain->setFormat(sprintf("Domain: <info>%%domain%%</info>\n%s\n\n", $progressBarDomain->getFormatDefinition('normal')));

        $progressBarCategory = new ProgressBar($section3);
        $progressBarCategory->setFormat(sprintf("Category: <info>%%category%%</info>\n%s\n\n", $progressBarDomain->getFormatDefinition('normal')));

        $progressBarProducts = new ProgressBar($section4);
        $progressBarProducts->setFormat(sprintf("Products: <info>%%message%%</info>\n%s\n\n", $progressBarProducts->getFormatDefinition('normal')));

        $time_start = microtime(true);
        $this->feedService->generateFeed($progressBarSalesChannels, $progressBarDomain, $progressBarCategory, $progressBarProducts);

        $time_end = microtime(true);
        $execution_time = ceil($time_end - $time_start);

        $output->writeln('Creation of feed took ' . $execution_time . ' sec');
        $output->writeln('Feed is created on ' . date('d-m-Y H:i:s', $this->feedService->getTimestampOfFeed()));

        return Command::SUCCESS;
    }
}
