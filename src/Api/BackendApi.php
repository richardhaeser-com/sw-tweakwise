<?php declare(strict_types=1);

namespace RH\Tweakwise\Api;

use function array_key_exists;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Symfony\Component\Routing\RouterInterface;

class BackendApi
{
    private readonly Client $client;
    public $apiUrl = 'https://navigator-api.tweakwise.com';
    public $frontendApiUrl = 'https://gateway.tweakwisenavigator.com';
    public function __construct(private readonly string $instanceKey, private readonly string $accessToken, private RouterInterface $router, ?Client $client = null)
    {
        $this->client = $client ?? new Client();
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

    public function getFilterAttributes(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/attribute',
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

    public function getCategoryTree(int $totalLevels = 10): array
    {
        $categories = [];
        try {
            $root = $this->fetchCategoryTreeNode(null);
            $this->appendNodeRecursive($root, $categories, '', 1, $totalLevels);
        } catch (GuzzleException $exception) {
            return ['error' => true, 'code' => $exception->getCode(), 'message' => $exception->getMessage()];
        }

        return $categories;
    }

    private function fetchCategoryTreeNode(?int $id): array
    {
        if ($id === null) {
            $url = $this->apiUrl . '/category/tree?type=Category';
        } else {
            $url = $this->apiUrl . '/category/tree/children?type=Category&id=' . $id;
        }
        $response = $this->client->request('GET', $url, [
            'headers' => [
                'TWN-InstanceKey' => $this->instanceKey,
                'TWN-Authentication' => $this->accessToken,
                'accept' => 'application/json',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return is_array($data) ? $data : [];
    }

    private function appendNodeRecursive(array $node, array &$result, string $previousName, int $level, int $maxLevel): void
    {
        if (!isset($node['Id'], $node['Name'])) {
            return;
        }
        $name = $previousName ? $previousName . ' > ' . $node['Name'] : $node['Name'];
        $result[$node['Id']] = $name;

        if ($level >= $maxLevel) {
            return;
        }

        if (isset($node['HasChildren'])) {
            $childNodes = $this->fetchCategoryTreeNode($node['Id']);
            foreach ($childNodes as $childNode) {
                $this->appendNodeRecursive($childNode, $result, $name, $level + 1, $maxLevel);
            }
        }
    }
    public function syncProductData(ProductEntity $product, FrontendEntity $frontend, ?ProductEntity $parent, array $customFieldNames, bool $groupedProducts = true): array
    {
        $productData = null;
        $domain = $frontend->getSalesChannelDomains()->first();
        $domainId = $domain->getId();

        $categories = [];
        $productId = ProductDataService::getTweakwiseProductId($product, $domainId);
        try {
            $productData = $this->getProductData($product, $domainId);
            foreach ($product->getCategories() ?? [] as $category) {
                $catData = $this->getCategoryData($category, $domainId);
                if (array_key_exists('CategoryId', $catData) && (int)$catData['CategoryId']) {
                    $categories[] = $catData['CategoryId'];
                }
            }
            foreach ($product->getStreams() ?? [] as $pStream) {
                foreach ($pStream->getCategories() as $sCategory) {
                    if ($sCategory->getProductAssignmentType() === 'product_stream') {
                        $catData = $this->getCategoryData($sCategory, $domainId);
                        if (array_key_exists('CategoryId', $catData) && (int)$catData['CategoryId']) {
                            $categories[] = $catData['CategoryId'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }

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
                        $value = $product->getTranslation('name') ?: $parent?->getTranslation('name') ?? '';
                        break;
                    case 'unitPrice':
                        /** @var CalculatedPrice $price */
                        $price = $product->calculatedPrice;
                        if ((int)$product->calculatedPrices->count()) {
                            $price = $product->calculatedPrices->last();
                        }
                        // No parent fallback for price — the XML feed template uses the product's
                        // own calculatedPrice only (no parent fallback in product.xml.twig).
                        // Shopware's price calculator always assigns a price to every loaded product,
                        // so a missing calculated price is not a production scenario.
                        $property = 'Price';
                        $value = $price?->getUnitPrice() ?: 0;
                        break;
                    case 'availableStock':
                        $property = 'Stock';
                        // Use ?? (null-coalescing) not ?: so that stock=0 is kept as-is and not
                        // treated as "no value", which would otherwise send the parent's stock for
                        // out-of-stock variants — diverging from what the XML feed emits.
                        $value = $product->getAvailableStock() ?? $parent?->getAvailableStock() ?? 0;
                        break;
                    case 'manufacturer':
                        $property = 'Brand';
                        $value = $product->getManufacturer()?->getTranslation('name') ?: $parent?->getManufacturer()?->getTranslation('name') ?: '';
                        break;
                    case 'url':
                        $property = 'Url';
                        $value = rtrim($domain->getUrl(), '/') . '/' . $this->getProductUrl($product);
                        break;
                    case 'images':
                        if ($product->getCover()?->getMedia()?->getUrl()) {
                            $property = 'Image';
                            $value = $product->getCover()->getMedia()->getUrl();
                            $width = 0;
                            foreach ($product->getCover()->getMedia()->getThumbnails() ?? [] as $thumbnail) {
                                if ($thumbnail->getWidth() > $width) {
                                    $value = $thumbnail->getUrl();
                                    $width = $thumbnail->getWidth();
                                }
                            }
                            break;
                        }
                        $property = '';
                        $value = '';
                        break;
                    case 'categories':
                        $property = 'Categories';
                        $value = $categories;
                        break;
                    case 'groupcode':
                        if (!$groupedProducts) {
                            $property = '';
                            $value = '';
                            break;
                        }
                        $property = 'GroupCode';
                        $value = $parent?->getProductNumber() ?: $product->getProductNumber();
                        break;
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
                'Key'    => 'item_type',
                'Values' => ['product'],
            ];
            foreach ($tmpAttributes as $groupName => $values) {
                $attributes[] = [
                    'Key'    => $groupName,
                    'Values' => $values,
                ];
            }

            $swAttributesConfig = $backendSyncProperties['swAttributes'] ?? [];
            $swEnabled = static function (string $key) use ($swAttributesConfig): bool {
                // Opt-in: an attribute is only synced when explicitly enabled in settings.
                return (bool) ($swAttributesConfig[$key] ?? false);
            };

            // Boolean flag attributes — mirrors product.xml.twig
            if ($swEnabled('sw-free-shipping')) {
                $attributes[] = [
                    'Key'    => 'sw-free-shipping',
                    'Values' => [$product->getShippingFree() ? 'true' : 'false'],
                ];
            }
            if ($swEnabled('sw-is-topseller')) {
                $attributes[] = [
                    'Key'    => 'sw-is-topseller',
                    'Values' => [$product->getMarkAsTopseller() ? 'true' : 'false'],
                ];
            }
            if ($swEnabled('sw-is-closeout')) {
                $attributes[] = [
                    'Key'    => 'sw-is-closeout',
                    'Values' => [$product->getIsCloseout() ? 'true' : 'false'],
                ];
            }

            // sw-has-discount: list price exists and is higher than unit price
            $syncPrice = $product->calculatedPrice;
            if ((int) $product->calculatedPrices->count()) {
                $syncPrice = $product->calculatedPrices->last();
            }
            $hasDiscount = $syncPrice?->getListPrice() !== null
                && $syncPrice->getListPrice()->getPrice() > $syncPrice->getUnitPrice();
            if ($swEnabled('sw-has-discount')) {
                $attributes[] = [
                    'Key'    => 'sw-has-discount',
                    'Values' => [$hasDiscount ? 'true' : 'false'],
                ];
            }

            // sw-new: available on SalesChannelProductEntity only
            $isNew = $product instanceof \Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity
                ? $product->isNew()
                : false;
            if ($swEnabled('sw-new')) {
                $attributes[] = [
                    'Key'    => 'sw-new',
                    'Values' => [$isNew ? 'true' : 'false'],
                ];
            }

            // sw-label: mirrors the feed template priority (soldout > topseller > discount > new)
            if ($swEnabled('sw-label')) {
                if ($product->getAvailableStock() < 1) {
                    $label = 'soldout';
                } elseif ($product->getMarkAsTopseller()) {
                    $label = 'topseller';
                } elseif ($hasDiscount) {
                    $label = 'discount';
                } elseif ($isNew) {
                    $label = 'new';
                } else {
                    $label = '';
                }
                $attributes[] = [
                    'Key'    => 'sw-label',
                    'Values' => [$label],
                ];
            }

            // Product info attributes — mirrors product.xml.twig
            if ($swEnabled('sw-id')) {
                $attributes[] = [
                    'Key'    => 'sw-id',
                    'Values' => [$product->getId()],
                ];
            }
            if ($swEnabled('sw-product-number')) {
                $attributes[] = [
                    'Key'    => 'sw-product-number',
                    'Values' => [$product->getProductNumber()],
                ];
            }
            if ($swEnabled('sw-ean')) {
                $attributes[] = [
                    'Key'    => 'sw-ean',
                    'Values' => [$product->getEan() ?? $parent?->getEan() ?? ''],
                ];
            }
            if ($swEnabled('sw-manufacturer-productnumber')) {
                $attributes[] = [
                    'Key'    => 'sw-manufacturer-productnumber',
                    'Values' => [$product->getManufacturerNumber() ?? $parent?->getManufacturerNumber() ?? ''],
                ];
            }
            if ($swEnabled('sw-release-date')) {
                $attributes[] = [
                    'Key'    => 'sw-release-date',
                    'Values' => [$product->getReleaseDate()?->format('Y-m-d') ?? ''],
                ];
            }
            if ($swEnabled('sw-description')) {
                $description = $product->getTranslation('description') ?? $parent?->getTranslation('description') ?? '';
                $attributes[] = [
                    'Key'    => 'sw-description',
                    'Values' => [mb_substr(strip_tags((string) $description), 0, 400)],
                ];
            }
            if ($swEnabled('sw-keywords')) {
                $keywords = $product->getCustomSearchKeywords() ?? $parent?->getCustomSearchKeywords() ?? [];
                $attributes[] = [
                    'Key'    => 'sw-keywords',
                    'Values' => [implode(', ', $keywords)],
                ];
            }
            if ($swEnabled('sw-delivery-time')) {
                $deliveryTime = $product->getDeliveryTime() ?? $parent?->getDeliveryTime();
                $attributes[] = [
                    'Key'    => 'sw-delivery-time',
                    'Values' => [$deliveryTime?->getTranslation('name') ?? ''],
                ];
            }
            if ($swEnabled('sw-avg-rating')) {
                $ratingAverage = $product->getRatingAverage();
                $attributes[] = [
                    'Key'    => 'sw-avg-rating',
                    'Values' => [$ratingAverage !== null ? (string) $ratingAverage : ''],
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
            } else {
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
