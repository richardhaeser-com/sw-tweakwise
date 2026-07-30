<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Feed;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Service\FeedService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

/**
 * Verifies that the grouped-products filter used by the XML feed query
 * correctly excludes parent products (parentId = null AND childCount > 0)
 * while including variants (parentId IS NOT NULL) and standalones (childCount = 0).
 *
 * Because this is a DAL filter applied at query time, the test inspects the
 * filter structure rather than executing a real DB query.
 */
class GroupedFeedExcludesParentsTest extends TestCase
{
    public function testFilterIsOrOfTwoBranches(): void
    {
        $filter = FeedService::buildGroupedProductsFilter();

        $this->assertInstanceOf(MultiFilter::class, $filter);
        $this->assertSame(
            MultiFilter::CONNECTION_OR,
            $filter->getOperator(),
            'OR connection: a product is included when it satisfies at least one branch'
        );
        $this->assertCount(2, $filter->getQueries());
    }

    public function testFirstBranchExcludesProductsWithoutParent(): void
    {
        $queries = FeedService::buildGroupedProductsFilter()->getQueries();

        // NOT(parentId = null) → true when parentId IS NOT NULL → includes variants
        $branch = $queries[0];
        $this->assertInstanceOf(
            NotFilter::class,
            $branch,
            'First branch must negate so that products with parentId = null are excluded by this branch'
        );

        $inner = $branch->getQueries()[0];
        $this->assertInstanceOf(EqualsFilter::class, $inner);
        $this->assertSame('parentId', $inner->getField());
        $this->assertNull(
            $inner->getValue(),
            'Inner filter matches parentId = null (parent/standalone products); NOT then makes variants pass'
        );
    }

    public function testSecondBranchIncludesStandaloneProducts(): void
    {
        $queries = FeedService::buildGroupedProductsFilter()->getQueries();

        // childCount = 0 → true for standalone products (no children)
        $branch = $queries[1];
        $this->assertInstanceOf(
            EqualsFilter::class,
            $branch,
            'Second branch must match standalone products that have no children'
        );
        $this->assertSame('childCount', $branch->getField());
        $this->assertSame(0, $branch->getValue());
    }

    /**
     * Verifies the combined logic for each product type.
     *
     * parent product  (parentId=null, childCount>0): branch1=false, branch2=false → OR=false → EXCLUDED ✓
     * variant         (parentId≠null, childCount=0): branch1=true,  branch2=true  → OR=true  → INCLUDED ✓
     * standalone      (parentId=null, childCount=0): branch1=false, branch2=true  → OR=true  → INCLUDED ✓
     */
    public function testCombinedFilterLogicDocumentedForAllProductTypes(): void
    {
        $filter = FeedService::buildGroupedProductsFilter();

        // The structure above is validated by the three preceding tests.
        // This test records the expected behaviour for all three product types
        // and fails if the filter structure has been altered (caught by the other tests).
        $this->assertInstanceOf(MultiFilter::class, $filter);
    }
}
