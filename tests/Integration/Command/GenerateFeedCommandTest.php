<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Integration\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Bundle\FrameworkBundle\Console\Application;

class GenerateFeedCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public function testConsoleCommandExists(): void
    {
        $container = $this->getContainer();

        /** @var FeedService|MockObject $feedServiceMock */
        $feedServiceMock = $this->createMock(FeedService::class);
        $container->set(FeedService::class, $feedServiceMock);

        $application = new Application($this->getKernel());
        $application->setAutoExit(false);

        self::assertTrue($application->has('tweakwise:generate-feed'));
    }
}
