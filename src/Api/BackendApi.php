<?php declare(strict_types=1);

namespace RH\Tweakwise\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Content\Product\ProductEntity;

class BackendApi
{
    private readonly Client $client;
    public $apiUrl = 'https://navigator-api.tweakwise.com';
    public function __construct(private readonly string $instanceKey, private readonly string $accessToken)
    {
        $this->client = new Client();
    }

    public function getProductData(ProductEntity $product, string $domainId): array
    {
        $productId = ProductDataService::getTweakwiseProductId($product, $domainId);
        try {
            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/item/' . $productId,
                [
                    'headers' => [
                        'TWN-InstanceKey' => $this->instanceKey,
                        'TWN-Authentication' => $this->accessToken,
                        'accept' => 'application/json',
                    ],
                ]
            );
        } catch (GuzzleException $exception) {
            return ['error' => true, 'code' => $exception->getCode(), 'message' => $exception->getMessage()];
        }

        $data = json_decode($response->getBody()->getContents(), true);
        return $data;
    }
}
