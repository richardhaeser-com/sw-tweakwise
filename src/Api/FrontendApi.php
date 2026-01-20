<?php declare(strict_types=1);

namespace RH\Tweakwise\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FrontendApi
{
    private readonly Client $client;
    public $apiUrl = 'https://gateway.tweakwisenavigator.com';
    public function __construct(private readonly string $instanceKey)
    {
        $this->client = new Client();
    }

    public function getInstance(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/instance/' . $this->instanceKey,
                [
                    'headers' => [
                        'TWN-Source' => 'Shopware plugin',
                        'accept' => 'application/json',
                    ],
                ]
            );
        } catch (GuzzleException $exception) {
            return ['validToken' => false, 'error' => $exception->getMessage()];
        }

        $data = json_decode($response->getBody()->getContents(), true);
        return array_merge($data, ['validToken' => true]);
    }

    public function getSortTemplates(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/catalog/sorttemplates/' . $this->instanceKey,
                [
                    'headers' => [
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

    public function getFacetsForCategory(string $categoryId, string $filterTemplate = null): array
    {
        $parameters = [
            'tn_cid=' . $categoryId,
        ];

        if ($filterTemplate) {
            $parameters[] = 'tn_ft=' . $filterTemplate;
        }

        try {
            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/facets/' . $this->instanceKey . '/?' . implode('&', $parameters),
                [
                    'headers' => [
                        'accept' => 'application/json',
                    ],
                ]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $exception) {
            return ['error' => true, 'code' => $exception->getCode(), 'message' => $exception->getMessage()];
        }
    }

    public function getAttributesForFacet(string $urlKey, string $categoryId): array
    {
        try {
            $parameters = [
                'tn_cid=' . $categoryId,
            ];

            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/facets/' . $urlKey . '/attributes/' . $this->instanceKey. '/?' . implode('&', $parameters),
                [
                    'headers' => [
                        'accept' => 'application/json',
                    ],
                ]
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $exception) {
            return ['error' => true, 'code' => $exception->getCode(), 'message' => $exception->getMessage()];
        }
    }
    public function getBuilderTemplates(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/catalog/builders/' . $this->instanceKey,
                [
                    'headers' => [
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
        $response = $this->client->request(
            'GET',
            $this->apiUrl . '/categorytree/' . $this->instanceKey,
            [
                'headers' => [
                    'accept' => 'application/json',
                ],
            ]
        );
        $data = json_decode($response->getBody()->getContents(), true);
        return $this->categoryPathsFromArray($data, ' > ', 1);
    }

    public function getFilterTemplates(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->apiUrl . '/catalog/templates/' . $this->instanceKey,
                [
                    'headers' => [
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

    private function categoryPathsFromArray(
        array $tree,
        string $separator = ' > ',
        int $skipLevels = 0,
        ?int $maxLevels = null
    ): array {
        $results = [];

        foreach (($tree['categories'] ?? []) as $category) {
            $this->walkCategoryArray($category, [], $results, $separator, $skipLevels, $maxLevels);
        }

        return $results;
    }

    private function walkCategoryArray(
        array $category,
        array $trail,
        array &$results,
        string $separator,
        int $skipLevels,
        ?int $maxLevels
    ): void {
        $trail[] = (string)($category['title'] ?? '');

        $visible = array_slice($trail, $skipLevels);
        if ($maxLevels !== null) {
            $visible = array_slice($visible, 0, $maxLevels);
        }

        $categoryId = (string)($category['categorypath'] ?? '');

        // Voeg ELKE node toe (dus ook tussenliggende)
        if ($categoryId !== '' && count($visible) > 0) {
            $results[$categoryId] = implode($separator, $visible);
        }

        // Stop dieper gaan als maxLevels is bereikt
        if ($maxLevels !== null && count($visible) >= $maxLevels) {
            return;
        }

        $children = $category['children'] ?? [];
        if (is_array($children) && $children !== []) {
            foreach ($children as $child) {
                $this->walkCategoryArray($child, $trail, $results, $separator, $skipLevels, $maxLevels);
            }
        }
    }
}
