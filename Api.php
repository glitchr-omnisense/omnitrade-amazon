<?php

namespace Omnitrade\Amazon;

use Omnitrade\Exception\ProviderException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Creators API's two calls, as its documentation shows them: an OAuth 2.0
 * client-credentials token (scope creatorsapi::default, JSON body, valid an
 * hour, kept until a minute before it runs out), then POST
 * {api_url}/getItems or /searchItems with the bearer token, the
 * x-marketplace header and, in the JSON body, partnerTag, marketplace and
 * the resources asked for (lowerCamelCase).
 */
final class Api
{
    public const API_URL = 'https://creatorsapi.amazon/catalog/v1';
    public const SCOPE = 'creatorsapi::default';

    /** What a product is read with: its title, brand, features, images, offers, page. */
    public const RESOURCES = [
        'images.primary.large',
        'images.variants.large',
        'itemInfo.title',
        'itemInfo.byLineInfo',
        'itemInfo.features',
        'itemInfo.externalIds',
        'itemInfo.classifications',
        'offersV2.listings.price',
        'offersV2.listings.availability',
        'offersV2.listings.merchantInfo',
        'parentASIN',
    ];

    private ?string $token = null;
    private int $tokenExpires = 0;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $partnerTag,
        private readonly string $marketplace,
        private readonly string $tokenUrl,
        private readonly string $apiUrl = self::API_URL,
        private readonly int $timeout = 10,
    ) {
    }

    public function marketplace(): string
    {
        return $this->marketplace;
    }

    public function partnerTag(): string
    {
        return $this->partnerTag;
    }

    /**
     * @param list<string> $asins at most 10
     *
     * @return list<array<string, mixed>> the items found (itemsResult.items)
     */
    public function getItems(array $asins): array
    {
        $data = $this->call('getItems', ['itemIds' => array_values(\array_slice($asins, 0, 10)), 'itemIdType' => 'ASIN']);

        return array_values((array) ($data['itemsResult']['items'] ?? []));
    }

    /** @return array{items: list<array<string, mixed>>, total: ?int} the items found (searchResult.items) */
    public function searchItems(string $keywords, int $count = 10): array
    {
        $data = $this->call('searchItems', ['keywords' => $keywords, 'itemCount' => max(1, min(10, $count))]);

        return [
            'items' => array_values((array) ($data['searchResult']['items'] ?? [])),
            'total' => isset($data['searchResult']['totalResultCount']) ? (int) $data['searchResult']['totalResultCount'] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function call(string $operation, array $body): array
    {
        $body += ['partnerTag' => $this->partnerTag, 'marketplace' => $this->marketplace, 'resources' => self::RESOURCES];
        try {
            $response = $this->http->request('POST', rtrim($this->apiUrl, '/').'/'.$operation, [
                'auth_bearer' => $this->token(),
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json', 'x-marketplace' => $this->marketplace],
                'json' => $body,
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
        } catch (HttpExceptionInterface $e) {
            throw new ProviderException('amazon', 'The Creators API did not answer: '.$e->getMessage(), null, $e);
        }
        if (!\is_array($data)) {
            throw new ProviderException('amazon', sprintf('The Creators API answered HTTP %d with a body that is not JSON.', $status));
        }
        if ($status >= 400) {
            $error = $data['errors'][0] ?? $data;
            throw new ProviderException('amazon', (string) ($error['message'] ?? $error['Message'] ?? sprintf('HTTP %d', $status)), isset($error['code']) ? (string) $error['code'] : (string) $status);
        }

        return $data;
    }

    private function token(): string
    {
        if (null !== $this->token && time() < $this->tokenExpires - 60) {
            return $this->token;
        }
        if ('' === $this->clientId || '' === $this->clientSecret) {
            throw new ProviderException('amazon', 'No Creators API credentials (client_id, client_secret).', 'no_credentials');
        }
        try {
            $response = $this->http->request('POST', $this->tokenUrl, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => ['grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'scope' => self::SCOPE],
                'timeout' => $this->timeout,
            ]);
            $data = json_decode($response->getContent(false), true);
            $status = $response->getStatusCode();
        } catch (HttpExceptionInterface $e) {
            throw new ProviderException('amazon', 'The token endpoint did not answer: '.$e->getMessage(), null, $e);
        }
        if ($status >= 400 || !\is_string($data['access_token'] ?? null)) {
            throw new ProviderException('amazon', (string) ($data['error_description'] ?? $data['error'] ?? sprintf('No token (HTTP %d).', $status)), (string) ($data['error'] ?? $status));
        }
        $this->token = $data['access_token'];
        $this->tokenExpires = time() + (int) ($data['expires_in'] ?? 3600);

        return $this->token;
    }
}
