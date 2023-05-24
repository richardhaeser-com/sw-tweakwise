<?php declare(strict_types=1);

namespace RH\Tweakwise\Command;

use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function date;

class GenerateFeedCommand extends Command
{
    protected static $defaultName = 'tweakwise:generate-feed';
    private FeedService $feedService;
    private EntityRepositoryInterface $feedRepository;

    public function __construct(FeedService $feedService, EntityRepositoryInterface $feedRepository)
    {
        $this->feedService = $feedService;
        $this->feedRepository = $feedRepository;
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
        $criteria = new Criteria();
        $criteria->addAssociation('salesChannelDomains');
        $criteria->addAssociation('salesChannelDomains.salesChannel');
        $criteria->addAssociation('salesChannelDomains.language');
        $criteria->addAssociation('salesChannelDomains.language.translationCode');
        $context = Context::createDefaultContext();

        $feeds = $this->feedRepository->search($criteria, $context)->getEntities();
        /** @var FeedEntity $feed */
        foreach ($feeds as $feed) {
            $this->feedService->generateFeed($feed);
            $output->writeln('Feed "' . $feed->getName() . '" is created on ' . date('d-m-Y H:i:s', $this->feedService->getTimestampOfFeed($feed)));
        }

        $time_end = microtime(true);
        $execution_time = ceil($time_end - $time_start);

        $output->writeln('Creation of feeds took ' . $execution_time . ' sec');

        return Command::SUCCESS;
    }
}
