<?php declare(strict_types=1);

namespace RH\Tweakwise\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Symfony\Component\Routing\RouterInterface;
use function array_key_exists;

class BackendApi
{
    private readonly Client $client;
    public $apiUrl = 'https://navigator-api.tweakwise.com';
    public function __construct(private readonly string $instanceKey, private readonly string $accessToken, private RouterInterface $router)
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

    public function getCategoryData(CategoryEntity $category, string $domainId): array
    {
        $key = md5($category->getId() . '_' . $domainId);

        try {
            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/category/getbykey/' . $key,
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

    public function syncProductData(ProductEntity $product, FrontendEntity $frontend, ?ProductEntity $parent, array $customFieldNames): array
    {
        $domain = $frontend->getSalesChannelDomains()->first();
        $domainId = $domain->getId();

        $categories = [];
        $productId = ProductDataService::getTweakwiseProductId($product, $domainId);
        try {
            $productData = $this->getProductData($product, $domainId);
            foreach ($product->getCategories() as $category) {
                $catData = $this->getCategoryData($category, $domainId);
                if (array_key_exists('CategoryId', $catData) && (int)$catData['CategoryId']) {
                    $categories[] = $catData['CategoryId'];
                }
            }
        } catch (\Exception $e) {}

        try {
            $data = [];
            $backendSyncProperties = $frontend->getBackendSyncProperties();
            // Main properties
            foreach ($backendSyncProperties['main'] as $propertyToSync => $doSync) {
                if (!$doSync) {
                    continue;
                }

                switch ($propertyToSync) {
                    case 'name':
                        $property = 'Name';
                        $value = $product?->getTranslation('name') ?: $parent?->getTranslation('name') ?: '';
                        break;
                    case 'unitPrice':
                        /** @var CalculatedPrice $price */
                        $price = $product->calculatedPrice;
                        if ((int)$product->calculatedPrices->count()) {
                            $price = $product->calculatedPrices->last();
                        }

                        if (!$price) {
                            /** @var CalculatedPrice $price */
                            $price = $parent->calculatedPrice;
                            if ((int)$parent->calculatedPrices->count()) {
                                $price = $parent->calculatedPrices->last();
                            }

                        }
                        $property = 'Price';
                        $value = $price?->getUnitPrice() ?: 0;
                        break;
                    case 'availableStock':
                        $property = 'Stock';
                        $value = $product?->getAvailableStock() ?: $parent?->getAvailableStock() ?: 0;
                        break;
                    case 'manufacturer':
                        $property = 'Brand';
                        $value = $product?->getManufacturer()?->getTranslation('name') ?: $parent?->getManufacturer()?->getTranslation('name') ?: '';
                        break;
                    case 'url':
                        $property = 'Url';
                        $value = rtrim($domain->getUrl(), '/') . '/' . $this->getProductUrl($product);
                        break;
                    case 'images':
                        if ($product->getCover()?->getMedia()?->getUrl()) {
                            $property = 'Image';
                            $value = $product->getCover()->getMedia()->getUrl();
                            break;
                        }
                        $property = '';
                        $value = '';
                        break;
                    case 'categories':
                        if ($categories) {
                            $property = 'Categories';
                            $value = $categories;
                            break;
                        }
                    default:
                        $property = '';
                        $value = '';
                }

                if ($property) {
                    $data[$property] = $value;
                }
            }

            $tmpAttributes = [];
            foreach ($backendSyncProperties['properties'] as $propertyToSync => $doSync) {
                if (!$doSync) {
                    continue;
                }

                foreach ($product->getProperties() as $property) {
                    if ($property->getGroupId() === $propertyToSync) {
                        $tmpAttributes[$property->getGroup()->getTranslation('name')][] = $property->getTranslation('name');
                    }
                }
            }

            $customFields = $product->getCustomFields();
            foreach ($backendSyncProperties['customFields'] as $customFieldToSync => $doSync) {
                if (!$doSync) {
                    continue;
                }
                if (array_key_exists($customFieldToSync, $customFieldNames)) {
                    if (is_array($customFields) && array_key_exists($customFieldToSync, $customFields)) {
                        $tmpAttributes[$customFieldNames[$customFieldToSync]][] = $customFields[$customFieldToSync];
                    }

                }
            }

            $attributes = [];
            $attributes[] = [
                'Key' => 'item_type',
                'Values' => ['product']
            ];
            foreach ($tmpAttributes as $groupName => $values) {
                $attributes[] = [
                    'Key' => $groupName,
                    'Values' => $values
                ];
            }
            $data['Attributes'] = $attributes;
            $data['Type'] = 'product';

            $response = null;
            if (array_key_exists('error', $productData) && $productData['error'] && array_key_exists('code', $productData) && $productData['code'] === 404) {
                $data['articleNumber'] = $productId;

                // new product for tweakwise
                $response = $this->client->request(
                    'POST',
                    $this->apiUrl . '/item',
                    [
                        'body' => json_encode($data),
                        'headers' => [
                            'TWN-InstanceKey' => $this->instanceKey,
                            'TWN-Authentication' => $this->accessToken,
                            'accept' => 'application/json',
                            'content-type' => 'text/json',
                        ],
                    ]
                );
            } else{
                // update product in tweakwise
                $response = $this->client->request(
                    'PATCH',
                    $this->apiUrl . '/item/' . $productId,
                    [
                        'body' => json_encode($data),
                        'headers' => [
                            'TWN-InstanceKey' => $this->instanceKey,
                            'TWN-Authentication' => $this->accessToken,
                            'accept' => 'application/json',
                            'content-type' => 'text/json',
                        ],
                    ]
                );
            }

            if ($response !== null) {
                $data = json_decode($response->getBody()->getContents(), true);
            }
        } catch (GuzzleException $exception) {
            return ['error' => true, 'code' => $exception->getCode(), 'message' => $exception->getMessage()];
        }

        return ['error' => false, 'data' => $data];
    }

    private function getProductUrl(ProductEntity $product): string
    {
        foreach ($product->getSeoUrls() as $seoUrl) {
            if ($seoUrl->getIsCanonical()) {
                return $seoUrl->getSeoPathInfo();
            }
        }

        return $this->router->generate('frontend.detail.page', [
            'productId' => $product->getId(),
        ]);
    }
}
