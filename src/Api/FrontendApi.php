<?php declare(strict_types=1);

namespace RH\Tweakwise\Api;

use GuzzleHttp\Client;

class FrontendApi
{
    private Client $client;
    public $apiUrl = 'https://gateway.tweakwisenavigator.com';
    public function __construct(private readonly string $instanceKey)
    {
        $this->client = new Client();
    }

    public function getInstance()
    {
        $response = $this->client->request('GET', $this->apiUrl . '/instance/' . $this->instanceKey,
            [
                'headers' => [
                    'TWN-Source' => 'Shopware plugin',
                    'accept' => 'application/json',
                ],
            ]
    );
        return json_decode($response->getBody()->getContents());
    }
}