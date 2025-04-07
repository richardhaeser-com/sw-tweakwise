<?php declare(strict_types=1);

namespace RH\Tweakwise\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Content\Product\ProductEntity;

class ProductDataServiceTest extends TestCase
{
    public function testGetTweakwiseProductId()
    {
        $product = new ProductEntity();
        $product->setId('1');

        $this->assertEquals('83dcefb7-1', ProductDataService::getTweakwiseProductId($product, '1'));
    }
}
