<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Integration\Feed;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Core\Content\Feed\FeedEntity;
use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * Load test: seeds 50,000 products and verifies the feed generates completely
 * without errors. Run via the weekly CI schedule or manually — excluded from
 * the regular test run by the 'load' group.
 *
 * @group load
 */
#[Group('load')]
class FeedLoadTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const PRODUCT_COUNT = 50000;
    private const BATCH_SIZE    = 500;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testFeedGeneratesSuccessfullyWithLargeProductCatalogue(): void
    {
        $taxId = $this->getContainer()->get('tax.repository')
            ->searchIds((new Criteria())->setLimit(1), $this->context)
            ->firstId();

        $this->assertNotNull($taxId, 'A tax record must exist in the test database.');

        $seedStart = microtime(true);
        $this->seedProducts($taxId);
        $seedSeconds = microtime(true) - $seedStart;

        $feedStart = microtime(true);
        $xml       = $this->generateFeed();
        $feedSeconds = microtime(true) - $feedStart;

        $items = $xml->xpath('//item');
        $this->assertCount(
            self::PRODUCT_COUNT,
            $items,
            sprintf('Feed must contain all %d products.', self::PRODUCT_COUNT)
        );

        $this->writeSummary($seedSeconds, $feedSeconds, count($items));
    }

    private function writeSummary(float $seedSeconds, float $feedSeconds, int $itemCount): void
    {
        $summaryFile = $_SERVER['GITHUB_STEP_SUMMARY'] ?? null;
        if ($summaryFile === null) {
            return;
        }

        $throughput = $feedSeconds > 0 ? round($itemCount / $feedSeconds) : 'n/a';

        $summary = implode("\n", [
            '## Feed load test results',
            '',
            '| Metric | Value |',
            '|--------|-------|',
            sprintf('| Products seeded    | %s |', number_format($itemCount)),
            sprintf('| Seed time          | %.2f s |', $seedSeconds),
            sprintf('| Feed generate time | %.2f s |', $feedSeconds),
            sprintf('| Throughput         | %s products/s |', number_format((int) $throughput)),
            '',
        ]);

        file_put_contents($summaryFile, $summary, FILE_APPEND);
    }

    private function seedProducts(string $taxId): void
    {
        $salesChannelId = TestDefaults::SALES_CHANNEL;

        for ($batch = 0; $batch < self::PRODUCT_COUNT / self::BATCH_SIZE; $batch++) {
            $products = [];

            for ($i = 0; $i < self::BATCH_SIZE; $i++) {
                $number    = sprintf('LOAD-%06d', $batch * self::BATCH_SIZE + $i);
                $productId = Uuid::randomHex();

                $products[] = [
                    'id'            => $productId,
                    'productNumber' => $number,
                    'name'          => 'Load Test Product ' . $number,
                    'stock'         => 10,
                    'price'         => [[
                        'currencyId' => Defaults::CURRENCY,
                        'gross'      => 9.99,
                        'net'        => 9.99,
                        'linked'     => false,
                    ]],
                    'taxId'         => $taxId,
                    'visibilities'  => [[
                        'salesChannelId' => $salesChannelId,
                        'visibility'     => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ]],
                ];
            }

            $this->getContainer()->get('product.repository')->upsert($products, $this->context);
        }
    }

    private function generateFeed(): \SimpleXMLElement
    {
        $domain = $this->getContainer()
            ->get('sales_channel_domain.repository')
            ->search(
                (new Criteria())->addFilter(new EqualsFilter('salesChannelId', TestDefaults::SALES_CHANNEL))->setLimit(1),
                $this->context
            )
            ->first();

        $this->assertNotNull($domain, 'Default sales channel must have at least one domain.');

        $feedId = Uuid::randomHex();
        $this->getContainer()->get('s_plugin_rhae_tweakwise_feed.repository')->create([[
            'id'                  => $feedId,
            'name'                => 'Load Test Feed',
            'status'              => FeedEntity::STATUS_QUEUED,
            'interval'            => '0 3 * * *',
            'type'                => 'full',
            'limit'               => '500',
            'groupedProducts'     => false,
            'excludeChildren'     => false,
            'salesChannelDomains' => [['id' => $domain->getId()]],
        ]], $this->context);

        $feedCriteria = new Criteria([$feedId]);
        $feedCriteria->addAssociation('salesChannelDomains');
        $feedCriteria->addAssociation('salesChannelDomains.salesChannel');
        $feedCriteria->addAssociation('salesChannelDomains.language');
        $feedCriteria->addAssociation('salesChannelDomains.language.translationCode');

        /** @var FeedEntity $feed */
        $feed = $this->getContainer()
            ->get('s_plugin_rhae_tweakwise_feed.repository')
            ->search($feedCriteria, $this->context)
            ->first();

        /** @var FeedService $feedService */
        $feedService = $this->getContainer()->get(FeedService::class);
        $feedService->generateFeed($feed, $this->context);

        $xml = $feedService->readFeed($feed);
        $this->assertNotEmpty($xml, 'Generated feed XML must not be empty.');

        return new \SimpleXMLElement($xml);
    }
}
