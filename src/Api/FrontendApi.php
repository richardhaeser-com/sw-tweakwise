<?php declare(strict_types=1);

namespace RH\Tweakwise\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FrontendApi
{
    private Client $client;
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
}
